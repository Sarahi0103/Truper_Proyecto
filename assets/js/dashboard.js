/**
 * Dashboard JavaScript - TRUPPER
 */

// Cargar datos del dashboard
function loadDashboardData() {
    // Aquí iría la carga de datos via AJAX
}

// Actualizar estado de órdenes
function updateOrderStatus(orderId, newStatus) {
    fetch('/backend/controllers/order_controller.php?action=update_status', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            order_id: orderId,
            status: newStatus
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        }
    });
}

// Cargar puntos
function loadPoints() {
    const pointsEl = document.querySelector('.card-value');
    if (pointsEl) {
        // Los puntos ya se cargan desde PHP
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadDashboardData();
    loadPoints();
});
