/* Dual Price for Woo Blocks (Cart & Checkout) – simplified, more aggressive scan */
(function () {
  const cfg = window.WDP_DUAL_PRICE || {};
  const rate = Number(cfg.rate || 1.95583);
  const decimals = Number(cfg.decimals || 2);
  const symbol = String(cfg.symbol || '€');

  const fmt = n => {
    try {
      return new Intl.NumberFormat('bg-BG', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
      }).format(Number(n));
    } catch (e) {
      return Number(n).toFixed(decimals);
    }
  };

  const parseBGN = txt => {
    if (!txt) return null;
    let t = txt.replace(/\s+/g, ' ').trim();
    t = t.replace(/[^\d,.,-]/g, '');
    if (t.includes(',')) t = t.replace(/\./g, '').replace(',', '.');
    const m = t.match(/-?\d+(\.\d+)?/);
    return m ? parseFloat(m[0]) : null;
  };

  const eurInline = bgn => {
    const eur = (Number(bgn) / rate);
    const span = document.createElement('span');
    span.className = 'wdp-eur-inline';
    span.textContent = ` (${fmt(eur)} ${symbol})`;
    return span;
  };

  function addInlineToPricePart(pricePart) {
    if (!pricePart || pricePart.querySelector('.wdp-eur-inline')) return;
    const inner = pricePart.querySelector('.wc-block-formatted-money-amount, .amount, bdi') || pricePart;
    const bgn = parseBGN(inner.textContent);
    if (bgn == null) return;
    pricePart.appendChild(eurInline(bgn));
  }

  function processItemPrice(priceContainer) {
    if (!priceContainer) return;
    const regularEl = priceContainer.querySelector('.wc-block-components-product-price__regular, del');
    const saleEl    = priceContainer.querySelector('.wc-block-components-product-price__value.is-discounted, ins.is-discounted');
    const singleEl  = priceContainer.querySelector('.wc-block-components-product-price__value:not(.is-discounted)');

    if (regularEl && saleEl) {
      addInlineToPricePart(regularEl);
      addInlineToPricePart(saleEl);
      regularEl.classList.add('wdp-line-old');
      saleEl.classList.add('wdp-line-new');
    } else if (singleEl) {
      addInlineToPricePart(singleEl);
      singleEl.classList.add('wdp-line-single');
    } else {
      addInlineToPricePart(priceContainer);
      priceContainer.classList.add('wdp-line-single');
    }
  }

  function processTotals(root) {
    root.querySelectorAll('.wc-block-components-totals-item__value, .wc-block-components-payment-summary__value').forEach(node => {
      if (node.querySelector('.wdp-eur-inline')) return;
      const inner = node.querySelector('.wc-block-formatted-money-amount, .amount, bdi') || node;
      const bgn = parseBGN(inner.textContent);
      if (bgn == null) return;
      node.appendChild(eurInline(bgn));
    });
  }

  function processClassicOrderSummary() {
    document.querySelectorAll('.woocommerce-table--order-details, .woocommerce-table').forEach(table => {
      table.querySelectorAll('td.product-total, td.woocommerce-table__product-total, td.product-total.product-total').forEach(td => {
        if (td.querySelector('.wdp-eur-inline')) return;
        const inner = td.querySelector('.amount, bdi') || td;
        const bgn = parseBGN(inner.textContent);
        if (bgn == null) return;
        td.appendChild(eurInline(bgn));
      });

      table.querySelectorAll('tfoot td, tfoot th').forEach(cell => {
        if (cell.querySelector('.wdp-eur-inline')) return;
        const inner = cell.querySelector('.amount, bdi') || cell;
        if (!inner) return;
        const bgn = parseBGN(inner.textContent);
        if (bgn == null) return;
        cell.appendChild(eurInline(bgn));
      });
    });
  }

  function customizeTotalsLabels(root) {
    root.querySelectorAll('.wc-block-components-totals-item').forEach(item => {
      const labelEl = item.querySelector('.wc-block-components-totals-item__label');
      if (!labelEl) return;
      const valueEl = item.querySelector('.wc-block-components-totals-item__value');
      let rawLabel = labelEl.textContent.trim();

      if (/^Общо$/i.test(rawLabel) && !/:$/.test(rawLabel)) {
        labelEl.textContent = 'Общо:';
      }

      const isShippingRow =
        item.classList.contains('wc-block-components-totals-item--shipping') ||
        item.classList.contains('wc-block-components-totals-item--shipping-method') ||
        /доставка/i.test(rawLabel);

      if (isShippingRow) {
        if (labelEl.textContent !== 'Доставка:') {
          labelEl.textContent = 'Доставка:';
        }
        if (valueEl) {
          const valTxt = valueEl.textContent.trim();
          if (/безплатно/i.test(valTxt)) {
            const eurSpans = Array.from(valueEl.querySelectorAll('.wdp-eur-inline'));
            if (!valueEl.querySelector('strong')) {
              valueEl.innerHTML = '<strong>БЕЗПЛАТНО</strong>';
              eurSpans.forEach(s => valueEl.appendChild(s));
            }
          }
        }
      }
    });
  }

  function forcePlaceOrderOnlyLabel() {
    const btn = document.querySelector('.wc-block-checkout .wc-block-components-checkout-place-order-button');
    if (!btn) return;
    btn.querySelectorAll('.wdp-eur-inline, .wdp-btn-eur-inline').forEach(n => n.remove());
    const normalized = btn.textContent.trim();
    if (normalized === 'Завърши поръчката') return;
    btn.textContent = 'Завърши поръчката';
  }

  function customizePaymentStepTitle() {
    const titles = document.querySelectorAll(
      '.wc-block-checkout h2.wc-block-components-title.wc-block-components-checkout-step__title'
    );
    titles.forEach(title => {
      const txt = title.textContent.trim();
      if (/^payment options$/i.test(txt) || /^payment$/i.test(txt)) {
        title.textContent = 'Начин на плащане';
      }
    });
  }

  function scan() {
    // Цени на редовете – Cart & Checkout blocks
    document
      .querySelectorAll('.wc-block-cart-item__prices, .wc-block-checkout .wc-block-components-order-summary-item .wc-block-components-product-price')
      .forEach(processItemPrice);

    // Totals – Cart & Checkout
    document.querySelectorAll('.wc-block-cart, .wc-block-checkout')
      .forEach(root => {
        processTotals(root);
        customizeTotalsLabels(root);
      });

    // Classic Woo order tables
    processClassicOrderSummary();
    // Checkout tweaks
    forcePlaceOrderOnlyLabel();
    customizePaymentStepTitle();
  }

  function init() {
    scan();
    // Aggressive rescan for dynamic/mobile rendering
    setInterval(scan, 2000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();