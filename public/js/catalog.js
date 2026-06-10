(function () {
  const STORAGE_CART = 'truper_cart';
  let selectedQuickCategory = '';

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
    const badge = document.getElementById('cartCount');
    if (badge) badge.textContent = String(count);
  }

  function addToCart(product) {
    const cart = getCart();
    const existing = cart.find((p) => p.sku === product.sku);
    if (existing) {
      existing.quantity += 1;
    } else {
      cart.push({ ...product, image_url: product.image_url || 'images/products/default-product.svg', quantity: 1 });
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

  function drawTicketPdf(format) {
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
    const folio = createTicketFolio(now);
    const date = now.toLocaleString('es-MX');
    const meta = getTicketMeta();
    const total = cart.reduce((sum, item) => sum + toNumber(item.unit_price) * toNumber(item.quantity), 0);

    const isA4 = false;
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({
      orientation: 'portrait',
      unit: 'mm',
      format: isA4 ? 'a4' : [210, 80]
    });

    let y = 12;
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(14);
    doc.text('TRUPER - TICKET', 10, y);
    y += 8;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(10);
    doc.text(`Codigo ticket: ${folio}`, 10, y);
    y += 5;
    doc.text(`Fecha: ${date}`, 10, y);
    y += 5;
    doc.text(`Codigo cliente: ${meta.clientCode}`, 10, y);
    y += 6;

    doc.setDrawColor(120);
    doc.line(10, y, isA4 ? 200 : 70, y);
    y += 5;

    doc.setFont('helvetica', 'bold');
    doc.text('Detalle de productos', 10, y);
    y += 5;
    doc.setFont('helvetica', 'normal');

    cart.forEach((item) => {
      if (y > 180) {
        doc.addPage();
        y = 12;
      }

      const lineTotal = toNumber(item.unit_price) * toNumber(item.quantity);
      const lineText = `${item.name} x${item.quantity}`;
      const wrapped = doc.splitTextToSize(lineText, isA4 ? 95 : 58);

      wrapped.forEach((line) => {
        doc.text(line, 10, y);
        y += 4;
      });

      doc.setFontSize(9);
      doc.text(`Codigo: ${formatProductCodeForTicket(item.sku || 'N/A')}`, 10, y);
      doc.text(`Precio: ${money(lineTotal)}`, 70, y, { align: 'right' });
      y += 4;
      doc.setDrawColor(200);
      doc.line(10, y, 70, y);
      y += 4;
      doc.setFontSize(10);
    });

    y += 2;
    doc.line(10, y, isA4 ? 200 : 70, y);
    y += 6;
    doc.setFont('helvetica', 'bold');
    doc.text(`Total: ${money(total)}`, isA4 ? 160 : 58, y, { align: 'right' });
    y += 6;
    doc.setFont('helvetica', 'normal');
    doc.text('Gracias por su compra', 10, y);

    doc.save(`ticket-${folio}.pdf`);

    if (window.showAlert) {
      window.showAlert('Ticket PDF generado correctamente', 'success');
    }
  }

  function applyFilters() {
    const query = (document.getElementById('catalogSearch')?.value || '').toLowerCase().trim();
    const category = selectedQuickCategory || '';
    const stockMode = document.getElementById('filterStock')?.value || '';
    const minPriceRaw = document.getElementById('filterMinPrice')?.value || '';
    const maxPriceRaw = document.getElementById('filterMaxPrice')?.value || '';
    const minPrice = minPriceRaw === '' ? null : toNumber(minPriceRaw);
    const maxPrice = maxPriceRaw === '' ? null : toNumber(maxPriceRaw);
    const sortMode = document.getElementById('filterSort')?.value || 'name_asc';

    let visibleCards = [];

    document.querySelectorAll('[data-product-card]').forEach((card) => {
      const name = (card.dataset.name || '').toLowerCase();
      const sku = (card.dataset.sku || '').toLowerCase();
      const cardCategory = card.dataset.category || '';
      const categoryTokens = String(cardCategory)
        .split(',')
        .map((x) => normalizeCategory(x))
        .filter(Boolean);
      const price = toNumber(card.dataset.price);
      const stock = toNumber(card.dataset.stock);

      const textMatch = `${name} ${sku} ${cardCategory.toLowerCase()}`.includes(query);
      const categoryMatch = !category || categoryTokens.includes(normalizeCategory(category));
      const priceMatch = (minPrice === null || price >= minPrice) && (maxPrice === null || price <= maxPrice);
      const stockMatch = !stockMode || (stockMode === 'available' ? stock > 0 : stock <= 10);

      const isVisible = textMatch && categoryMatch && priceMatch && stockMatch;
      
      if (isVisible) {
        visibleCards.push({ card, price, stock, name });
      }
      
      card.style.display = isVisible ? '' : 'none';
    });

    // Aplicar ordenamiento
    const grid = document.querySelector('.catalog-grid-min');
    if (!grid) return;

    visibleCards.sort((a, b) => {
      switch (sortMode) {
        case 'name_asc':
          return a.name.localeCompare(b.name);
        case 'name_desc':
          return b.name.localeCompare(a.name);
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

    // Reordenar las tarjetas en el DOM
    visibleCards.forEach(({ card }) => {
      grid.appendChild(card);
    });
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

  function setupProductGalleries() {
    document.querySelectorAll('[data-product-gallery]').forEach((gallery) => {
      const images = Array.from(gallery.querySelectorAll('.product-gallery-image'));
      if (images.length <= 1) return;

      let currentIndex = 0;
      const currentEl = gallery.querySelector('[data-gallery-current]');
      const prevBtn = gallery.querySelector('[data-gallery-prev]');
      const nextBtn = gallery.querySelector('[data-gallery-next]');

      const render = () => {
        images.forEach((img, idx) => {
          img.classList.toggle('active', idx === currentIndex);
        });
        if (currentEl) currentEl.textContent = String(currentIndex + 1);
      };

      const move = (delta) => {
        currentIndex = (currentIndex + delta + images.length) % images.length;
        render();
      };

      if (prevBtn) {
        prevBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          move(-1);
        });
      }

      if (nextBtn) {
        nextBtn.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          move(1);
        });
      }
    });
  }

  function setupHandlers() {
    document.querySelectorAll('[data-add-product]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const product = {
          id: btn.dataset.id,
          sku: btn.dataset.sku,
          name: decodeHtmlEntities(btn.dataset.name),
          image_url: btn.dataset.image || 'images/products/default-product.svg',
          unit_price: toNumber(btn.dataset.price)
        };
        addToCart(product);
      });
    });

    document.querySelectorAll('[data-compare-product]').forEach((btn) => {
      btn.addEventListener('click', () => {
        const product = {
          id: btn.dataset.id,
          sku: btn.dataset.sku,
          name: decodeHtmlEntities(btn.dataset.name),
          image_url: btn.dataset.image || 'images/products/default-product.svg',
          unit_price: toNumber(btn.dataset.price),
          category: btn.dataset.category || ''
        };
        addToCompare(product);
      });
    });

    const filterIds = ['catalogSearch', 'filterStock', 'filterMinPrice', 'filterMaxPrice', 'filterSort'];
    filterIds.forEach((id) => {
      const el = document.getElementById(id);
      if (el) el.addEventListener('input', applyFilters);
      if (el && el.tagName === 'SELECT') el.addEventListener('change', applyFilters);
    });

    const clearFilters = document.getElementById('clearFilters');
    if (clearFilters) {
      clearFilters.addEventListener('click', () => {
        const search = document.getElementById('catalogSearch');
        const stock = document.getElementById('filterStock');
        const minPrice = document.getElementById('filterMinPrice');
        const maxPrice = document.getElementById('filterMaxPrice');
        const sort = document.getElementById('filterSort');
        if (search) search.value = '';
        if (stock) stock.value = '';
        if (minPrice) minPrice.value = '';
        if (maxPrice) maxPrice.value = '';
        if (sort) sort.value = 'name_asc';
        selectedQuickCategory = '';
        document.querySelectorAll('[data-quick-category]').forEach((btn) => {
          btn.classList.toggle('active', (btn.dataset.quickCategory || '') === '');
        });
        applyFilters();
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
    if (closeBtn && drawer) closeBtn.addEventListener('click', () => drawer.classList.remove('open'));

    const ticketBtn = document.getElementById('printTicket');
    if (ticketBtn) ticketBtn.addEventListener('click', () => drawTicketPdf('thermal'));

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

  function addToCompare(product) {
    const COMPARE_STORAGE = 'truper_compare';
    let compareList = readJson(COMPARE_STORAGE, []);
    
    // Verificar si el producto ya está en la lista
    const existingIndex = compareList.findIndex(p => p.sku === product.sku);
    if (existingIndex !== -1) {
      if (window.showAlert) {
        window.showAlert('Este producto ya está en la comparación', 'warning');
      }
      return;
    }
    
    // Limitar a 4 productos para comparación
    if (compareList.length >= 4) {
      if (window.showAlert) {
        window.showAlert('Máximo 4 productos para comparar', 'warning');
      }
      return;
    }
    
    compareList.push(product);
    writeJson(COMPARE_STORAGE, compareList);
    
    if (window.showAlert) {
      window.showAlert('Producto agregado a comparación', 'success');
    }
    
    // Mostrar botón de ver comparación si hay productos
    updateCompareButton();
  }

  function updateCompareButton() {
    const COMPARE_STORAGE = 'truper_compare';
    const compareList = readJson(COMPARE_STORAGE, []);
    
    let compareBtn = document.getElementById('compareBtn');
    if (!compareBtn && compareList.length > 0) {
      compareBtn = document.createElement('button');
      compareBtn.id = 'compareBtn';
      compareBtn.className = 'btn btn-secondary';
      compareBtn.textContent = `Comparar (${compareList.length})`;
      compareBtn.style.cssText = 'position: fixed; bottom: 80px; right: 20px; z-index: 999;';
      compareBtn.addEventListener('click', showCompareModal);
      document.body.appendChild(compareBtn);
    } else if (compareBtn) {
      if (compareList.length === 0) {
        compareBtn.remove();
      } else {
        compareBtn.textContent = `Comparar (${compareList.length})`;
      }
    }
  }

  function showCompareModal() {
    const COMPARE_STORAGE = 'truper_compare';
    const compareList = readJson(COMPARE_STORAGE, []);
    
    if (compareList.length === 0) {
      if (window.showAlert) {
        window.showAlert('No hay productos para comparar', 'warning');
      }
      return;
    }
    
    // Crear modal de comparación
    const modal = document.createElement('div');
    modal.className = 'compare-modal';
    modal.style.cssText = `
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2000;
    `;
    
    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
      background: var(--bg-card);
      border-radius: 16px;
      padding: 2rem;
      max-width: 90vw;
      max-height: 90vh;
      overflow-y: auto;
      position: relative;
    `;
    
    let html = '<h2 style="margin-bottom: 1.5rem;">Comparación de Productos</h2>';
    html += '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">';
    
    compareList.forEach(product => {
      html += `
        <div style="border: 1px solid var(--border); border-radius: 8px; padding: 1rem; text-align: center;">
          <img src="${product.image_url}" alt="${product.name}" style="max-width: 100%; height: 150px; object-fit: contain; margin-bottom: 1rem;">
          <h3 style="font-size: 1rem; margin-bottom: 0.5rem;">${product.name}</h3>
          <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem;">${product.sku}</p>
          <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 0.5rem;">${product.category}</p>
          <p style="font-weight: 600; color: var(--accent); font-size: 1.1rem;">${money(product.unit_price)}</p>
          <button class="btn btn-small btn-danger" onclick="removeFromCompare('${product.sku}')" style="margin-top: 1rem;">Eliminar</button>
        </div>
      `;
    });
    
    html += '</div>';
    html += '<button class="btn btn-primary" onclick="closeCompareModal()" style="margin-top: 1.5rem; width: 100%;">Cerrar</button>';
    
    modalContent.innerHTML = html;
    modal.appendChild(modalContent);
    
    // Función para eliminar de comparación
    window.removeFromCompare = (sku) => {
      let compareList = readJson(COMPARE_STORAGE, []);
      compareList = compareList.filter(p => p.sku !== sku);
      writeJson(COMPARE_STORAGE, compareList);
      modal.remove();
      updateCompareButton();
      showCompareModal();
    };
    
    // Función para cerrar modal
    window.closeCompareModal = () => {
      modal.remove();
    };
    
    // Cerrar al hacer clic fuera del modal
    modal.addEventListener('click', (e) => {
      if (e.target === modal) {
        modal.remove();
      }
    });
    
    document.body.appendChild(modal);
  }

  function initCatalog() {
    setupProductGalleries();
    setupHandlers();
    setupAutocomplete();
    updateCartBadge();
    renderCart();
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initCatalog);
  } else {
    initCatalog();
  }
})();
