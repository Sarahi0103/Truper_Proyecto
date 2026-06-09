/**
 * Búsqueda avanzada con filtros
 * Permite búsquedas complejas con múltiples filtros
 */

class AdvancedSearch {
    constructor(options = {}) {
        this.searchInput = options.searchInput || document.getElementById('searchInput');
        this.filterContainer = options.filterContainer || document.getElementById('filterContainer');
        this.resultsContainer = options.resultsContainer || document.getElementById('resultsContainer');
        this.onSearch = options.onSearch || null;
        this.filters = options.filters || {};
        
        this.init();
    }

    init() {
        if (!this.searchInput) return;

        // Crear contenedor de filtros si no existe
        if (!this.filterContainer) {
            this.createFilterContainer();
        }

        // Event listeners
        this.searchInput.addEventListener('input', this.debounce(this.handleSearch.bind(this), 300));
        
        // Botón de filtros
        const filterButton = document.createElement('button');
        filterButton.className = 'btn btn-filter-toggle';
        filterButton.innerHTML = '🔍 Filtros';
        filterButton.addEventListener('click', () => this.toggleFilters());
        this.searchInput.parentElement.appendChild(filterButton);

        // Cargar filtros guardados
        this.loadSavedFilters();
    }

    createFilterContainer() {
        this.filterContainer = document.createElement('div');
        this.filterContainer.className = 'filter-container';
        this.filterContainer.style.display = 'none';
        
        this.filterContainer.innerHTML = `
            <div class="filter-header">
                <h3>Filtros de búsqueda</h3>
                <button class="btn-close-filters">×</button>
            </div>
            <div class="filter-body">
                <div class="filter-group">
                    <label>Categoría</label>
                    <select id="filter-category" class="filter-select">
                        <option value="">Todas</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Precio mínimo</label>
                    <input type="number" id="filter-price-min" class="filter-input" placeholder="0">
                </div>
                <div class="filter-group">
                    <label>Precio máximo</label>
                    <input type="number" id="filter-price-max" class="filter-input" placeholder="999999">
                </div>
                <div class="filter-group">
                    <label>
                        <input type="checkbox" id="filter-in-stock">
                        Solo en stock
                    </label>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-primary btn-apply-filters">Aplicar</button>
                    <button class="btn btn-secondary btn-reset-filters">Limpiar</button>
                </div>
            </div>
        `;

        document.body.appendChild(this.filterContainer);

        // Event listeners para filtros
        this.filterContainer.querySelector('.btn-close-filters').addEventListener('click', () => this.toggleFilters());
        this.filterContainer.querySelector('.btn-apply-filters').addEventListener('click', () => this.applyFilters());
        this.filterContainer.querySelector('.btn-reset-filters').addEventListener('click', () => this.resetFilters());

        // Cargar categorías dinámicamente
        this.loadCategories();
    }

    loadCategories() {
        // Categorías predefinidas
        const categories = [
            'Herramientas Manuales',
            'Herramientas Eléctricas',
            'Plomería',
            'Electricidad',
            'Jardinería',
            'Construcción',
            'Seguridad Industrial',
            'Almacenamiento',
            'Automotriz',
            'General'
        ];

        const select = this.filterContainer.querySelector('#filter-category');
        categories.forEach(cat => {
            const option = document.createElement('option');
            option.value = cat;
            option.textContent = cat;
            select.appendChild(option);
        });
    }

    toggleFilters() {
        const isVisible = this.filterContainer.style.display !== 'none';
        this.filterContainer.style.display = isVisible ? 'none' : 'flex';
    }

    getFilters() {
        return {
            category: this.filterContainer.querySelector('#filter-category').value,
            priceMin: parseFloat(this.filterContainer.querySelector('#filter-price-min').value) || 0,
            priceMax: parseFloat(this.filterContainer.querySelector('#filter-price-max').value) || null,
            inStock: this.filterContainer.querySelector('#filter-in-stock').checked
        };
    }

    applyFilters() {
        const filters = this.getFilters();
        this.filters = filters;
        this.saveFilters();
        this.handleSearch();
        this.toggleFilters();
    }

    resetFilters() {
        this.filterContainer.querySelector('#filter-category').value = '';
        this.filterContainer.querySelector('#filter-price-min').value = '';
        this.filterContainer.querySelector('#filter-price-max').value = '';
        this.filterContainer.querySelector('#filter-in-stock').checked = false;
        this.filters = {};
        this.saveFilters();
        this.handleSearch();
        this.toggleFilters();
    }

    saveFilters() {
        localStorage.setItem('searchFilters', JSON.stringify(this.filters));
    }

    loadSavedFilters() {
        const saved = localStorage.getItem('searchFilters');
        if (saved) {
            try {
                this.filters = JSON.parse(saved);
                this.applyFiltersToUI();
            } catch (e) {
                console.error('Error loading saved filters:', e);
            }
        }
    }

    applyFiltersToUI() {
        if (!this.filterContainer) return;

        if (this.filters.category) {
            this.filterContainer.querySelector('#filter-category').value = this.filters.category;
        }
        if (this.filters.priceMin) {
            this.filterContainer.querySelector('#filter-price-min').value = this.filters.priceMin;
        }
        if (this.filters.priceMax) {
            this.filterContainer.querySelector('#filter-price-max').value = this.filters.priceMax;
        }
        if (this.filters.inStock) {
            this.filterContainer.querySelector('#filter-in-stock').checked = true;
        }
    }

    handleSearch() {
        const query = this.searchInput.value.trim();
        const filters = this.getFilters();

        if (this.onSearch) {
            this.onSearch(query, filters);
        }
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func.apply(this, args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    buildQueryString(query, filters) {
        const params = new URLSearchParams();
        
        if (query) {
            params.append('q', query);
        }
        
        if (filters.category) {
            params.append('category', filters.category);
        }
        
        if (filters.priceMin > 0) {
            params.append('price_min', filters.priceMin);
        }
        
        if (filters.priceMax > 0) {
            params.append('price_max', filters.priceMax);
        }
        
        if (filters.inStock) {
            params.append('in_stock', '1');
        }

        return params.toString();
    }

    destroy() {
        if (this.searchInput) {
            this.searchInput.removeEventListener('input', this.handleSearch);
        }
        if (this.filterContainer) {
            this.filterContainer.remove();
        }
    }
}

// Estilos CSS
const searchStyles = `
    .btn-filter-toggle {
        margin-left: 0.5rem;
        padding: 0.5rem 1rem;
        background: #333;
        border: 1px solid #444;
        color: #fff;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .btn-filter-toggle:hover {
        background: #444;
    }

    .filter-container {
        position: fixed;
        top: 0;
        right: 0;
        bottom: 0;
        width: 350px;
        background: #1a1a1a;
        border-left: 1px solid #333;
        z-index: 10000;
        display: flex;
        flex-direction: column;
        box-shadow: -4px 0 20px rgba(0, 0, 0, 0.5);
    }

    .filter-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.5rem;
        border-bottom: 1px solid #333;
    }

    .filter-header h3 {
        margin: 0;
        color: #fff;
        font-size: 1.2rem;
    }

    .btn-close-filters {
        background: none;
        border: none;
        color: #888;
        font-size: 1.5rem;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        border-radius: 50%;
    }

    .btn-close-filters:hover {
        background: #333;
        color: #fff;
    }

    .filter-body {
        flex: 1;
        padding: 1.5rem;
        overflow-y: auto;
    }

    .filter-group {
        margin-bottom: 1.5rem;
    }

    .filter-group label {
        display: block;
        color: #888;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        text-transform: uppercase;
    }

    .filter-select,
    .filter-input {
        width: 100%;
        padding: 0.75rem;
        background: #0d0d0f;
        border: 1px solid #222;
        border-radius: 8px;
        color: #fff;
        font-size: 0.95rem;
    }

    .filter-select:focus,
    .filter-input:focus {
        outline: none;
        border-color: #ff7f00;
    }

    .filter-actions {
        display: flex;
        gap: 0.5rem;
        margin-top: 2rem;
    }

    .filter-actions button {
        flex: 1;
        padding: 0.75rem;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-apply-filters {
        background: linear-gradient(90deg, #ff6600, #ff9500);
        border: none;
        color: #fff;
    }

    .btn-apply-filters:hover {
        opacity: 0.9;
    }

    .btn-reset-filters {
        background: #333;
        border: 1px solid #444;
        color: #fff;
    }

    .btn-reset-filters:hover {
        background: #444;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = searchStyles;
document.head.appendChild(styleSheet);

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (document.getElementById('searchInput')) {
            window.advancedSearch = new AdvancedSearch();
        }
    });
} else {
    if (document.getElementById('searchInput')) {
        window.advancedSearch = new AdvancedSearch();
    }
}

// Exportar para uso en módulos
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AdvancedSearch };
}
