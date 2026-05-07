/**
 * MÓDULO DE ACTUALIZACIONES EN TIEMPO REAL
 * Fichero: public/js/admin_supply_realtime.js
 * 
 * Funcionalidades:
 * 1. Auto-refresh de lista de productos
 * 2. Sincronización automática con marketplace
 * 3. Actualización de stock en tiempo real
 * 4. Notificaciones de cambios
 */

class AdminSupplyRealtime {
    constructor(config = {}) {
        this.config = {
            pollInterval: 3000,           // Polling cada 3 segundos
            enableWebSocket: false,       // TODO: Implementar WebSocket
            enableNotifications: true,
            cacheExpiryTime: 300000,      // 5 minutos
            ...config
        };
        
        this.lastDataHash = null;
        this.isPolling = false;
        this.cache = new Map();
        this.listeners = new Map();
        
        this.init();
    }

    /**
     * Inicializar el módulo
     */
    init() {
        console.log('🚀 Inicializando actualizaciones en tiempo real...');
        
        // Iniciar polling
        this.startPolling();
        
        // Escuchar cambios en el formulario
        this.setupFormListeners();
        
        // Cuando hay un cambio, refrescar datos
        document.addEventListener('product-updated', () => this.refresh());
        document.addEventListener('product-deleted', () => this.refresh());
        document.addEventListener('marketplace-synced', () => this.refresh());
    }

    /**
     * FUNCIONALIDAD 1: Polling de datos
     * ==================================
     */
    startPolling() {
        if (this.isPolling) return;
        this.isPolling = true;
        
        console.log('📡 Iniciando polling cada ' + this.config.pollInterval + 'ms');
        
        setInterval(() => {
            this.refresh();
        }, this.config.pollInterval);
    }

    /**
     * Refrescar datos del servidor
     */
    async refresh() {
        try {
            const url = '/api/admin_supply?action=stock-list';
            
            // Usar caché si está disponible
            if (this.cache.has(url)) {
                const cached = this.cache.get(url);
                if (Date.now() - cached.timestamp < this.config.cacheExpiryTime) {
                    console.log('♻️ Usando datos cacheados');
                    return cached.data;
                }
            }
            
            console.log('🔄 Refrescando datos del servidor...');
            
            const response = await fetch(url, {
                cache: 'no-store',
                headers: {
                    'Cache-Control': 'no-cache, no-store, must-revalidate'
                }
            });
            
            if (!response.ok) {
                throw new Error('Error al cargar datos: ' + response.statusText);
            }
            
            const data = await response.json();
            
            // Guardar en caché
            this.cache.set(url, {
                data: data,
                timestamp: Date.now()
            });
            
            // Detectar cambios
            const newHash = this.hashData(data);
            if (newHash !== this.lastDataHash) {
                console.log('✨ Cambios detectados - Actualizando UI');
                this.updateUI(data);
                this.lastDataHash = newHash;
                
                // Disparar evento
                this.emit('data-changed', data);
            }
            
            return data;
            
        } catch (error) {
            console.error('❌ Error al refrescar:', error);
            return null;
        }
    }

    /**
     * FUNCIONALIDAD 2: Actualizar UI solo si hay cambios
     * ====================================================
     */
    updateUI(data) {
        if (!Array.isArray(data)) return;
        
        console.log('🎨 Actualizando ' + data.length + ' productos');
        
        data.forEach(product => {
            const row = document.querySelector(`[data-product-id="${product.id}"]`);
            
            if (row) {
                const oldData = JSON.parse(row.dataset.productData || '{}');
                
                // Solo actualizar si hay cambios
                if (JSON.stringify(oldData) !== JSON.stringify(product)) {
                    this.updateRow(row, product);
                    this.animateChange(row);
                }
            }
        });
    }

    /**
     * Actualizar una fila del tabla
     */
    updateRow(row, product) {
        // Actualizar código
        const codeCell = row.querySelector('[data-field="code"]');
        if (codeCell) codeCell.textContent = product.sku || '-';
        
        // Actualizar nombre
        const nameCell = row.querySelector('[data-field="name"]');
        if (nameCell) nameCell.textContent = product.name || '-';
        
        // Actualizar stock
        const stockCell = row.querySelector('[data-field="stock"]');
        if (stockCell) {
            const stock = product.stock || 0;
            stockCell.textContent = stock;
            
            // Colorear según disponibilidad
            if (stock === 0) {
                stockCell.classList.add('stock-zero');
            } else if (stock < 10) {
                stockCell.classList.add('stock-low');
            } else {
                stockCell.classList.remove('stock-zero', 'stock-low');
            }
        }
        
        // Actualizar precio
        const priceCell = row.querySelector('[data-field="price"]');
        if (priceCell) priceCell.textContent = '$' + (product.price || '0').toFixed(2);
        
        // Actualizar estado marketplace
        const mpCell = row.querySelector('[data-field="marketplace"]');
        if (mpCell) {
            mpCell.textContent = product.marketplace_enabled ? '✓ Activo' : '✗ Inactivo';
            mpCell.classList.toggle('marketplace-active', product.marketplace_enabled);
        }
        
        // Guardar datos nuevos
        row.dataset.productData = JSON.stringify(product);
    }

    /**
     * FUNCIONALIDAD 3: Sincronización con Marketplace
     * ================================================
     */
    async syncToMarketplace(productId) {
        try {
            console.log('📤 Sincronizando producto ' + productId + ' con marketplace...');
            
            const response = await fetch('/api/admin_supply?action=marketplace-sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId,
                    sync_timestamp: new Date().toISOString()
                })
            });
            
            if (response.ok) {
                console.log('✅ Sincronización completada');
                this.showNotification('Sincronizado con Marketplace', 'success');
                this.emit('marketplace-synced', { productId });
            }
            
        } catch (error) {
            console.error('❌ Error en sincronización:', error);
            this.showNotification('Error al sincronizar', 'error');
        }
    }

    /**
     * FUNCIONALIDAD 4: Animaciones de cambios
     * =======================================
     */
    animateChange(element) {
        // Animar cambio
        element.classList.add('row-updated');
        
        // Remover animación después
        setTimeout(() => {
            element.classList.remove('row-updated');
        }, 2000);
    }

    /**
     * Mostrar notificaciones
     */
    showNotification(message, type = 'info') {
        if (!this.config.enableNotifications) return;
        
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: ${type === 'success' ? '#28a745' : '#dc3545'};
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 9999;
            animation: slideIn 0.3s ease;
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    /**
     * FUNCIONALIDAD 5: Detectar cambios en formularios
     * ================================================
     */
    setupFormListeners() {
        const form = document.querySelector('[data-admin-form]');
        if (!form) return;
        
        // Cuando se guarda un producto
        form.addEventListener('submit', (e) => {
            if (e.target.dataset.action === 'product-save') {
                setTimeout(() => this.refresh(), 500);
            }
        });
    }

    /**
     * Utilidades
     */
    hashData(data) {
        return JSON.stringify(data).split('').reduce((a, b) => {
            a = ((a << 5) - a) + b.charCodeAt(0);
            return a & a;
        }, 0).toString();
    }

    emit(event, data) {
        if (this.listeners.has(event)) {
            this.listeners.get(event).forEach(callback => {
                callback(data);
            });
        }
    }

    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, []);
        }
        this.listeners.get(event).push(callback);
    }
}

// ============================================================
// INICIALIZAR CUANDO ESTÁ LISTO EL DOM
// ============================================================

document.addEventListener('DOMContentLoaded', () => {
    // Inicializar si estamos en página de admin_supply
    if (document.querySelector('[data-admin-supply-page]')) {
        window.adminSupplyRT = new AdminSupplyRealtime({
            pollInterval: 3000,           // Cada 3 segundos
            enableNotifications: true
        });
        
        console.log('✅ Sistema de actualizaciones en tiempo real activado');
    }
});

/**
 * ESTILOS CSS PARA ANIMACIONES
 * ============================
 * Agregar a css/admin_supply.css:
 */
const styles = `
@keyframes slideIn {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes highlight {
    0%, 100% { background-color: transparent; }
    50% { background-color: rgba(255, 127, 0, 0.2); }
}

.row-updated {
    animation: highlight 1s ease;
}

.stock-zero {
    color: #dc3545;
    font-weight: bold;
    background: rgba(220, 53, 69, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
}

.stock-low {
    color: #ffc107;
    font-weight: bold;
}

.marketplace-active {
    color: #28a745;
    font-weight: 600;
}

.notification {
    display: flex;
    align-items: center;
    gap: 8px;
    animation: slideIn 0.3s ease, slideOut 0.3s ease 2.7s forwards;
}

@keyframes slideOut {
    to {
        transform: translateX(400px);
        opacity: 0;
    }
}
`;

// Inyectar estilos si no existen
if (!document.querySelector('style[data-realtime]')) {
    const styleEl = document.createElement('style');
    styleEl.dataset.realtime = 'true';
    styleEl.textContent = styles;
    document.head.appendChild(styleEl);
}
