/**
 * Dynamic badge override за WooCommerce Blocks (Homepage Product / Featured Products).
 * 1) Търси wc-block sale badge.
 * 2) Ако може, чете regular + sale цени от DOM.
 * 3) Ако цената липсва (скрита или премахната), fallback към Store API или WP REST за процент.
 * 4) Формат: "Спести −XX%".
 */
(function() {
  const FORMAT = (pct) => `Спести −${pct}%`;

  const parsePrice = (el) => {
    if (!el) return null;
    const txt = el.textContent.replace(/\s+/g,'').replace(',', '.');
    const m = txt.match(/(\d+(\.\d+)?)/);
    return m ? parseFloat(m[1]) : null;
  };

  function updateBadge(badge) {
    // Опит за DOM цени
    const card = badge.closest('.wc-block-grid__product, .wp-block-product-template, .wc-block-components-product-container');
    if (!card) return;

    const regularEl = card.querySelector('del .woocommerce-Price-amount, .wc-block-components-product-price__regular');
    const saleEl    = card.querySelector('ins .woocommerce-Price-amount, .wc-block-components-product-price__value.is-discounted');

    const regular = parsePrice(regularEl);
    const sale    = parsePrice(saleEl);

    let pct = null;

    if (regular && sale && sale < regular) {
      pct = Math.round( (1 - sale / regular) * 100 );
    }

    const textEl = badge.querySelector('.wc-block-components-product-sale-badge__text') || badge;

    if (pct) {
      textEl.textContent = FORMAT(pct);
      return;
    }

    // Fallback – цените липсват (строго скрити): опит чрез REST API (ако имаш product ID/slug)
    // WooCommerce Blocks обикновено не дава ID лесно. Ако имаш data-product-id атрибут, използвай го.
    const link = card.querySelector('a[href*="/product/"]');
    if (!link) return;

    // Извличаме slug от URL /product/slug/
    const slugMatch = link.getAttribute('href').match(/\/product\/([^\/]+)\/?/);
    if (!slugMatch) return;
    const slug = slugMatch[1];

    fetch(`/wp-json/wp/v2/product?slug=${encodeURIComponent(slug)}`)
      .then(r => r.ok ? r.json() : [])
      .then(products => {
        if (!Array.isArray(products) || !products.length) return;
        const p = products[0];
        if (p && typeof p.discount_percent !== 'undefined' && p.discount_percent) {
          textEl.textContent = FORMAT(p.discount_percent);
        }
      })
      .catch(() => {});
  }

  function init() {
    document.querySelectorAll('.wc-block-components-product-sale-badge').forEach(updateBadge);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();