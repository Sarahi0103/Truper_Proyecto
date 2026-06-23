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

  function drawTicketPdf(format, overrideFolio = null) {
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
      const stockMatch = !stockMode || (stockMode === 'available' ? stock > 0 : stock <= 10);

      const isVisible = textMatch && categoryMatch && stockMatch;
      
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

    const filterIds = ['catalogSearch', 'filterStock', 'filterSort'];
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
        const sort = document.getElementById('filterSort');
        if (search) search.value = '';
        if (stock) stock.value = '';
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
    if (closeBtn && drawer) {
      closeBtn.addEventListener('click', () => {
        if (window.showPremiumModal) {
          window.showPremiumModal(
            'Cerrar Carrito',
            '¿Seguro que quieres cerrar el carrito?',
            '🛒',
            () => {
              drawer.classList.remove('open');
            }
          );
        } else {
          if (confirm('¿Seguro que quieres cerrar el carrito?')) {
            drawer.classList.remove('open');
          }
        }
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
        if (window.showAlert) window.showAlert('El carrito está vacío', 'warning');
        else alert('El carrito está vacío');
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
            const lineTotal = (toNumber(item.unit_price || 0) * toNumber(item.quantity || 0));
            message += `- ${item.name}\n`;
            message += `  Codigo: ${code}\n`;
            message += `  ${item.quantity} x $${toNumber(item.unit_price).toFixed(2)} = $${lineTotal.toFixed(2)}\n`;
            if (idx < (items.length - 1)) {
              message += '---------------------------\n';
            }
          });
          message += '---------------------------\n';
          message += `TOTAL: $${total.toFixed(2)}\n`;
          message += `PDF/Ticket: ${ticketUrl}\n\n`;
          message += 'Quedo atento(a) a disponibilidad y tiempo de entrega.';

          const encodedMsg = encodeURIComponent(message);
          const whatsappUrl = companyWhatsApp
            ? `https://wa.me/${companyWhatsApp}?text=${encodedMsg}`
            : `https://wa.me/?text=${encodedMsg}`;

          window.open(whatsappUrl, '_blank');
        })
        .catch(err => {
          console.error(err);
          if (window.showAlert) window.showAlert('Error: ' + err.message, 'error');
          else alert('Error: ' + err.message);
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
          if (window.showAlert) window.showAlert('El carrito está vacío', 'warning');
          else alert('El carrito está vacío');
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
            if (window.showAlert) window.showAlert('Error: ' + err.message, 'error');
            else alert('Error: ' + err.message);
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
