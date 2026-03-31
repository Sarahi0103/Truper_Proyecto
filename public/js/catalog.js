(function () {
  const STORAGE_CART = 'truper_cart';
  const STORAGE_FAV = 'truper_favorites';

  function readJson(key, fallback) {
    try {
      const raw = localStorage.getItem(key);
      return raw ? JSON.parse(raw) : fallback;
    } catch (_) {
      return fallback;
    }
  }

  function writeJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
  }

  function getCart() {
    return readJson(STORAGE_CART, []);
  }

  function setCart(items) {
    writeJson(STORAGE_CART, items);
  }

  function getFavs() {
    return readJson(STORAGE_FAV, []);
  }

  function setFavs(items) {
    writeJson(STORAGE_FAV, items);
  }

  function toNumber(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  function money(v) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0 }).format(toNumber(v));
  }

  function updateCartBadge() {
    const cart = getCart();
    const count = cart.reduce((sum, i) => sum + toNumber(i.quantity), 0);
    const badge = document.getElementById('cartCount');
    if (badge) badge.textContent = String(count);
  }

  function toggleFavorite(product) {
    const favs = getFavs();
    const exists = favs.find((p) => p.sku === product.sku);
    const next = exists ? favs.filter((p) => p.sku !== product.sku) : favs.concat([product]);
    setFavs(next);
    renderFavoriteButtons();
  }

  function renderFavoriteButtons() {
    const favs = getFavs();
    document.querySelectorAll('[data-fav-sku]').forEach((btn) => {
      const isFav = favs.some((f) => f.sku === btn.dataset.favSku);
      btn.classList.toggle('active', isFav);
      btn.textContent = isFav ? 'Favorito' : 'Favoritos';
    });
  }

  function addToCart(product) {
    const cart = getCart();
    const existing = cart.find((p) => p.sku === product.sku);
    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({ ...product, quantity: 1 });
    }
    setCart(cart);
    updateCartBadge();
    renderCart();
    if (window.showAlert) {
      window.showAlert('Producto agregado al carrito', 'success');
    }
  }

  function removeFromCart(sku) {
    const next = getCart().filter((i) => i.sku !== sku);
    setCart(next);
    updateCartBadge();
    renderCart();
  }

  function changeQty(sku, delta) {
    const cart = getCart();
    const item = cart.find((i) => i.sku === sku);
    if (!item) return;
    item.quantity = Math.max(1, toNumber(item.quantity) + delta);
    setCart(cart);
    updateCartBadge();
    renderCart();
  }

  function renderCart() {
    const cart = getCart();
    const list = document.getElementById('cartList');
    const totalEl = document.getElementById('cartTotalAmount');
    if (!list || !totalEl) return;

    if (cart.length === 0) {
      list.innerHTML = '<p class="text-muted">Tu carrito está vacío.</p>';
      totalEl.textContent = money(0);
      return;
    }

    list.innerHTML = cart.map((item) => {
      const line = toNumber(item.unit_price) * toNumber(item.quantity);
      return `
        <div class="cart-item">
          <strong>${item.name}</strong>
          <div class="text-muted">${item.sku}</div>
          <div class="d-flex justify-between align-center mt-1">
            <div>${money(item.unit_price)} x ${item.quantity}</div>
            <div>
              <button class="btn btn-small btn-ghost" data-dec="${item.sku}">-</button>
              <button class="btn btn-small btn-ghost" data-inc="${item.sku}">+</button>
              <button class="btn btn-small btn-danger" data-remove="${item.sku}">x</button>
            </div>
          </div>
          <div class="text-right mt-1"><strong>${money(line)}</strong></div>
        </div>
      `;
    }).join('');

    const total = cart.reduce((sum, item) => sum + toNumber(item.unit_price) * toNumber(item.quantity), 0);
    totalEl.textContent = money(total);

    list.querySelectorAll('[data-remove]').forEach((btn) => {
      btn.addEventListener('click', () => removeFromCart(btn.dataset.remove));
    });
    list.querySelectorAll('[data-inc]').forEach((btn) => {
      btn.addEventListener('click', () => changeQty(btn.dataset.inc, 1));
    });
    list.querySelectorAll('[data-dec]').forEach((btn) => {
      btn.addEventListener('click', () => changeQty(btn.dataset.dec, -1));
    });
  }

  function drawTicketWindow() {
    const cart = getCart();
    if (cart.length === 0) {
      if (window.showAlert) window.showAlert('No hay productos en el carrito', 'warning');
      return;
    }

    const now = new Date();
    const folio = `TCK-${now.getTime().toString().slice(-8)}`;
    const date = now.toLocaleString('es-MX');
    const rows = cart.map((item) => {
      const line = toNumber(item.unit_price) * toNumber(item.quantity);
      return `<div>${item.name}<br>${item.quantity} x ${money(item.unit_price)} = ${money(line)}</div>`;
    }).join('<br>');
    const total = cart.reduce((sum, item) => sum + toNumber(item.unit_price) * toNumber(item.quantity), 0);

    const html = `
      <html><head><title>Ticket ${folio}</title>
      <style>
        body{font-family:monospace;margin:0;padding:10px}
        .ticket-print{width:280px}
        .ticket-print h2{text-align:center;font-size:14px;margin-bottom:8px}
        .line{border-top:1px dashed #000;margin:6px 0}
      </style></head>
      <body onload="window.print()">
        <div class="ticket-print">
          <h2>TRUPER</h2>
          <div>Folio: ${folio}</div>
          <div>Fecha: ${date}</div>
          <div class="line"></div>
          ${rows}
          <div class="line"></div>
          <div><strong>Total: ${money(total)}</strong></div>
          <div>Gracias por su compra</div>
        </div>
      </body></html>`;

    const win = window.open('', '_blank', 'width=360,height=720');
    if (win) {
      win.document.open();
      win.document.write(html);
      win.document.close();
    }
  }

  function setupHandlers() {
    document.querySelectorAll('[data-add-product]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const product = {
          id: btn.dataset.id,
          sku: btn.dataset.sku,
          name: btn.dataset.name,
          unit_price: toNumber(btn.dataset.price)
        };
        addToCart(product);
      });
    });

    document.querySelectorAll('[data-fav-product]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const product = {
          sku: btn.dataset.sku,
          name: btn.dataset.name
        };
        toggleFavorite(product);
      });
    });

    const searchInput = document.getElementById('catalogSearch');
    if (searchInput) {
      searchInput.addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('[data-product-card]').forEach((card) => {
          const haystack = `${card.dataset.name} ${card.dataset.sku} ${card.dataset.category}`.toLowerCase();
          card.style.display = haystack.includes(q) ? '' : 'none';
        });
      });
    }

    const openBtn = document.getElementById('openCart');
    const closeBtn = document.getElementById('closeCart');
    const drawer = document.getElementById('cartDrawer');
    if (openBtn && drawer) openBtn.addEventListener('click', () => drawer.classList.add('open'));
    if (closeBtn && drawer) closeBtn.addEventListener('click', () => drawer.classList.remove('open'));

    const ticketBtn = document.getElementById('printTicket');
    if (ticketBtn) ticketBtn.addEventListener('click', drawTicketWindow);

    const clearBtn = document.getElementById('clearCart');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        setCart([]);
        updateCartBadge();
        renderCart();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    setupHandlers();
    renderFavoriteButtons();
    updateCartBadge();
    renderCart();
  });
})();
