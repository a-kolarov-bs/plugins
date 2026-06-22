<?php
/**
 * Plugin Name: Dual Price (EUR) for WooCommerce
 * Description: Елегантен двоен показ на цени: BGN + EUR в скоби на същия ред. Поддържани: продукт/архив (вкл. намаление с del/ins), вариации, mini-cart (Kadence off-canvas), cart/checkout (classic), имейли.
 * Version: 1.5.0
 * Author: You
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WDP_Dual_Price {
	private static $instance = null;
	private $opt_key = 'wdp_settings';

	public static function instance() {
		if ( self::$instance === null ) self::$instance = new self();
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Product & Archive (заменяме price html с BGN + (EUR))
		add_filter( 'woocommerce_get_price_html', [ $this, 'filter_product_price_html' ], 99, 2 );
		add_filter( 'woocommerce_available_variation', [ $this, 'filter_variation_price_html' ], 99, 3 );

		// Cart rows (classic)
		add_filter( 'woocommerce_cart_item_price', [ $this, 'cart_item_price' ], 99, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', [ $this, 'cart_item_subtotal' ], 99, 3 );

		// Totals (classic cart/checkout) – добавяме (EUR) към стойностите, без допълнителни редове
		add_filter( 'woocommerce_cart_subtotal', [ $this, 'cart_subtotal_html' ], 99, 3 );
		add_filter( 'woocommerce_cart_totals_order_total_html', [ $this, 'order_total_html' ], 99 );

		// Mini‑cart (Kadence off‑canvas): към quantity × price и „Междинна сума:“ добавяме (EUR)
		add_filter( 'woocommerce_widget_cart_item_quantity', [ $this, 'mini_cart_item_qty' ], 10, 3 );
		add_filter( 'woocommerce_widget_shopping_cart_subtotal', [ $this, 'mini_cart_subtotal_eur' ], 99, 1 );

		// Emails & Thank you
		add_filter( 'woocommerce_get_formatted_order_total', [ $this, 'order_total_secondary' ], 99, 4 );
		add_filter( 'woocommerce_email_order_item_subtotal', [ $this, 'email_item_subtotal' ], 99, 3 );
	}

	
	/* ================= Settings & Assets ================ */

	public function enqueue_assets() {
		wp_enqueue_style(
			'wdp-dual-price',
			plugin_dir_url( __FILE__ ) . 'assets/dual-price_Version3.css',
			[],
			'1.5.0'
		);

		$opt = $this->get_settings();
		$data = [
			'rate'     => (float) str_replace( ',', '.', $opt['rate_bgn_per_eur'] ),
			'decimals' => (int) $opt['decimals'],
			'symbol'   => html_entity_decode( get_woocommerce_currency_symbol( 'EUR' ) ),
		];

		// JS за Woo Blocks (Cart/Checkout): добавя inline "(EUR)" към стойностите
		wp_enqueue_script(
			'wdp-dual-price-blocks',
			plugin_dir_url( __FILE__ ) . 'assets/dual-price-blocks_Version2.js',
			[],
			'1.5.0',
			true
		);
		wp_add_inline_script( 'wdp-dual-price-blocks', 'window.WDP_DUAL_PRICE = ' . wp_json_encode( $data ) . ';', 'before' );
	}

	public function defaults() {
		return [
			'rate_bgn_per_eur' => '1.95583',
			'decimals'         => '2',
			'show_in_emails'   => 'yes',
		];
	}
	public function get_settings() {
		$opt  = get_option( $this->opt_key, [] );
		return wp_parse_args( $opt, $this->defaults() );
	}
	public function register_settings() {
		register_setting( 'wdp_settings_group', $this->opt_key, [
			'type'              => 'array',
			'default'           => $this->defaults(),
			'sanitize_callback' => [ $this, 'sanitize' ],
		] );
	}
	public function sanitize( $input ) {
		$d = $this->defaults();
		$out = [];
		$out['rate_bgn_per_eur'] = isset( $input['rate_bgn_per_eur'] ) ? preg_replace( '/[^0-9\.,]/', '', $input['rate_bgn_per_eur'] ) : $d['rate_bgn_per_eur'];
		$out['decimals']         = isset( $input['decimals'] ) ? (string) max( 0, (int) $input['decimals'] ) : $d['decimals'];
		$out['show_in_emails']   = ( isset( $input['show_in_emails'] ) && $input['show_in_emails'] === 'no' ) ? 'no' : 'yes';
		return $out;
	}
// ВМЪКНИ ТОВА ВЪТРЕ В КЛАСА WDP_Dual_Price,
// точно ПРЕДИ реда:  /* ================= Helpers ================ */

public function add_settings_page() {
	add_options_page(
		'Dual Price',
		'Dual Price',
		'manage_options',
		'wdp-dual-price',
		[ $this, 'render_settings_page' ]
	);
}

public function render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) return;
	$opt = $this->get_settings(); ?>
	<div class="wrap">
		<h1>Dual Price (EUR)</h1>
		<form method="post" action="options.php">
			<?php settings_fields( 'wdp_settings_group' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="wdp_rate">Курс (1 EUR = … BGN)</label></th>
					<td>
						<input id="wdp_rate" name="<?php echo esc_attr( $this->opt_key ); ?>[rate_bgn_per_eur]" type="text" value="<?php echo esc_attr( $opt['rate_bgn_per_eur'] ); ?>" class="regular-text" />
						<p class="description">По подразбиране: 1.95583</p>
					</td>
				</tr>
				<tr>
					<th><label for="wdp_decimals">Десетични знаци (EUR)</label></th>
					<td>
						<input id="wdp_decimals" name="<?php echo esc_attr( $this->opt_key ); ?>[decimals]" type="number" min="0" max="4" value="<?php echo esc_attr( $opt['decimals'] ); ?>" />
					</td>
				</tr>
				<tr>
					<th>Показвай в имейли</th>
					<td>
						<label>
							<input type="checkbox" name="<?php echo esc_attr( $this->opt_key ); ?>[show_in_emails]" value="yes" <?php checked( $opt['show_in_emails'], 'yes' ); ?> />
							Да
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
		<p><em>EUR е вторична информативна валута. Поръчките се обработват в BGN.</em></p>
	</div>
	<?php
}
	/* ================= Helpers ================ */

	private function rate() {
		$opt = $this->get_settings();
		$r = (float) str_replace( ',', '.', $opt['rate_bgn_per_eur'] );
		return $r > 0 ? $r : 1.95583;
	}
	private function decimals() {
		$opt = $this->get_settings();
		return max( 0, (int) $opt['decimals'] );
	}
	private function eur_plain( $amount_bgn ) {
		$rate = $this->rate();
		$eur  = $rate > 0 ? (float) $amount_bgn / $rate : 0.0;
		$eur  = round( $eur, $this->decimals() );
		return wp_strip_all_tags( wc_price( $eur, [ 'currency' => 'EUR' ] ) ); // "194,29 €"
	}
	private function eur_inline_html( $amount_bgn ) {
		return ' <span class="wdp-eur-inline">(' . esc_html( $this->eur_plain( $amount_bgn ) ) . ')</span>';
	}

	/* ================= Product / Archive ================= */

	public function filter_product_price_html( $html, $product ) {
		if ( ! $product instanceof WC_Product ) return $html;

		$reg = $product->get_regular_price();
		$sal = $product->get_sale_price();

		$reg_disp = $reg !== '' ? wc_get_price_to_display( $product, [ 'price' => $reg ] ) : null;
		$sal_disp = $sal !== '' ? wc_get_price_to_display( $product, [ 'price' => $sal ] ) : null;

		if ( $product->is_on_sale() && $reg_disp && $sal_disp && $sal_disp < $reg_disp ) {
			$out  = '<del class="wdp-price-regular">';
			$out .= wc_price( $reg_disp );
			$out .= $this->eur_inline_html( $reg_disp );
			$out .= '</del> </br>';
			$out .= '<ins class="wdp-price-sale">';
			$out .= wc_price( $sal_disp );
			$out .= $this->eur_inline_html( $sal_disp );
			$out .= '</ins>';
			return $out;
		}

		$disp = wc_get_price_to_display( $product );
		if ( $disp ) {
			return wc_price( $disp ) . $this->eur_inline_html( $disp );
		}
		return $html;
	}

	public function filter_variation_price_html( $variation_data, $product, $variation ) {
		if ( isset( $variation_data['price_html'] ) && $variation instanceof WC_Product_Variation ) {
			$reg = $variation->get_regular_price();
			$sal = $variation->get_sale_price();

			$reg_disp = $reg !== '' ? wc_get_price_to_display( $variation, [ 'price' => $reg ] ) : null;
			$sal_disp = $sal !== '' ? wc_get_price_to_display( $variation, [ 'price' => $sal ] ) : null;

			if ( $variation->is_on_sale() && $reg_disp && $sal_disp && $sal_disp < $reg_disp ) {
				$variation_data['price_html']  = '<del class="wdp-price-regular">' . wc_price( $reg_disp ) . $this->eur_inline_html( $reg_disp ) . '</del> ';
				$variation_data['price_html'] .= '<ins class="wdp-price-sale">' . wc_price( $sal_disp ) . $this->eur_inline_html( $sal_disp ) . '</ins>';
			} else {
				$disp = wc_get_price_to_display( $variation );
				if ( $disp ) {
					$variation_data['price_html'] = wc_price( $disp ) . $this->eur_inline_html( $disp );
				}
			}
		}
		return $variation_data;
	}

	/* ================= Cart rows (classic) ================= */

	public function cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		$product = $cart_item['data'] ?? null;
		if ( ! $product instanceof WC_Product ) return $price_html;
		$display = wc_get_price_to_display( $product );
		if ( $display > 0 ) {
			return $price_html . $this->eur_inline_html( $display );
		}
		return $price_html;
	}

	public function cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		$include_tax = wc()->cart && wc()->cart->display_prices_including_tax();
		$amount_bgn  = (float) ( $cart_item['line_total'] + ( $include_tax ? $cart_item['line_tax'] : 0 ) );
		if ( $amount_bgn > 0 ) {
			return $subtotal_html . $this->eur_inline_html( $amount_bgn );
		}
		return $subtotal_html;
	}

	/* ================= Totals (classic) ================= */

	public function cart_subtotal_html( $cart_subtotal_html, $compound, $cart ) {
		$amount = (float) $cart->get_cart_contents_total() + ( $cart->display_prices_including_tax() ? $cart->get_cart_contents_tax() : 0 );
		if ( $amount > 0 ) {
			$cart_subtotal_html .= $this->eur_inline_html( $amount );
		}
		return $cart_subtotal_html;
	}

	public function order_total_html( $order_total_html ) {
		$total = (float) WC()->cart->get_total( 'edit' );
		if ( $total > 0 ) {
			$order_total_html .= '<span class="wdp-eur-inline-total">' . $this->eur_inline_html( $total ) . '</span>';
		}
		return $order_total_html;
	}

	/* ================= Mini‑cart (Kadence off‑canvas) ================= */

	public function mini_cart_item_qty( $html, $cart_item, $cart_item_key ) {
		$include_tax = wc()->cart && wc()->cart->display_prices_including_tax();
		$amount = (float) ( $cart_item['line_total'] + ( $include_tax ? $cart_item['line_tax'] : 0 ) );
		if ( $amount <= 0 ) return $html;
		return $html . $this->eur_inline_html( $amount );
	}

	public function mini_cart_subtotal_eur( $subtotal_html ) {
		$cart = WC()->cart;
		if ( ! $cart ) return $subtotal_html;
		$amount = (float) $cart->get_cart_contents_total() + ( $cart->display_prices_including_tax() ? $cart->get_cart_contents_tax() : 0 );
		if ( $amount <= 0 ) return $subtotal_html;
		return $subtotal_html . $this->eur_inline_html( $amount );
	}

	/* ================= Orders & Emails ================= */

	public function order_total_secondary( $formatted_total, $order, $tax_display, $display_refunded ) {
		$opt = $this->get_settings();
		if ( ( $opt['show_in_emails'] ?? 'yes' ) !== 'yes' ) return $formatted_total;
		$total = (float) $order->get_total();
		if ( $total <= 0 ) return $formatted_total;
		return $formatted_total . ' <span class="wdp-eur-inline-email">' . $this->eur_inline_html( $total ) . '</span>';
	}

	public function email_item_subtotal( $subtotal_html, $item, $order ) {
		$amount = (float) $item->get_total() + (float) $item->get_total_tax();
		if ( $amount > 0 ) {
			$subtotal_html .= ' <span class="wdp-eur-inline-email">' . $this->eur_inline_html( $amount ) . '</span>';
		}
		return $subtotal_html;
	}
}

WDP_Dual_Price::instance();