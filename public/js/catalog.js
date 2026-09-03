(function () {
  const isOnlineMode = window.location.search.includes('mode=online') || window.location.pathname.includes('tienda.php');
  const STORAGE_CART = isOnlineMode ? 'fox_cart' : 'truper_cart';
  let selectedQuickCategory = '';
  let selectedColorFilter = '';

  let allProducts = [];
  try {
    const dataEl = document.getElementById('products-data');
    if (dataEl) {
      allProducts = JSON.parse(dataEl.textContent) || [];
    }
  } catch (err) {
    console.error('Error parsing products data:', err);
  }

  let productGroupsColorsMap = {};
  try {
    const grpEl = document.getElementById('product-groups-data');
    if (grpEl) {
      const grps = JSON.parse(grpEl.textContent) || [];
      grps.forEach((g) => {
        if (g && g.name) {
          productGroupsColorsMap[String(g.name).trim().toLowerCase()] = g.color || '#FF7F00';
        }
      });
    }
  } catch (err) {
    console.error('Error parsing product groups data:', err);
  }
  window.productGroupsColorsMap = productGroupsColorsMap;

  let filteredProducts = [...allProducts];
  let currentPage = 1;
  const itemsPerPage = 24;

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
    let cart = readJson(STORAGE_CART, []);
    return Array.isArray(cart) ? cart : [];
  }

  function setCart(items) {
    writeJson(STORAGE_CART, items);
  }

  function toNumber(v) {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }

  function normalizeCategory(value) {
    return String(value || '')
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .toLowerCase()
      .trim();
  }

  function displayProductCode(rawSku) {
    return String(rawSku || '').replace(/^XLS-/i, '');
  }

  function decodeHtmlEntities(value) {
    let result = String(value || '');
    if (!result) return '';

    const textarea = document.createElement('textarea');
    for (let i = 0; i < 3; i += 1) {
      textarea.innerHTML = result;
      const decoded = textarea.value;
      if (decoded === result) break;
      result = decoded;
    }

    return result;
  }

  function money(v) {
    return new Intl.NumberFormat('es-MX', {
      style: 'currency',
      currency: 'MXN',
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    }).format(toNumber(v));
  }

  function updateCartBadge() {
    const cart = getCart();
    const count = cart.reduce((sum, i) => sum + toNumber(i.quantity), 0);
    
    // Update all standalone badge elements and counters
    const badges = document.querySelectorAll('#cartCount, .cart-count-badge, .badge-cart, #drawerCartCount');
    badges.forEach(badge => {
      badge.textContent = String(count);
      if (badge.classList.contains('badge-cart') || badge.classList.contains('cart-count-badge')) {
        badge.style.display = count > 0 ? 'inline-flex' : 'none';
      } else {
        badge.style.display = 'inline';
      }
      badge.classList.remove('pulse-badge');
      void badge.offsetWidth;
      badge.classList.add('pulse-badge');
    });

    const openCartBtn = document.getElementById('openCart');
    if (openCartBtn) {
      const countSpan = openCartBtn.querySelector('#cartCount');
      if (countSpan) {
        countSpan.textContent = String(count);
        countSpan.style.display = 'inline';
      } else {
        openCartBtn.textContent = `Carrito (${count})`;
      }
    }
  }

  function notifyProductAdded(prodName) {
    if (window.TruperToast && typeof window.TruperToast.success === 'function') {
      window.TruperToast.success('Producto Agregado', `"${prodName}" se añadió al carrito.`);
      return;
    }
    if (typeof window.showToast === 'function') {
      window.showToast('success', 'Producto Agregado', `"${prodName}" se añadió al carrito.`);
      return;
    }
    
    // Self-contained fallback toast notification
    let container = document.getElementById('truper-toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'truper-toast-container';
      container.className = 'toast-container';
      container.style.cssText = 'position:fixed; top:24px; right:24px; z-index:99999; display:flex; flex-direction:column; gap:12px; pointer-events:none; max-width:400px; width:calc(100% - 48px);';
      document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.style.cssText = 'background:rgba(26,26,26,0.95); backdrop-filter:blur(10px); -webkit-backdrop-filter:blur(10px); border-radius:10px; padding:16px 20px; box-shadow:0 10px 25px rgba(0,0,0,0.4); color:#fff; font-family:sans-serif; font-size:14px; display:flex; align-items:center; gap:14px; pointer-events:auto; border-left:4px solid #00c853; transition:all 0.35s ease; opacity:1;';
    toast.innerHTML = `<div style="flex-grow:1;"><strong style="color:#00e676; display:block; margin-bottom:2px; font-size:14px;">Producto Agregado</strong><span style="color:#bbb; font-size:13px;">"${prodName}" se añadió al carrito.</span></div>`;
    container.appendChild(toast);
    setTimeout(() => {
      toast.style.opacity = '0';
      setTimeout(() => toast.remove(), 350);
    }, 3500);
  }

  async function addToCart(product) {
    const cart = getCart();
    const existing = cart.find((p) => p.sku === product.sku);
    
    // Actualizar localStorage primero para respuesta inmediata
    if (existing) {
      existing.quantity = toNumber(existing.quantity) + 1;
    } else {
      cart.push({
        id: product.id,
        sku: product.sku,
        name: product.name,
        image_url: product.image_url || 'images/products/default-product.svg',
        unit_price: toNumber(product.unit_price || product.price || 0),
        quantity: 1,
        product_type: 'catalog'
      });
    }
    setCart(cart);
    updateCartBadge();
    renderCart();

    // Sincronizar con servidor
    try {
      const response = await fetch('/api/cart.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_id: product.id,
          product_type: 'catalog',
          quantity: 1,
          price: toNumber(product.unit_price || product.price || 0),
          csrf_token: (document.cookie.match(/csrf_token=([^;]+)/) || [])[1] || ''
        })
      });
      const result = await response.json();
      
      if (!result.success) {
        console.error('Error al sincronizar con servidor:', result.message);
        // Revertir si falló
        const revertedCart = getCart();
        const revertedItem = revertedCart.find((p) => p.sku === product.sku);
        if (revertedItem) {
          revertedItem.quantity = Math.max(1, revertedItem.quantity - 1);
          if (revertedItem.quantity === 0) {
            const idx = revertedCart.findIndex((p) => p.sku === product.sku);
            if (idx > -1) revertedCart.splice(idx, 1);
          }
        }
        setCart(revertedCart);
        updateCartBadge();
        renderCart();
        
        if (window.TruperToast && typeof window.TruperToast.error === 'function') {
          window.TruperToast.error('Error', result.message);
        }
        return;
      }
    } catch (e) {
      console.error('Error de red al agregar al carrito:', e);
    }

    const prodName = product.name || 'Producto';
    notifyProductAdded(prodName);
  }

  async function removeFromCart(sku) {
    const cart = getCart();
    const item = cart.find((i) => i.sku === sku);
    
    // Actualizar localStorage primero
    const next = cart.filter((i) => i.sku !== sku);
    setCart(next);
    updateCartBadge();
    renderCart();

    // Sincronizar con servidor (si tenemos cart_id)
    if (item && item.cart_id) {
      try {
        await fetch('/api/cart.php?action=remove', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cart_id: item.cart_id, csrf_token: (document.cookie.match(/csrf_token=([^;]+)/) || [])[1] || '' })
        });
      } catch (e) {
        console.error('Error al eliminar del servidor:', e);
      }
    }
  }

  async function changeQty(sku, delta) {
    const cart = getCart();
    const item = cart.find((i) => i.sku === sku);
    if (!item) return;
    
    const newQty = Math.max(1, toNumber(item.quantity) + delta);
    item.quantity = newQty;
    
    // Actualizar localStorage primero
    setCart(cart);
    updateCartBadge();
    renderCart();

    // Sincronizar con servidor (si tenemos cart_id)
    if (item.cart_id) {
      try {
        const response = await fetch('/api/cart.php?action=update', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ cart_id: item.cart_id, quantity: newQty, csrf_token: (document.cookie.match(/csrf_token=([^;]+)/) || [])[1] || '' })
        });
        const result = await response.json();
        
        if (!result.success) {
          console.error('Error al actualizar cantidad:', result.message);
        }
      } catch (e) {
        console.error('Error de red al actualizar cantidad:', e);
      }
    }
  }

  // Cargar carrito desde el servidor al iniciar
  async function loadCartFromServer() {
    try {
      const response = await fetch('/api/cart.php?action=get');
      const result = await response.json();
      
      if (result.success && result.data) {
        const data = result.data;
        const items = data.items || [];
        
        const serverCart = items.map(item => ({
          id: item.product_id,
          sku: item.sku,
          name: item.name,
          image_url: item.image_url || 'images/products/default-product.svg',
          unit_price: toNumber(item.price || item.current_price || 0),
          original_price: toNumber(item.original_price || 0),
          discount_percentage: toNumber(item.discount_percentage || 0),
          quantity: toNumber(item.quantity || 1),
          cart_id: item.id,
          reservation_id: item.reservation_id,
          expires_at: item.expires_at,
          available_stock: toNumber(item.available_stock || 0),
          low_stock_threshold: toNumber(item.low_stock_threshold || 5),
          product_type: item.product_type || 'catalog'
        }));
        
        // Si el localStorage está vacío, usar el del servidor
        const localCart = getCart();
        if (localCart.length === 0 && serverCart.length > 0) {
          setCart(serverCart);
          updateCartBadge();
          renderCart();
          
          // Mostrar notificación de stock bajo si hay productos
          if (data.low_stock_count > 0) {
            showLowStockNotification(data.low_stock_items);
          }
          
          // Iniciar temporizador de reserva
          startReservationTimer(serverCart);
        }
      }
    } catch (e) {
      console.error('Error al cargar carrito del servidor:', e);
    }
  }

  function showLowStockNotification(lowStockItems) {
    if (!lowStockItems || lowStockItems.length === 0) return;
    
    const message = lowStockItems.length === 1
      ? `⚠️ "${lowStockItems[0].name}" tiene poco stock disponible (${lowStockItems[0].available_stock} unidades)`
      : `⚠️ ${lowStockItems.length} productos tienen poco stock disponible`;
    
    if (window.TruperToast && typeof window.TruperToast.warning === 'function') {
      window.TruperToast.warning('Stock Bajo', message);
    } else if (typeof window.showToast === 'function') {
      window.showToast('warning', 'Stock Bajo', message);
    }
  }

  let reservationTimerInterval = null;

  function startReservationTimer(cart) {
    if (reservationTimerInterval) {
      clearInterval(reservationTimerInterval);
    }

    // Encontrar la expiración más temprana
    const earliestExpiry = cart
      .filter(item => item.expires_at)
      .map(item => new Date(item.expires_at))
      .sort((a, b) => a - b)[0];

    if (!earliestExpiry) return;

    const timerEl = document.getElementById('reservationTimer');
    const bannerEl = document.getElementById('reservationBanner');
    
    if (!timerEl || !bannerEl) return;

    bannerEl.style.display = 'flex';

    reservationTimerInterval = setInterval(() => {
      const now = new Date();
      const diff = earliestExpiry - now;

      if (diff <= 0) {
        timerEl.textContent = '00:00 (Expirado)';
        timerEl.style.color = '#ef4444';
        clearInterval(reservationTimerInterval);
        reservationTimerInterval = null;
        return;
      }

      const minutes = Math.floor(diff / 60000);
      const seconds = Math.floor((diff % 60000) / 1000);
      timerEl.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
    }, 1000);
  }

  function renderCart() {
    if (window.location.pathname.endsWith('cart.php')) return;
    const cart = getCart();
    const list = document.getElementById('drawerCartList') || document.getElementById('cartList');
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
          <div class="text-muted">${displayProductCode(item.sku)}</div>
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

  function getTicketMeta() {
    const body = document.body;
    return {
      clientCode: (body?.dataset?.clientCode || 'PUBLICO').trim()
    };
  }

  function createTicketFolio(dateObj) {
    const stamp = String(dateObj.getTime());
    return `TCK-${stamp.slice(-8)}`;
  }

  function formatProductCodeForTicket(rawSku) {
    const sku = String(rawSku || '').trim();
    return sku.replace(/^XLS-/i, '');
  }

  async function drawTicketPdf(format, overrideFolio = null) {
    const cart = getCart();
    if (cart.length === 0) {
      if (window.showAlert) window.showAlert('No hay productos en el carrito', 'warning');
      return;
    }

    if (!window.jspdf || !window.jspdf.jsPDF) {
      if (window.showAlert) window.showAlert('No se pudo generar el PDF en este momento', 'error');
      return;
    }

    const now = new Date();
    const folio = overrideFolio || createTicketFolio(now);
    const date = now.toLocaleString('es-MX');
    const meta = getTicketMeta();
    const total = cart.reduce((sum, item) => sum + toNumber(item.unit_price) * toNumber(item.quantity), 0);

    // Fetch the logo and convert it to Base64
    let logoBase64 = '';
    try {
      const response = await fetch('/truper_logo2.png');
      if (response.ok) {
        const blob = await response.blob();
        logoBase64 = await new Promise((resolve) => {
          const reader = new FileReader();
          reader.onloadend = () => resolve(reader.result);
          reader.readAsDataURL(blob);
        });
      }
    } catch (e) {
      console.warn('Could not load logo for ticket PDF:', e);
    }

    // Dynamic height calculation
    let dynamicHeight = 75; // Base height for header, meta, totals, margins
    cart.forEach(item => {
      const nameLength = String(item.name || '').length;
      const nameLines = Math.ceil(nameLength / 32);
      dynamicHeight += (nameLines * 4.5) + 8;
    });
    dynamicHeight = Math.max(120, Math.round(dynamicHeight));

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
      unit: 'mm',
      format: [80, dynamicHeight]
    });

    let y = 6;

    // Logo on top-left
    if (logoBase64) {
      doc.addImage(logoBase64, 'JPEG', 6, y, 12, 14);
    }

    // Header text beside logo
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(33, 37, 41);
    doc.text('FERRETERÍA FOX', 20, y + 4);
    
    doc.setFontSize(9);
    doc.text('TICKET DE COMPRA', 20, y + 8);
    
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(7.5);
    doc.setTextColor(100, 116, 139);
    doc.text('Folio: ' + folio, 20, y + 12);
    
    y += 16;
    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 5;

    // Meta info
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(71, 85, 105);
    doc.text('Fecha: ' + date, 6, y);
    y += 4.5;
    doc.text('Código cliente: ' + meta.clientCode, 6, y);
    y += 4.5;
    
    doc.line(6, y, 74, y);
    y += 5;

    // Title
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.setTextColor(33, 37, 41);
    doc.text('Detalle de productos', 6, y);
    y += 5;
    
    // Items
    doc.setFont('helvetica', 'normal');
    doc.setTextColor(71, 85, 105);

    cart.forEach((item, idx) => {
      const qty = Number(item.quantity || 0);
      const name = String(item.name || 'Producto');
      const code = String(item.sku || 'N/A');
      const unitPrice = Number(item.unit_price || 0);
      const lineTotal = qty * unitPrice;

      // Handle name wrapping properly
      doc.setFont('helvetica', 'bold');
      doc.setFontSize(8.5);
      doc.setTextColor(33, 37, 41);
      
      const splitName = doc.splitTextToSize(name, 68);
      splitName.forEach(line => {
        doc.text(line, 6, y);
        y += 4;
      });
      
      doc.setFont('helvetica', 'normal');
      doc.setFontSize(8);
      doc.setTextColor(100, 116, 139);
      doc.text('Código: ' + formatProductCodeForTicket(code), 6, y);
      y += 4;
      
      doc.setFontSize(8.5);
      doc.setTextColor(71, 85, 105);
      doc.text(qty + ' x ' + money(unitPrice), 6, y);
      doc.text(money(lineTotal), 74, y, { align: 'right' });
      y += 5;

      if (idx < (cart.length - 1)) {
        doc.setDrawColor(241, 245, 249);
        doc.setLineWidth(0.2);
        doc.line(6, y - 1, 74, y - 1);
        y += 2;
      }
    });

    doc.setDrawColor(203, 213, 225);
    doc.setLineWidth(0.3);
    doc.line(6, y, 74, y);
    y += 6;

    // Totals
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(11);
    doc.setTextColor(255, 102, 0);
    doc.text('TOTAL: ' + money(total), 74, y, { align: 'right' });
    y += 7;
    
    // Footer
    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8.5);
    doc.setTextColor(148, 163, 184);
    doc.text('Gracias por su compra', 40, y, { align: 'center' });

    doc.save(`ticket-${folio}.pdf`);

    if (window.showAlert) {
      window.showAlert('Ticket PDF generado correctamente', 'success');
    }
  }

  function escapeHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getCategoryColorStyleJS(catName) {
    const map = window.categoryColorMap || {};
    const key = String(catName || '').trim().toLowerCase()
      .replace(/[áä]/g, 'a')
      .replace(/[éë]/g, 'e')
      .replace(/[íï]/g, 'i')
      .replace(/[óö]/g, 'o')
      .replace(/[úü]/g, 'u')
      .replace(/ñ/g, 'n');

    if (map[key]) {
      return map[key];
    }

    const defaultMap = {
      'material electrico': { bg: 'rgba(14, 165, 233, 0.18)', border: '#0ea5e9', color: '#38bdf8', dot: '#0ea5e9' },
      'fontaneria': { bg: 'rgba(20, 184, 166, 0.18)', border: '#14b8a6', color: '#2dd4bf', dot: '#14b8a6' },
      'cerrajeria': { bg: 'rgba(245, 158, 11, 0.18)', border: '#f59e0b', color: '#fbbf24', dot: '#f59e0b' },
      'herreria': { bg: 'rgba(239, 68, 68, 0.18)', border: '#ef4444', color: '#f87171', dot: '#ef4444' },
      'herramientas': { bg: 'rgba(168, 85, 247, 0.18)', border: '#a855f7', color: '#c084fc', dot: '#a855f7' },
      'pintura': { bg: 'rgba(16, 185, 129, 0.18)', border: '#10b981', color: '#34d399', dot: '#10b981' },
      'automotriz': { bg: 'rgba(236, 72, 153, 0.18)', border: '#ec4899', color: '#f472b6', dot: '#ec4899' },
      'jardineria': { bg: 'rgba(132, 204, 22, 0.18)', border: '#84cc16', color: '#a3e635', dot: '#84cc16' },
      'fijacion': { bg: 'rgba(99, 102, 241, 0.18)', border: '#6366f1', color: '#818cf8', dot: '#6366f1' },
      'medicion': { bg: 'rgba(251, 146, 60, 0.18)', border: '#fb923c', color: '#fdba74', dot: '#fb923c' },
      'marketplace ce': { bg: 'rgba(255, 102, 0, 0.2)', border: '#ff6600', color: '#ff9933', dot: '#ff6600' }
    };

    if (defaultMap[key]) {
      return defaultMap[key];
    }

    let hash = 0;
    for (let i = 0; i < key.length; i++) {
      hash = key.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash) % 360;
    return {
      bg: `hsla(${hue}, 80%, 60%, 0.18)`,
      border: `hsl(${hue}, 80%, 55%)`,
      color: `hsl(${hue}, 80%, 55%)`,
      dot: `hsl(${hue}, 80%, 55%)`
    };
  }

  function renderProductCard(p) {
    const catStyle = getCategoryColorStyleJS(p.cat);
    const skuEscaped = escapeHtml(p.sku);
    const nameEscaped = escapeHtml(p.name);
    const catEscaped = escapeHtml(p.cat);
    const descEscaped = escapeHtml(p.desc || 'Descripción pendiente');
    const firstImg = p.imgs[0] || 'images/products/default-product.svg';

    let imgsHtml = '';
    p.imgs.forEach((img, idx) => {
      imgsHtml += `
        <img
          class="product-gallery-image ${idx === 0 ? 'active' : ''}"
          src="${escapeHtml(img)}"
          alt="${nameEscaped}"
          loading="lazy"
          decoding="async"
          fetchpriority="${idx === 0 ? 'high' : 'low'}"
          width="300"
          height="300">
      `;
    });

    let navHtml = '';
    if (p.imgs.length > 1) {
      navHtml = `
        <button type="button" class="gallery-nav gallery-prev" data-gallery-prev aria-label="Imagen anterior">&#10094;</button>
        <button type="button" class="gallery-nav gallery-next" data-gallery-next aria-label="Imagen siguiente">&#10095;</button>
        <div class="gallery-counter"><span data-gallery-current>1</span>/${p.imgs.length}</div>
      `;
    }

    let variantsHtml = '';
    if (p.variants && p.variants.length > 0) {
      p.variants.forEach(variant => {
        variantsHtml += `<span class="variant-pill">${escapeHtml(variant)}</span>`;
      });
    } else {
      variantsHtml = `<span class="variant-pill">Modelo Estandar</span>`;
    }

    const stockClass = p.stock <= 10 ? 'stock-low' : 'stock-ok';
    const stockPrefix = p.stock <= 10 ? 'Stock bajo: ' : 'Stock: ';

    let priceHtml = '';
    if (p.discount > 0) {
      priceHtml = `
        <div class="catalog-price-container" style="display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px;">
          <div class="original-price-wrap" style="display: flex; align-items: center; gap: 8px;">
            <span class="price-base" style="text-decoration: line-through; color: rgba(255, 255, 255, 0.4); font-size: 0.85rem;">
              ${money(p.net_price)}
            </span>
            <span class="discount-badge" style="background: rgba(255, 102, 0, 0.15); color: var(--color-naranja, #ff6600); font-size: 0.75rem; font-weight: bold; padding: 2px 6px; border-radius: 4px;">
              ${p.discount}% OFF
            </span>
          </div>
          <div class="catalog-price" style="color: #fff; font-weight: 700; font-size: 1.2rem; padding: 0;">
            ${money(p.price)}
          </div>
        </div>
      `;
    } else {
      priceHtml = `<div class="catalog-price">${money(p.price)}</div>`;
    }

    return `
      <article class="product-card-min"
        data-product-card
        data-name="${escapeHtml(p.name.toLowerCase())}"
        data-sku="${escapeHtml(p.sku.toLowerCase())}"
        data-category="${catEscaped}"
        data-price="${p.price}"
        data-stock="${p.stock}">
        <div class="product-media" data-product-gallery>
          <a href="product_detail.php?id=${p.id}${isOnlineMode ? '&mode=online' : ''}" class="product-media-link" aria-label="Ver detalle de ${nameEscaped}"></a>
          ${imgsHtml}
          ${navHtml}
        </div>
        <div class="product-content">
          <div class="catalog-tag category-colored-tag" style="background:${catStyle.bg}; color:${catStyle.color}; border:1px solid ${catStyle.border};"><span class="cat-dot" style="background:${catStyle.dot};"></span>${catEscaped}</div>
          ${(() => {
            if (!p.group || !p.group.trim()) return '';
            const grpTrim = p.group.trim();
            const grpEscaped = escapeHtml(grpTrim);
            const grpColor = (productGroupsColorsMap && productGroupsColorsMap[grpTrim.toLowerCase()]) || '#FF7F00';
            return `<div class="catalog-tag group-colored-tag" style="background:rgba(0,0,0,0.5); color:#ffffff; border:1px solid ${grpColor}; font-weight:600; margin-top:3px;"><span class="cat-dot" style="background:${grpColor}; box-shadow:0 0 6px ${grpColor};"></span>${grpEscaped}</div>`;
          })()}
          <div class="product-code-label"><strong>Código:</strong> <strong>${skuEscaped}</strong></div>
          <h3 class="product-title">${nameEscaped}</h3>
          <p class="product-spec">${descEscaped}</p>
          <div>
            ${variantsHtml}
          </div>
          <span class="stock-badge ${stockClass}">
            ${stockPrefix}${p.stock}
          </span>
          ${priceHtml}
          <div class="product-actions">
            <button
              type="button"
              class="btn btn-primary btn-small"
              data-add-product
              data-id="${p.id}"
              data-sku="${skuEscaped}"
              data-name="${nameEscaped}"
              data-image="${escapeHtml(firstImg)}"
              data-price="${p.price}">Agregar</button>
          </div>
        </div>
      </article>
    `;
  }

  const grid = document.querySelector('.catalog-grid-min');
  const emptyState = document.getElementById('catalogEmptyState');
  const loadingSpinner = document.getElementById('catalogLoading');

  function renderList() {
    if (!grid) return;

    const limit = currentPage * itemsPerPage;
    const itemsToRender = filteredProducts.slice(0, limit);

    if (itemsToRender.length === 0) {
      grid.innerHTML = '';
      if (emptyState) emptyState.style.display = 'block';
      if (loadingSpinner) loadingSpinner.style.display = 'none';
      return;
    }

    if (emptyState) emptyState.style.display = 'none';

    grid.innerHTML = itemsToRender.map(renderProductCard).join('');

    if (loadingSpinner) {
      if (limit < filteredProducts.length) {
        loadingSpinner.style.display = 'flex';
      } else {
        loadingSpinner.style.display = 'none';
      }
    }
  }

  function setupInfiniteScroll() {
    const sentinel = document.getElementById('catalogSentinel');
    if (!sentinel) return;

    const observer = new IntersectionObserver((entries) => {
      const entry = entries[0];
      if (entry.isIntersecting && filteredProducts.length > currentPage * itemsPerPage) {
        currentPage++;
        renderList();
      }
    }, {
      rootMargin: '200px'
    });

    observer.observe(sentinel);
  }

  function setupEventDelegation() {
    document.addEventListener('click', (e) => {
      const addBtn = e.target.closest('[data-add-product]');
      if (addBtn) {
        e.preventDefault();
        e.stopPropagation();
        const product = {
          id: addBtn.dataset.id,
          sku: addBtn.dataset.sku,
          name: decodeHtmlEntities(addBtn.dataset.name),
          image_url: addBtn.dataset.image || 'images/products/default-product.svg',
          unit_price: toNumber(addBtn.dataset.price)
        };
        addToCart(product);
      }
    });

    if (!grid) return;

    grid.addEventListener('click', (e) => {
      // 2. Navegación de galería
      const prevBtn = e.target.closest('[data-gallery-prev]');
      const nextBtn = e.target.closest('[data-gallery-next]');
      if (prevBtn || nextBtn) {
        e.preventDefault();
        e.stopPropagation();
        const gallery = e.target.closest('[data-product-gallery]');
        if (!gallery) return;
        const images = Array.from(gallery.querySelectorAll('.product-gallery-image'));
        if (images.length <= 1) return;

        let currentIndex = images.findIndex((img) => img.classList.contains('active'));
        if (currentIndex === -1) currentIndex = 0;

        const delta = prevBtn ? -1 : 1;
        currentIndex = (currentIndex + delta + images.length) % images.length;

        images.forEach((img, idx) => {
          img.classList.toggle('active', idx === currentIndex);
        });

        const currentEl = gallery.querySelector('[data-gallery-current]');
        if (currentEl) currentEl.textContent = String(currentIndex + 1);
      }
    });
  }

  const COLOR_NAME_ALIASES = {
    '#EF4444': 'rojo red herrería herreria',
    '#0EA5E9': 'azul blue eléctrico electrico material',
    '#14B8A6': 'verde teal fontanería fontaneria',
    '#F59E0B': 'dorado amarillo cerrajería cerrajeria',
    '#A855F7': 'morado púrpura purpura herramientas',
    '#10B981': 'esmeralda verde pintura',
    '#EC4899': 'rosa magenta automotriz',
    '#84CC16': 'lima verde jardinería jardineria',
    '#6366F1': 'índigo indigo fijación fijacion',
    '#FB923C': 'naranja medición medicion',
    '#FF6600': 'naranja truper marketplace'
  };

  function applyFilters() {
    const query = (document.getElementById('catalogSearch')?.value || '').toLowerCase().trim();
    const category = selectedQuickCategory || '';
    const groupFilter = (document.getElementById('filterGroup')?.value || '').toLowerCase().trim();
    const stockMode = document.getElementById('filterStock')?.value || '';
    const sortMode = document.getElementById('filterSort')?.value || 'name_asc';

    const searchTokens = query.toLowerCase().split(/\s+/).filter(Boolean);

    filteredProducts = allProducts.filter(p => {
      if (searchTokens.length > 0) {
        const catStyle = getCategoryColorStyleJS(p.cat, p.color || null);
        const hex = (catStyle.border || '').toUpperCase();
        const colorAlias = COLOR_NAME_ALIASES[hex] || '';
        const textToSearch = `${p.name.toLowerCase()} ${p.sku.toLowerCase()} ${p.cat.toLowerCase()} ${(p.group || '').toLowerCase()} ${(p.color || '').toLowerCase()} ${hex.toLowerCase()} ${colorAlias.toLowerCase()}`;
        const matchAll = searchTokens.every(token => textToSearch.includes(token));
        if (!matchAll) return false;
      }
      if (category) {
        const categoryTokens = p.cat.split(/\s*,\s*/).map(normalizeCategory);
        const normSelected = normalizeCategory(category);
        if (!categoryTokens.includes(normSelected)) return false;
      }
      const activeGroup = (selectedColorFilter !== '' ? selectedColorFilter : (document.getElementById('filterGroup')?.value || '')).trim();
      if (activeGroup) {
        const prodGroup = (p.group || '').trim();
        if (activeGroup.toUpperCase() === '__NONE__') {
          if (prodGroup !== '') return false;
        } else {
          if (prodGroup.toLowerCase() !== activeGroup.toLowerCase()) return false;
        }
      }
      if (stockMode) {
        if (stockMode === 'available' && p.stock <= 0) return false;
        if (stockMode === 'low' && p.stock > 10) return false;
      }
      return true;
    });

    filteredProducts.sort((a, b) => {
      switch (sortMode) {
        case 'name_asc':
          return a.name.localeCompare(b.name, 'es');
        case 'name_desc':
          return b.name.localeCompare(a.name, 'es');
        case 'price_asc':
          return a.price - b.price;
        case 'price_desc':
          return b.price - a.price;
        case 'stock_desc':
          return b.stock - a.stock;
        default:
          return 0;
      }
    });

    currentPage = 1;
    renderList();
  }

  function setupAutocomplete() {
    const searchInput = document.getElementById('catalogSearch');
    if (!searchInput) return;

    let autocompleteContainer = null;
    let debounceTimer = null;

    // Crear contenedor de autocompletado
    function createAutocompleteContainer() {
      autocompleteContainer = document.createElement('div');
      autocompleteContainer.className = 'autocomplete-suggestions';
      autocompleteContainer.style.cssText = `
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-top: 4px;
        max-height: 300px;
        overflow-y: auto;
        z-index: 1000;
        display: none;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
      `;
      searchInput.parentElement.style.position = 'relative';
      searchInput.parentElement.appendChild(autocompleteContainer);
    }

    // Mostrar sugerencias
    function showSuggestions(suggestions) {
      if (!autocompleteContainer) createAutocompleteContainer();
      
      if (suggestions.length === 0) {
        autocompleteContainer.style.display = 'none';
        return;
      }

      autocompleteContainer.innerHTML = suggestions.map(s => `
        <div class="autocomplete-item" data-sku="${s.sku}" style="
          padding: 12px 16px;
          cursor: pointer;
          border-bottom: 1px solid var(--border);
          display: flex;
          justify-content: space-between;
          align-items: center;
        ">
          <div>
            <div style="font-weight: 500; color: var(--text-primary);">${s.name}</div>
            <div style="font-size: 0.85rem; color: var(--text-secondary);">${s.sku} - ${s.category || ''}</div>
          </div>
          <div style="font-weight: 600; color: var(--accent);">${money(s.price)}</div>
        </div>
      `).join('');

      autocompleteContainer.querySelectorAll('.autocomplete-item').forEach(item => {
        item.addEventListener('click', () => {
          searchInput.value = item.dataset.sku;
          autocompleteContainer.style.display = 'none';
          applyFilters();
        });
      });

      autocompleteContainer.style.display = 'block';
    }

    // Buscar sugerencias
    function searchSuggestions(query) {
      if (query.length < 2) {
        if (autocompleteContainer) autocompleteContainer.style.display = 'none';
        return;
      }

      fetch(`/api/search_autocomplete.php?q=${encodeURIComponent(query)}&limit=8`)
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            showSuggestions(data.suggestions);
          }
        })
        .catch(err => {
          console.error('Autocomplete error:', err);
        });
    }

    // Event listeners
    searchInput.addEventListener('input', (e) => {
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(() => {
        searchSuggestions(e.target.value);
      }, 300);
    });

    searchInput.addEventListener('blur', () => {
      setTimeout(() => {
        if (autocompleteContainer) autocompleteContainer.style.display = 'none';
      }, 200);
    });

    searchInput.addEventListener('focus', () => {
      if (searchInput.value.length >= 2) {
        searchSuggestions(searchInput.value);
      }
    });

    // Cerrar al presionar Escape
    searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && autocompleteContainer) {
        autocompleteContainer.style.display = 'none';
      }
    });
  }

  function setupHandlers() {

    const filterIds = ['catalogSearch', 'filterStock', 'filterSort'];
    filterIds.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', applyFilters);
      if (el && el.tagName === 'SELECT') el.addEventListener('change', applyFilters);
    });

    function syncGroupUI(val) {
      const targetVal = (val || '').trim();
      const groupSelect = document.getElementById('filterGroup');
      if (groupSelect) {
        let matchedOptionVal = targetVal;
        for (let i = 0; i < groupSelect.options.length; i++) {
          if (groupSelect.options[i].value.trim().toLowerCase() === targetVal.toLowerCase()) {
            matchedOptionVal = groupSelect.options[i].value;
            break;
          }
        }
        if (groupSelect.value !== matchedOptionVal) {
          groupSelect.value = matchedOptionVal;
        }
      }
      document.querySelectorAll('.color-chip-filter').forEach((btn) => {
        const btnVal = (btn.dataset.colorFilter || '').trim();
        btn.classList.toggle('active', btnVal.toLowerCase() === targetVal.toLowerCase());
      });
    }

    const groupSelectEl = document.getElementById('filterGroup');
    if (groupSelectEl) {
      groupSelectEl.addEventListener('change', () => {
        selectedColorFilter = groupSelectEl.value;
        syncGroupUI(selectedColorFilter);
        applyFilters();
      });
    }

    const colorFilterButtons = document.querySelectorAll('.color-chip-filter');
    if (colorFilterButtons.length > 0) {
      colorFilterButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          selectedColorFilter = btn.dataset.colorFilter || '';
          syncGroupUI(selectedColorFilter);
          applyFilters();
        });
      });
    }

    const quickCategoryButtons = document.querySelectorAll('[data-quick-category]');
    if (quickCategoryButtons.length > 0) {
      quickCategoryButtons.forEach((btn) => {
        btn.addEventListener('click', () => {
          selectedQuickCategory = btn.dataset.quickCategory || '';

          quickCategoryButtons.forEach((x) => x.classList.remove('active'));
          btn.classList.add('active');
          applyFilters();
        });
      });
    }

    const openBtn = document.getElementById('openCart');
    const closeBtn = document.getElementById('closeCart');
    const drawer = document.getElementById('cartDrawer');
    if (openBtn && drawer) openBtn.addEventListener('click', () => drawer.classList.add('open'));
    if (closeBtn && drawer) {
      closeBtn.addEventListener('click', () => {
        drawer.classList.remove('open');
      });
    }

    window.registerQuoteTicket = function(items, total) {
      return fetch('api/create_quote_ticket.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-Token': window.csrfToken || ''
        },
        body: JSON.stringify({
          items: items.map(item => ({
            id: item.id,
            sku: item.sku,
            name: item.name,
            unit_price: item.unit_price,
            quantity: item.quantity
          })),
          total: total
        })
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Error al registrar la cotización en el servidor');
        }
        return response.json();
      })
      .then(result => {
        if (!result.success || !result.folio) {
          throw new Error(result.message || 'Error en la respuesta del servidor');
        }
        return result;
      });
    };

    window.shareQuoteViaWhatsApp = function(companyWhatsApp, clientCode, typeLabel = 'COTIZACION') {
      const items = getCart();
      if (items.length === 0) {
        showAlert('El carrito está vacío', 'warning');
        return;
      }

      const shareBtn = document.getElementById('shareWhatsApp');
      let originalText = '';
      if (shareBtn) {
        originalText = shareBtn.innerHTML;
        shareBtn.disabled = true;
        shareBtn.innerHTML = '⌛ Procesando...';
      }

      const total = items.reduce((sum, item) => sum + (toNumber(item.unit_price || 0) * toNumber(item.quantity || 0)), 0);

      window.registerQuoteTicket(items, total)
        .then(result => {
          const officialFolio = result.folio;
          drawTicketPdf('thermal', officialFolio);

          const now = new Date();
          const issueDate = now.toLocaleString('es-MX');
          const ticketUrl = `${window.location.origin}/ticket_quote.php?folio=${encodeURIComponent(officialFolio)}&auto_pdf=1`;

          let message = `TRUPER - ${typeLabel.toUpperCase()}\n`;
          message += '===========================\n';
          message += `Folio: ${officialFolio}\n`;
          message += `Fecha: ${issueDate}\n`;
          message += `Cliente: ${clientCode || 'PUBLICO'}\n`;
          message += '---------------------------\n';
          message += 'PRODUCTOS:\n';
          items.forEach((item, idx) => {
            const code = String(item.sku || '').replace(/^XLS-/i, '') || 'N/A';
          const nowStr = new Date().toLocaleString('es-MX', { hour12: false });
          let body = `🧾 *COTIZACIÓN OFICIAL TRUPER / FOX VIRTUAL*\n`;
          body += `📌 *Folio de Ticket:* ${officialFolio}\n`;
          body += `📅 *Fecha:* ${nowStr}\n`;
          body += `👤 *Cliente:* ${clientCode}\n`;
          body += `-----------------------------------\n\n`;

          items.forEach((item, index) => {
            const price = toNumber(item.unit_price || 0);
            const qty = toNumber(item.quantity || 0);
            const lineTotal = price * qty;
            const sku = item.sku || 'N/A';
            const name = item.name || 'Producto';
            body += `${index + 1}. *${name}*\n   SKU: ${sku} | Cant: ${qty} x $${price.toFixed(2)} = *$${lineTotal.toFixed(2)}*\n\n`;
          });

          body += `-----------------------------------\n`;
          body += `💰 *TOTAL GENERAL: $${total.toFixed(2)} MXN*\n\n`;
          body += `⚠️ *Nota:* Este folio de cotización es válido por 15 días en nuestra sucursal. Menciónalo al realizar tu pago o recolección.`;

          const encodedMsg = encodeURIComponent(body);
          const whatsappUrl = companyWhatsApp
            ? `https://wa.me/${companyWhatsApp}?text=${encodedMsg}`
            : `https://wa.me/?text=${encodedMsg}`;

          window.open(whatsappUrl, '_blank');
        })
        .catch(err => {
          console.error(err);
          showAlert('Error: ' + err.message, 'error');
        })
        .finally(() => {
          if (shareBtn) {
            shareBtn.disabled = false;
            shareBtn.innerHTML = originalText;
          }
        });
    };

    const ticketBtn = document.getElementById('printTicket');
    if (ticketBtn) {
      ticketBtn.addEventListener('click', () => {
        const items = getCart();
        if (items.length === 0) {
          showAlert('El carrito está vacío', 'warning');
          return;
        }

        const originalText = ticketBtn.innerHTML;
        ticketBtn.disabled = true;
        ticketBtn.innerHTML = '⌛ Guardando...';

        const total = items.reduce((sum, item) => sum + (toNumber(item.unit_price || 0) * toNumber(item.quantity || 0)), 0);

        window.registerQuoteTicket(items, total)
          .then(result => {
            const officialFolio = result.folio;
            drawTicketPdf('thermal', officialFolio);
          })
          .catch(err => {
            console.error(err);
            showAlert('Error: ' + err.message, 'error');
          })
          .finally(() => {
            ticketBtn.disabled = false;
            ticketBtn.innerHTML = originalText;
          });
      });
    }

    const shareBtn = document.getElementById('shareWhatsApp');
    if (shareBtn) {
      shareBtn.addEventListener('click', () => {
        const companyWhatsApp = shareBtn.dataset.companyWhatsapp || '';
        const clientCode = shareBtn.dataset.clientCode || 'PUBLICO';
        const typeLabel = shareBtn.dataset.typeLabel || 'COTIZACION';
        window.shareQuoteViaWhatsApp(companyWhatsApp, clientCode, typeLabel);
      });
    }

    const clearBtn = document.getElementById('clearCart');
    if (clearBtn) {
      clearBtn.addEventListener('click', () => {
        setCart([]);
        updateCartBadge();
        renderCart();
      });
    }

    applyFilters();
  }

  function initCatalog() {
    setupEventDelegation();
    setupInfiniteScroll();
    setupHandlers();
    setupAutocomplete();
    loadCartFromServer(); // Cargar carrito del servidor
    updateCartBadge();
    renderCart();
    setupAdvancedSearch(); // Búsqueda avanzada
    setupLanguageSelector(); // Selector de idioma
    setupWishlist(); // Wishlist
    setupProductComparison(); // Comparador de productos
  }

  // Búsqueda avanzada con autocomplete
  function setupAdvancedSearch() {
    const searchInput = document.getElementById('catalogSearch');
    const suggestionsBox = document.getElementById('searchSuggestions');
    
    if (!searchInput || !suggestionsBox) return;
    
    let debounceTimer;
    
    searchInput.addEventListener('input', (e) => {
      const query = e.target.value.trim();
      
      clearTimeout(debounceTimer);
      
      if (query.length < 2) {
        suggestionsBox.style.display = 'none';
        return;
      }
      
      debounceTimer = setTimeout(() => {
        fetch(`/api/search.php?action=autocomplete&q=${encodeURIComponent(query)}`)
          .then(response => response.json())
          .then(data => {
            if (data.success && data.results && data.results.length > 0) {
              suggestionsBox.innerHTML = data.results.map(product => `
                <div class="suggestion-item" data-product-id="${product.id}">
                  <img src="${product.image_url || '/img/no-image.png'}" alt="${product.name}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;">
                  <div>
                    <div style="font-weight:700;color:#fff;">${product.name}</div>
                    <div style="font-size:0.85rem;color:#888;">SKU: ${product.sku} | $${parseFloat(product.price).toFixed(2)}</div>
                  </div>
                </div>
              `).join('');
              suggestionsBox.style.display = 'block';
            } else {
              suggestionsBox.style.display = 'none';
            }
          })
          .catch(err => {
            console.error('Error en búsqueda:', err);
          });
      }, 300);
    });
    
    // Cerrar sugerencias al hacer clic fuera
    document.addEventListener('click', (e) => {
      if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
        suggestionsBox.style.display = 'none';
      }
    });
    
    // Seleccionar sugerencia
    suggestionsBox.addEventListener('click', (e) => {
      const item = e.target.closest('.suggestion-item');
      if (item) {
        const productId = item.dataset.productId;
        searchInput.value = item.querySelector('.font-weight-700').textContent;
        suggestionsBox.style.display = 'none';
        // Filtrar por el producto seleccionado
        filteredProducts = allProducts.filter(p => p.id == productId);
        renderProducts();
      }
    });
  }
  
  // Selector de idioma
  function setupLanguageSelector() {
    const selector = document.getElementById('languageSelector');
    if (!selector) return;
    
    selector.addEventListener('change', (e) => {
      const lang = e.target.value;
      // Guardar en sesión y recargar
      fetch('/api/auth.php?action=set_language', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ language: lang })
      }).then(() => {
        location.reload();
      });
    });
  }
  
  // Wishlist functionality
  function setupWishlist() {
    // Agregar botón de wishlist a cada producto
    document.querySelectorAll('[data-product-card]').forEach(card => {
      const productId = card.dataset.productId;
      if (!productId) return;
      
      const wishlistBtn = document.createElement('button');
      wishlistBtn.className = 'wishlist-btn';
      wishlistBtn.innerHTML = '❤️';
      wishlistBtn.style.cssText = 'position:absolute;top:10px;right:10px;background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:35px;height:35px;cursor:pointer;font-size:1.2rem;transition:all 0.2s;';
      
      // Verificar si ya está en wishlist
      checkWishlistItem(productId).then(isInWishlist => {
        if (isInWishlist) {
          wishlistBtn.style.background = '#ff7f00';
        }
      });
      
      wishlistBtn.addEventListener('click', () => {
        toggleWishlistItem(productId).then(result => {
          if (result.success) {
            wishlistBtn.style.background = result.in_wishlist ? '#ff7f00' : 'rgba(255,255,255,0.2)';
            showAlert(result.in_wishlist ? 'Agregado a favoritos' : 'Eliminado de favoritos', 'success');
          }
        });
      });
      
      card.style.position = 'relative';
      card.appendChild(wishlistBtn);
    });
  }
  
  function checkWishlistItem(productId) {
    return fetch('/api/wishlist.php?action=check&product_id=' + productId)
      .then(response => response.json())
      .then(data => data.in_wishlist || false)
      .catch(() => false);
  }
  
  function toggleWishlistItem(productId) {
    return fetch('/api/wishlist.php?action=toggle', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId })
    })
    .then(response => response.json())
    .catch(() => ({ success: false }));
  }
  
  // Comparador de productos
  function setupProductComparison() {
    let comparisonList = JSON.parse(localStorage.getItem('product_comparison') || '[]');
    
    // Agregar botón de comparar a cada producto
    document.querySelectorAll('[data-product-card]').forEach(card => {
      const productId = card.dataset.productId;
      if (!productId) return;
      
      const compareBtn = document.createElement('button');
      compareBtn.className = 'compare-btn';
      compareBtn.innerHTML = '⚖️';
      compareBtn.style.cssText = 'position:absolute;top:50px;right:10px;background:rgba(255,255,255,0.2);border:none;border-radius:50%;width:35px;height:35px;cursor:pointer;font-size:1.2rem;transition:all 0.2s;';
      
      if (comparisonList.includes(productId)) {
        compareBtn.style.background = '#22c55e';
      }
      
      compareBtn.addEventListener('click', () => {
        if (comparisonList.includes(productId)) {
          comparisonList = comparisonList.filter(id => id !== productId);
          compareBtn.style.background = 'rgba(255,255,255,0.2)';
        } else {
          if (comparisonList.length >= 4) {
            showAlert('Máximo 4 productos para comparar', 'warning');
            return;
          }
          comparisonList.push(productId);
          compareBtn.style.background = '#22c55e';
        }
        localStorage.setItem('product_comparison', JSON.stringify(comparisonList));
        updateCompareButton(comparisonList);
      });
      
      card.appendChild(compareBtn);
    });
    
    updateCompareButton(comparisonList);
  }
  
  function updateCompareButton(comparisonList) {
    let compareBtn = document.getElementById('compareProductsBtn');
    if (!compareBtn && comparisonList.length > 0) {
      compareBtn = document.createElement('button');
      compareBtn.id = 'compareProductsBtn';
      compareBtn.className = 'btn btn-primary';
      compareBtn.textContent = `Comparar (${comparisonList.length})`;
      compareBtn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:1000;';
      compareBtn.addEventListener('click', () => {
        window.location.href = '/product_compare.php?products=' + comparisonList.join(',');
      });
      document.body.appendChild(compareBtn);
    } else if (compareBtn) {
      if (comparisonList.length > 0) {
        compareBtn.textContent = `Comparar (${comparisonList.length})`;
        compareBtn.style.display = 'block';
      } else {
        compareBtn.style.display = 'none';
      }
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCatalog);
  } else {
    initCatalog();
  }
})();
