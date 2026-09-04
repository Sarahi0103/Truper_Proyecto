/**
 * Sistema de actualizaciones en tiempo real
 * Actualiza datos automáticamente sin recargar la página
 */

class RealTimeUpdater {
    constructor(options = {}) {
        this.url = options.url || '/api/admin_supply.php?action=stock-list';
        this.interval = options.interval || 5000; // 5 segundos
        this.enabled = options.enabled !== false;
        this.callbacks = options.callbacks || {};
        this.lastData = null;
        this.timer = null;
        this.isVisible = true;
        
        this.init();
    }

    init() {
        if (!this.enabled) return;

        // Detectar visibilidad de la página
        document.addEventListener('visibilitychange', () => {
            this.isVisible = !document.hidden;
            if (this.isVisible) {
                this.start();
            } else {
                this.stop();
            }
        });

        // Iniciar actualizaciones
        this.start();
    }

    start() {
        if (this.timer) return;
        
        this.update(); // Actualización inmediata
        this.timer = setInterval(() => {
            if (this.isVisible) {
                this.update();
            }
        }, this.interval);
    }

    stop() {
        if (this.timer) {
            clearInterval(this.timer);
            this.timer = null;
        }
    }

    async update() {
        try {
            const response = await fetch(this.url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                background: true // No mostrar loader
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            
            if (data.success && data.data) {
                this.processUpdates(data.data);
            }
        } catch (error) {
            console.error('Error en actualización en tiempo real:', error);
            
            if (this.callbacks.onError) {
                this.callbacks.onError(error);
            }
        }
    }

    processUpdates(newData) {
        if (!this.lastData) {
            this.lastData = newData;
            if (this.callbacks.onInitialLoad) {
                this.callbacks.onInitialLoad(newData);
            }
            return;
        }

        const changes = this.detectChanges(this.lastData, newData);
        
        if (changes.length > 0) {
            if (this.callbacks.onChanges) {
                this.callbacks.onChanges(changes, newData);
            }
            
            this.applyChanges(changes);
        }

        this.lastData = newData;
    }

    detectChanges(oldData, newData) {
        const changes = [];
        
        // Detectar cambios en stock
        if (oldData.stock && newData.stock) {
            const oldStockMap = new Map(oldData.stock.map(item => [item.sku, item]));
            const newStockMap = new Map(newData.stock.map(item => [item.sku, item]));

            for (const [sku, newItem] of newStockMap) {
                const oldItem = oldStockMap.get(sku);
                
                if (!oldItem) {
                    changes.push({
                        type: 'added',
                        sku: sku,
                        data: newItem
                    });
                } else if (JSON.stringify(oldItem) !== JSON.stringify(newItem)) {
                    changes.push({
                        type: 'updated',
                        sku: sku,
                        oldData: oldItem,
                        newData: newItem
                    });
                }
            }

            for (const [sku, oldItem] of oldStockMap) {
                if (!newStockMap.has(sku)) {
                    changes.push({
                        type: 'deleted',
                        sku: sku,
                        data: oldItem
                    });
                }
            }
        }

        return changes;
    }

    applyChanges(changes) {
        changes.forEach(change => {
            const row = document.querySelector(`[data-sku="${change.sku}"]`);
            
            if (!row) return;

            switch (change.type) {
                case 'updated':
                    this.updateRow(row, change.newData);
                    this.highlightChange(row);
                    break;
                    
                case 'deleted':
                    this.removeRow(row);
                    break;
                    
                case 'added':
                    this.addRow(change.data);
                    break;
            }
        });
    }

    updateRow(row, data) {
        // Actualizar campos específicos
        const stockCell = row.querySelector('[data-field="stock"]');
        if (stockCell && data.stock_quantity !== undefined) {
            stockCell.textContent = data.stock_quantity;
            
            // Alerta de stock bajo
            if (data.stock_quantity <= data.reorder_level) {
                stockCell.classList.add('stock-low');
            } else {
                stockCell.classList.remove('stock-low');
            }
        }

        const priceCell = row.querySelector('[data-field="price"]');
        if (priceCell && data.unit_price !== undefined) {
            priceCell.textContent = this.formatPrice(data.unit_price);
        }

        const statusCell = row.querySelector('[data-field="status"]');
        if (statusCell && data.is_active !== undefined) {
            statusCell.textContent = data.is_active ? 'Activo' : 'Inactivo';
            statusCell.className = data.is_active ? 'status-active' : 'status-inactive';
        }
    }

    highlightChange(row) {
        row.classList.add('row-updated');
        
        setTimeout(() => {
            row.classList.remove('row-updated');
        }, 2000);
    }

    removeRow(row) {
        row.style.opacity = '0';
        row.style.transform = 'translateX(-20px)';
        
        setTimeout(() => {
            row.remove();
        }, 300);
    }

    addRow(data) {
        // Implementar según la estructura de la tabla
        // Esto depende de cómo esté estructurada la tabla en admin_supply.php
    }

    formatPrice(price) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN'
        }).format(price);
    }

    setUrl(url) {
        this.url = url;
        this.update();
    }

    setInterval(interval) {
        this.stop();
        this.interval = interval;
        this.start();
    }

    destroy() {
        this.stop();
    }
}

// Inicializar para admin_supply
if (document.querySelector('#admin-supply-table')) {
    const updater = new RealTimeUpdater({
        url: '/api/admin_supply.php?action=stock-list',
        interval: 5000,
        callbacks: {
            onInitialLoad: (data) => {
                console.log('Datos iniciales cargados:', data.stock?.length || 0, 'productos');
            },
            onChanges: (changes, newData) => {
                console.log('Cambios detectados:', changes.length);
                // Mostrar notificación de cambios
                if (changes.length > 0) {
                    showNotification(`${changes.length} producto(s) actualizado(s)`, 'info');
                }
            },
            onError: (error) => {
                console.error('Error en actualización:', error);
            }
        }
    });

    // Exponer para uso global
    window.adminSupplyUpdater = updater;
}

// Función helper para notificaciones
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `realtime-notification realtime-${type}`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    requestAnimationFrame(() => {
        notification.classList.add('show');
    });

    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

// Estilos CSS
const realtimeStyles = `
    .row-updated {
        background: rgba(46, 204, 113, 0.2) !important;
        transition: background 0.3s ease;
    }

    .stock-low {
        color: #e74c3c !important;
        font-weight: 700;
    }

    .realtime-notification {
        position: fixed;
        top: 2rem;
        right: 2rem;
        padding: 1rem 1.5rem;
        background: #1a1a1a;
        border: 1px solid #333;
        border-radius: 8px;
        color: #ffffff;
        font-weight: 600;
        z-index: 10000;
        opacity: 0;
        transform: translateY(-20px);
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .realtime-notification.show {
        opacity: 1;
        transform: translateY(0);
    }

    .realtime-info {
        border-color: #3498db;
    }

    .realtime-warning {
        border-color: #f39c12;
    }

    .realtime-error {
        border-color: #e74c3c;
    }

    .realtime-success {
        border-color: #2ecc71;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = realtimeStyles;
document.head.appendChild(styleSheet);

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { RealTimeUpdater };
}
