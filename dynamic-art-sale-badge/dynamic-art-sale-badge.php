<?php
/**
 * Plugin Name: Dynamic Art Sale Badge
 * Description: Динамичен badge „Спести −XX%“ за WooCommerce (Shop + Single) и Woo Blocks (Featured/Product Grid) — без да показва цена.
 * Version: 1.2.0
 * Author: You
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/** Настройка: percent | percent_and_saved | up_to_percent */
if ( ! defined( 'DASB_BADGE_MODE' ) ) {
	define( 'DASB_BADGE_MODE', 'percent' );
}

/** Стил (използваш твоя sale-badge.css) */
add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style( 'dasb-style', plugin_dir_url( __FILE__ ) . 'assets/sale-badge.css', [], '1.2.0' );
}, 20 );

/**
 * Изчислява процента/спестеното за продукт (simple/variable).
 *
 * @param WC_Product $product
 * @return array{percent:int|null,saved:float|null,regular:float|null,sale:float|null}
 */
function dasb_get_discount( $product ) {
	$out = [ 'percent' => null, 'saved' => null, 'regular' => null, 'sale' => null ];
	if ( ! $product instanceof WC_Product || ! $product->is_on_sale() ) return $out;

	if ( $product->is_type( 'simple' ) || $product->is_type( 'external' ) ) {
		$regular = (float) wc_get_price_to_display( $product, [ 'price' => $product->get_regular_price() ] );
		$sale    = (float) wc_get_price_to_display( $product, [ 'price' => $product->get_sale_price() ] );
	} elseif ( $product->is_type( 'variable' ) ) {
		// Най-голям реален процент: max regular срещу min sale
		$regular = (float) $product->get_variation_regular_price( 'max', true );
		$sale    = (float) $product->get_variation_sale_price( 'min', true );
	} else {
		return $out;
	}

	if ( $regular > 0 && $sale > 0 && $sale < $regular ) {
		$out['regular'] = $regular;
		$out['sale']    = $sale;
		$out['saved']   = $regular - $sale;
		$out['percent'] = (int) round( ( 1 - ( $sale / $regular ) ) * 100 );
	}
	return $out;
}

/** Форматира текста за badge според режима */
function dasb_badge_text( array $d, WC_Product $product ) {
	if ( empty( $d['percent'] ) ) return '';
	$pct = (int) $d['percent'];

	switch ( DASB_BADGE_MODE ) {
		case 'percent_and_saved':
			$saved = isset( $d['saved'] ) ? wc_price( $d['saved'] ) : '';
			return sprintf( 'Спести −%d%%%s', $pct, $saved ? " ($saved)" : '' );
		case 'up_to_percent':
			return $product->is_type( 'variable' ) ? sprintf( 'До −%d%%', $pct ) : sprintf( 'Спести −%d%%', $pct );
		case 'percent':
		default:
			return sprintf( 'Спести −%d%%', $pct );
	}
}

/** Замяна на стандартния Woo sale flash (Shop + Single) */
add_filter( 'woocommerce_sale_flash', function( $html, $post, $product ) {
	$d     = dasb_get_discount( $product );
	$label = dasb_badge_text( $d, $product );
	if ( ! $label ) return $html;

	$new  = '<span class="my-sale-badge" aria-hidden="true">' . esc_html( $label ) . '</span>';
	$new .= '<span class="screen-reader-text">' . esc_html__( 'Продукт с намаление', 'dynamic-art-sale-badge' ) . ' ' . esc_html( $label ) . '</span>';
	return $new;
}, 10, 3 );

/**
 * WooCommerce Blocks (Featured / Product Grid на начална страница):
 * Заменяме „Промоция“ със „Спести −XX%“ дори когато цената е скрита.
 */
add_filter( 'render_block_woocommerce/product-sale-badge', function( $block_content, $block ) {
	$product_id = 0;

	// Woo Blocks подават postId в контекста
	if ( ! empty( $block['context']['postId'] ) ) {
		$product_id = (int) $block['context']['postId'];
	} else {
		// Fallback (рядко нужно)
		$product_id = get_the_ID();
	}

	if ( ! $product_id ) return $block_content;

	$product = wc_get_product( $product_id );
	if ( ! $product || ! $product->is_on_sale() ) return $block_content;

	$d     = dasb_get_discount( $product );
	$label = dasb_badge_text( $d, $product );
	if ( ! $label ) return $block_content;

	// Заместваме вътрешния текст без да променяме структура/класове
	$block_content = preg_replace(
		'#(<span class="wc-block-components-product-sale-badge__text"[^>]*>)(.*?)(</span>)#is',
		'$1' . esc_html( $label ) . '$3',
		$block_content,
		1
	);

	$block_content = preg_replace(
		'#(<span class="screen-reader-text"[^>]*>)(.*?)(</span>)#is',
		'$1' . esc_html__( 'Продукт с намаление', 'dynamic-art-sale-badge' ) . ' ' . esc_html( $label ) . '$3',
		1
	);

	return $block_content;
}, 10, 2 );