/**
 * Barcode Scanner Integration
 * Integración para lector de códigos de barras
 */

let barcodeBuffer = '';
let lastScanTime = 0;
const SCAN_TIMEOUT = 100; // ms
const BARCODE_TIMEOUT = 5000; // Tiempo para completar código

/**
 * Inicializar lector de códigos de barras
 */
function initBarcodeScanner() {
    document.addEventListener('keypress', handleBarcodeInput);
}

/**
 * Manejar entrada de código de barras
 */
function handleBarcodeInput(event) {
    const currentTime = Date.now();
    
    // Resetear buffer si tiene mucho tiempo
    if (currentTime - lastScanTime > BARCODE_TIMEOUT) {
        barcodeBuffer = '';
    }
    
    // Solo capturar si no hay input activo o es el scanner
    if (event.target === document.body || event.target.classList.contains('barcode-input')) {
        if (event.key === 'Enter' && barcodeBuffer.length > 5) {
            processBarcode(barcodeBuffer);
            barcodeBuffer = '';
        } else if (event.key.match(/^[0-9]$/)) {
            barcodeBuffer += event.key;
        }
    }
    
    lastScanTime = currentTime;
}

/**
 * Procesar código de barras escaneado
 */
async function processBarcode(barcode) {
    console.log('Código escaneado:', barcode);
    
    try {
        // Buscar producto con este código
        const response = await apiCall(`/products/by-barcode?barcode=${barcode}`);
        
        if (response && response.success && response.product) {
            const product = response.product;
            
            // Agregar al carrito si es página de pedidos
            if (window.location.pathname.includes('orders.php')) {
                // Buscar en la tabla de productos y agregar
                const quantityInput = document.querySelector(`input[data-product-id="${product.id}"]`);
                if (quantityInput) {
                    const currentQuantity = parseInt(quantityInput.value) || 1;
                    addToCart(product.id, product.name, product.unit_price, currentQuantity + 1);
                } else {
                    addToCart(product.id, product.name, product.unit_price, 1);
                }
                showAlert(`✓ ${product.name} agregado al pedido`, 'success');
            }
            
            // Registrar escaneo
            await apiCall('/products/log-scan', 'POST', {
                barcode: barcode,
                product_id: product.id
            });
        } else {
            showAlert('Producto no encontrado: ' + barcode, 'warning');
        }
    } catch (error) {
        console.error('Error procesando código:', error);
        showAlert('Error al procesar código de barras', 'error');
    }
}

/**
 * Modo de prueba para scanners USB (simulado)
 */
function enableTestBarcodeMode() {
    const testBarcodes = [
        '7707055400110',  // Martillo
        '7707055400127',  // Llave Inglesa
        '7707055400134',  // Destornilladores
        '7707055400141',  // Tabla Madera
        '7707055400158'   // Clavos
    ];
    
    console.log('Modo de Prueba: Presiona números para escanear');
    console.log('Códigos disponibles:', testBarcodes);
}

// Inicializar cuando el documento esté listo
document.addEventListener('DOMContentLoaded', initBarcodeScanner);
