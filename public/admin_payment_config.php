<?php
require_once __DIR__ . '/../config/config.php';

require_login();
require_admin();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración de Pagos - Ferretería FOX</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/styles.css'); ?>">
    <link rel="stylesheet" href="<?php echo asset_url('css/theme.css'); ?>">
    <style>
        .payment-config-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .config-section {
            background: var(--card-bg, #1a1a2e);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid var(--border-color, rgba(255,127,0,0.2));
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--theme-accent, #ff7f00);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bank-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .bank-card {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 10px;
            padding: 16px;
            transition: all 0.3s;
        }
        
        .bank-card:hover {
            border-color: var(--theme-accent, #ff7f00);
            transform: translateY(-2px);
        }
        
        .bank-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .bank-details {
            font-size: 0.9rem;
            color: #aaa;
            line-height: 1.6;
        }
        
        .bank-actions {
            display: flex;
            gap: 10px;
            margin-top: 12px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.85rem;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #fff;
        }
        
        .form-input {
            width: 100%;
            padding: 10px 14px;
            background: rgba(255,255,255,0.1);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 6px;
            color: #fff;
            font-size: 0.95rem;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--theme-accent, #ff7f00);
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--card-bg, #1a1a2e);
            border-radius: 12px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .payment-method-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px;
            background: rgba(255,255,255,0.05);
            border-radius: 8px;
            margin-bottom: 12px;
        }
        
        .method-info {
            flex: 1;
        }
        
        .method-name {
            font-weight: 700;
            color: #fff;
        }
        
        .method-fee {
            font-size: 0.9rem;
            color: #888;
        }
        
        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.4s;
            border-radius: 26px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: var(--theme-accent, #ff7f00);
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }
    </style>
</head>
<body>
    <div class="payment-config-container">
        <h1 style="color: #fff; margin-bottom: 30px;">⚙️ Configuración de Pagos</h1>
        
        <!-- Sección de Bancos Mexicanos -->
        <div class="config-section">
            <div class="section-title">
                🏦 Bancos Mexicanos
                <button class="btn btn-primary btn-sm" onclick="openBankModal()">+ Agregar Banco</button>
            </div>
            <div id="banksGrid" class="bank-grid">
                <p style="color: #888;">Cargando bancos...</p>
            </div>
        </div>
        
        <!-- Sección de Métodos de Pago -->
        <div class="config-section">
            <div class="section-title">
                💳 Métodos de Pago
            </div>
            <div id="paymentMethodsList">
                <p style="color: #888;">Cargando métodos de pago...</p>
            </div>
        </div>
        
        <!-- Sección de Configuración Fiscal SAT -->
        <div class="config-section">
            <div class="section-title">
                📋 Configuración Fiscal SAT
            </div>
            <form id="satConfigForm">
                <div class="form-group">
                    <label class="form-label">RFC de la Empresa</label>
                    <input type="text" class="form-input" id="companyRfc" placeholder="Ej: FOX010101ABC" maxlength="13" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Razón Social</label>
                    <input type="text" class="form-input" id="companyTaxName" placeholder="Ej: Ferretería FOX S.A. de C.V." required>
                </div>
                <div class="form-group">
                    <label class="form-label">Régimen Fiscal (c_RegimenFiscal)</label>
                    <select class="form-input" id="companyTaxRegime" required>
                        <option value="601">601 - General de Ley Personas Morales</option>
                        <option value="603">603 - Personas Morales con Fines no Lucrativos</option>
                        <option value="605">605 - Sueldos y Salarios e Ingresos Asimilados</option>
                        <option value="606">606 - Arrendamiento</option>
                        <option value="612">612 - Personas Físicas con Actividades Empresariales</option>
                        <option value="620">620 - Régimen Simplificado de Confianza</option>
                        <option value="621">621 - Incorporación Fiscal</option>
                        <option value="626">626 - Régimen Simplificado de Confianza</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Código Postal Fiscal</label>
                    <input type="text" class="form-input" id="companyZipCode" placeholder="Ej: 44600" maxlength="5" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email de Facturación</label>
                    <input type="email" class="form-input" id="companyEmail" placeholder="facturacion@ferreteriafox.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" class="form-input" id="companyPhone" placeholder="Ej: 3312345678">
                </div>
                <div class="form-group">
                    <label class="form-label">Dirección Fiscal</label>
                    <textarea class="form-input" id="companyAddress" rows="3" placeholder="Dirección completa fiscal"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">API Key de Facturapi</label>
                    <input type="password" class="form-input" id="facturapiApiKey" placeholder="sk_live_xxxxxxxxxxxx">
                </div>
                <div class="form-group">
                    <label class="form-label">Proveedor PAC</label>
                    <select class="form-input" id="pacProvider">
                        <option value="facturapi">Facturapi</option>
                        <option value="finkok">Finkok</option>
                        <option value="sw">SW (Soluciones Web)</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Configuración Fiscal</button>
            </form>
        </div>
    </div>
    
    <!-- Modal para agregar/editar banco -->
    <div id="bankModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="bankModalTitle">Agregar Banco</h3>
                <button class="close-modal" onclick="closeBankModal()">&times;</button>
            </div>
            <form id="bankForm">
                <input type="hidden" id="bankId">
                <div class="form-group">
                    <label class="form-label">Nombre del Banco</label>
                    <input type="text" class="form-input" id="bankName" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Código de Banco SAT</label>
                    <input type="text" class="form-input" id="bankCode" placeholder="Ej: 002" maxlength="3" required>
                </div>
                <div class="form-group">
                    <label class="form-label">CLABE</label>
                    <input type="text" class="form-input" id="bankClabe" placeholder="18 dígitos" maxlength="18" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Cuenta</label>
                    <input type="text" class="form-input" id="accountNumber" placeholder="Opcional">
                </div>
                <div class="form-group">
                    <label class="form-label">Titular de la Cuenta</label>
                    <input type="text" class="form-input" id="accountHolder" required>
                </div>
                <div class="form-group">
                    <label class="form-label">RFC del Titular</label>
                    <input type="text" class="form-input" id="accountRfc" placeholder="Opcional" maxlength="13">
                </div>
                <div class="form-group">
                    <label class="form-label">URL del Logo</label>
                    <input type="url" class="form-input" id="bankLogo" placeholder="https://...">
                </div>
                <div class="form-group">
                    <label class="form-label">Métodos Soportados</label>
                    <div class="checkbox-group">
                        <label class="checkbox-item">
                            <input type="checkbox" id="supportsSpei" checked>
                            SPEI
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" id="supportsCard">
                            Tarjeta
                        </label>
                        <label class="checkbox-item">
                            <input type="checkbox" id="supportsTransfer" checked>
                            Transferencia
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="checkbox-item">
                        <input type="checkbox" id="bankActive" checked>
                        Banco Activo
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Guardar Banco</button>
            </form>
        </div>
    </div>
    
    <script src="<?php echo asset_url('js/catalog.js'); ?>"></script>
    <script>
        // Cargar bancos
        function loadBanks() {
            fetch('/api/admin_payment_config.php?action=get_banks')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderBanks(data.banks);
                    }
                });
        }
        
        function renderBanks(banks) {
            const grid = document.getElementById('banksGrid');
            if (banks.length === 0) {
                grid.innerHTML = '<p style="color: #888;">No hay bancos configurados</p>';
                return;
            }
            
            grid.innerHTML = banks.map(bank => `
                <div class="bank-card">
                    <div class="bank-name">${bank.bank_name}</div>
                    <div class="bank-details">
                        <div><strong>Código:</strong> ${bank.bank_code}</div>
                        <div><strong>CLABE:</strong> ${bank.clabe}</div>
                        <div><strong>Titular:</strong> ${bank.account_holder}</div>
                        <div><strong>SPEI:</strong> ${bank.supports_spei ? '✅' : '❌'}</div>
                        <div><strong>Tarjeta:</strong> ${bank.supports_card ? '✅' : '❌'}</div>
                        <div><strong>Transferencia:</strong> ${bank.supports_transfer ? '✅' : '❌'}</div>
                    </div>
                    <div class="bank-actions">
                        <button class="btn btn-primary btn-sm" onclick="editBank(${bank.id})">Editar</button>
                        <button class="btn btn-danger btn-sm" onclick="deleteBank(${bank.id})">Eliminar</button>
                    </div>
                </div>
            `).join('');
        }
        
        // Cargar métodos de pago
        function loadPaymentMethods() {
            fetch('/api/admin_payment_config.php?action=get_payment_methods')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderPaymentMethods(data.methods);
                    }
                });
        }
        
        function renderPaymentMethods(methods) {
            const list = document.getElementById('paymentMethodsList');
            list.innerHTML = methods.map(method => `
                <div class="payment-method-item">
                    <div class="method-info">
                        <div class="method-name">${method.method_name}</div>
                        <div class="method-fee">Comisión: ${method.fee_percentage}% + $${method.fee_fixed} | Min: $${method.min_amount}</div>
                        <div style="font-size: 0.85rem; color: #666;">${method.description || ''}</div>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" ${method.is_enabled ? 'checked' : ''} onchange="togglePaymentMethod('${method.method_code}', this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            `).join('');
        }
        
        // Cargar configuración SAT
        function loadSatConfig() {
            fetch('/api/admin_payment_config.php?action=get_sat_config')
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.config) {
                        const config = data.config;
                        document.getElementById('companyRfc').value = config.company_rfc || '';
                        document.getElementById('companyTaxName').value = config.company_tax_name || '';
                        document.getElementById('companyTaxRegime').value = config.company_tax_regime || '601';
                        document.getElementById('companyZipCode').value = config.company_zip_code || '';
                        document.getElementById('companyEmail').value = config.company_email || '';
                        document.getElementById('companyPhone').value = config.company_phone || '';
                        document.getElementById('companyAddress').value = config.company_address || '';
                        document.getElementById('facturapiApiKey').value = config.facturapi_api_key || '';
                        document.getElementById('pacProvider').value = config.pac_provider || 'facturapi';
                    }
                });
        }
        
        // Modal de banco
        function openBankModal(bankId = null) {
            document.getElementById('bankModal').classList.add('active');
            document.getElementById('bankForm').reset();
            document.getElementById('bankId').value = '';
            document.getElementById('bankModalTitle').textContent = 'Agregar Banco';
            
            if (bankId) {
                document.getElementById('bankModalTitle').textContent = 'Editar Banco';
                // Cargar datos del banco
                fetch(`/api/admin_payment_config.php?action=get_bank&bank_id=${bankId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.bank) {
                            const bank = data.bank;
                            document.getElementById('bankId').value = bank.id;
                            document.getElementById('bankName').value = bank.bank_name;
                            document.getElementById('bankCode').value = bank.bank_code;
                            document.getElementById('bankClabe').value = bank.clabe;
                            document.getElementById('accountNumber').value = bank.account_number || '';
                            document.getElementById('accountHolder').value = bank.account_holder;
                            document.getElementById('accountRfc').value = bank.rfc || '';
                            document.getElementById('bankLogo').value = bank.logo_url || '';
                            document.getElementById('supportsSpei').checked = bank.supports_spei;
                            document.getElementById('supportsCard').checked = bank.supports_card;
                            document.getElementById('supportsTransfer').checked = bank.supports_transfer;
                            document.getElementById('bankActive').checked = bank.is_active;
                        }
                    });
            }
        }
        
        function closeBankModal() {
            document.getElementById('bankModal').classList.remove('active');
        }
        
        function editBank(bankId) {
            openBankModal(bankId);
        }
        
        function deleteBank(bankId) {
            if (confirm('¿Está seguro de eliminar este banco?')) {
                fetch(`/api/admin_payment_config.php?action=delete_bank&bank_id=${bankId}`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ csrf_token: document.querySelector('meta[name="csrf-token"]')?.content })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        loadBanks();
                        alert('Banco eliminado correctamente');
                    } else {
                        alert('Error: ' + data.message);
                    }
                });
            }
        }
        
        // Formulario de banco
        document.getElementById('bankForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const bankId = document.getElementById('bankId').value;
            const action = bankId ? 'update_bank' : 'add_bank';
            const url = `/api/admin_payment_config.php?action=${action}${bankId ? '&bank_id=' + bankId : ''}`;
            
            const bankData = {
                bank_name: document.getElementById('bankName').value,
                bank_code: document.getElementById('bankCode').value,
                clabe: document.getElementById('bankClabe').value,
                account_number: document.getElementById('accountNumber').value,
                account_holder: document.getElementById('accountHolder').value,
                rfc: document.getElementById('accountRfc').value,
                logo_url: document.getElementById('bankLogo').value,
                supports_spei: document.getElementById('supportsSpei').checked,
                supports_card: document.getElementById('supportsCard').checked,
                supports_transfer: document.getElementById('supportsTransfer').checked,
                is_active: document.getElementById('bankActive').checked
            };
            
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...bankData, csrf_token: document.querySelector('meta[name="csrf-token"]')?.content })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    closeBankModal();
                    loadBanks();
                    alert('Banco guardado correctamente');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
        
        // Formulario de configuración SAT
        document.getElementById('satConfigForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const config = {
                company_rfc: document.getElementById('companyRfc').value,
                company_tax_name: document.getElementById('companyTaxName').value,
                company_tax_regime: document.getElementById('companyTaxRegime').value,
                company_zip_code: document.getElementById('companyZipCode').value,
                company_email: document.getElementById('companyEmail').value,
                company_phone: document.getElementById('companyPhone').value,
                company_address: document.getElementById('companyAddress').value,
                facturapi_api_key: document.getElementById('facturapiApiKey').value,
                pac_provider: document.getElementById('pacProvider').value
            };
            
            fetch('/api/admin_payment_config.php?action=update_sat_config', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ...config, csrf_token: document.querySelector('meta[name="csrf-token"]')?.content })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Configuración fiscal guardada correctamente');
                } else {
                    alert('Error: ' + data.message);
                }
            });
        });
        
        // Toggle método de pago (placeholder - necesitaría endpoint adicional)
        function togglePaymentMethod(methodCode, enabled) {
            console.log('Toggle', methodCode, enabled);
            // Implementar endpoint para habilitar/deshabilitar métodos
        }
        
        // Inicializar
        loadBanks();
        loadPaymentMethods();
        loadSatConfig();
    </script>
</body>
</html>
