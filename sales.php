<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Get canteen parameter from URL or use user's default
$canteen_type = $_GET['canteen'] ?? $_SESSION['canteen_type'];

// Ensure user can only access their assigned canteen(s)
if ($_SESSION['canteen_type'] !== 'both' && $canteen_type !== $_SESSION['canteen_type']) {
    $canteen_type = $_SESSION['canteen_type'];
}

// If canteen_type is 'both', default to 'primary'
if ($canteen_type === 'both') {
    $canteen_type = 'primary';
}

// Get products with categories based on selected canteen
$stmt = $conn->prepare("SELECT p.*, pc.name as category_name,
                       CASE 
                           WHEN ? = 'primary' THEN p.stock_primary
                           WHEN ? = 'secondary' THEN p.stock_secondary
                           ELSE GREATEST(p.stock_primary, p.stock_secondary)
                       END as available_stock
                       FROM products p 
                       LEFT JOIN product_categories pc ON p.category_id = pc.id
                       WHERE p.status = 'active' 
                       ORDER BY pc.name, p.name");
$stmt->execute([$canteen_type, $canteen_type]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filtering
$stmt = $conn->prepare("SELECT DISTINCT pc.id, pc.name 
                       FROM product_categories pc 
                       JOIN products p ON pc.id = p.category_id 
                       WHERE p.status = 'active' 
                       ORDER BY pc.name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get clients with positive balance (for account payments)
$client_condition = '';
if ($canteen_type === 'primary') {
    $client_condition = " AND (canteen_type = 'primary' OR type LIKE '%primary%')";
} elseif ($canteen_type === 'secondary') {
    $client_condition = " AND (canteen_type = 'secondary' OR type LIKE '%secondary%')";
}

$stmt = $conn->prepare("SELECT id, name, type, balance, debt 
                       FROM clients 
                       WHERE status = 'active' AND balance > 0 $client_condition
                       ORDER BY name");
$stmt->execute();
$clients_with_balance = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get debtors (clients with existing debt)
$stmt = $conn->prepare("SELECT id, name, type, balance, debt 
                       FROM clients 
                       WHERE status = 'active' AND debt > 0 $client_condition
                       ORDER BY name");
$stmt->execute();
$debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<!-- Tabs for Canteen Sections (only for admin/manager with access to both) -->
<?php if ($_SESSION['canteen_type'] === 'both'): ?>
<div class="tabs-container">
    <div class="tabs">
        <a href="?canteen=primary" class="tab <?php echo $canteen_type === 'primary' ? 'active' : ''; ?>">
            <i class="fas fa-graduation-cap"></i> 
            Cantina Primária
        </a>
        <a href="?canteen=secondary" class="tab <?php echo $canteen_type === 'secondary' ? 'active' : ''; ?>">
            <i class="fas fa-user-graduate"></i> 
            Cantina Secundária
        </a>
    </div>
</div>
<?php endif; ?>

<div class="pos-layout">
    <!-- Products Section -->
    <div class="products-section">
        <div class="products-header">
            <h2>Sistema de Vendas - <?php echo $canteen_type === 'primary' ? 'Cantina Primária' : 'Cantina Secundária'; ?></h2>
            <p>Selecione os produtos e finalize a venda</p>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="product-search" class="form-control" 
                       placeholder="Buscar produtos...">
            </div>
            
            <!-- Category Filter Buttons - Dynamic from Database -->
            <div class="category-filters">
                <button class="category-filter-btn active" data-category="all">
                    <i class="fas fa-th-large"></i>
                    <span>Todos</span>
                </button>
                <?php foreach ($categories as $category): ?>
                <button class="category-filter-btn" data-category="<?php echo strtolower($category['name']); ?>">
                    <i class="fas fa-<?php 
                        $icons = [
                            'bebidas' => 'tint',
                            'hambúrgueres' => 'hamburger',
                            'hamburguer' => 'hamburger',
                            'sandes' => 'bread-slice',
                            'bolachas' => 'cookie-bite',
                            'tostas' => 'utensils',
                            'tosta' => 'utensils',
                            'pregos' => 'drumstick-bite',
                            'prego' => 'drumstick-bite',
                            'snacks e doces' => 'candy-cane',
                            'salgadinhos' => 'pepper-hot'
                        ];
                        echo $icons[strtolower($category['name'])] ?? 'box';
                    ?>"></i>
                    <span><?php echo htmlspecialchars($category['name']); ?></span>
                </button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="products-content">
            <h3>Produtos Disponíveis (<?php echo count($products); ?>)</h3>
            
            <!-- Products Grid - Small Cards with Price -->
            <div class="products-grid-compact">
                <?php foreach ($products as $product): ?>
                <?php
                // Check if product can be sold with zero stock
                $allowZeroStock = false;
                $categoryName = strtolower($product['category_name'] ?? '');
                
                // Allow zero stock for prepared foods
                if (in_array($categoryName, ['hamburguer', 'hambúrgueres', 'tosta', 'tostas', 'sandes', 'prego', 'pregos'])) {
                    $allowZeroStock = true;
                }
                
                // Determine if product is available
                $isAvailable = $product['available_stock'] > 0 || $allowZeroStock;
                ?>
                <div class="product-card-compact <?php echo !$isAvailable ? 'out-of-stock' : ''; ?>" 
                     data-product-id="<?php echo $product['id']; ?>"
                     data-product-name="<?php echo htmlspecialchars($product['name']); ?>"
                     data-product-price="<?php echo $product['selling_price']; ?>"
                     data-product-stock="<?php echo $product['available_stock']; ?>"
                     data-allow-zero-stock="<?php echo $allowZeroStock ? 'true' : 'false'; ?>"
                     data-category="<?php echo strtolower($product['category_name'] ?? 'outros'); ?>">
                    
                    <div class="product-info-compact">
                        <div class="product-name-compact"><?php echo htmlspecialchars($product['name']); ?></div>
                        <div class="product-price-compact">Mt <?php echo number_format($product['selling_price'], 2); ?></div>
                        <div class="product-stock-compact <?php echo $product['available_stock'] <= $product['min_stock'] ? 'low-stock' : ''; ?> <?php echo ($allowZeroStock && $product['available_stock'] < 0) ? 'negative-stock' : ''; ?>">
                            <?php echo $product['available_stock']; ?>
                        </div>
                    </div>
                    
                    <?php if ($isAvailable): ?>
                    <button class="product-add-btn-compact">
                        <i class="fas fa-plus"></i>
                        <span>Adicionar</span>
                    </button>
                    <?php else: ?>
                    <div class="product-out-badge-compact">
                        <i class="fas fa-times"></i>
                        <span>Esgotado</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Cart Section - Fixed Right Side with Proper Structure -->
    <div class="cart-panel">
        <!-- Empty Cart State -->
        <div class="empty-cart-state" id="empty-cart-state">
            <div class="cart-header-empty">
                <i class="fas fa-shopping-cart"></i>
                <span>Carrinho (0)</span>
            </div>
            <div class="empty-cart-content">
                <div class="empty-cart-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h3>Carrinho vazio</h3>
                <p>Adicione produtos para começar</p>
            </div>
        </div>
        
        <!-- Active Cart State with Fixed Structure -->
        <div class="active-cart-state" id="active-cart-state" style="display: none;">
            <!-- Fixed Header -->
            <div class="cart-header">
                <i class="fas fa-shopping-cart"></i>
                <span>Carrinho (<span id="cart-count">0</span>)</span>
            </div>
            
            <!-- Scrollable Middle Section -->
            <div class="cart-middle-section">
                <!-- Cart Items -->
                <div class="cart-items-container" id="cart-items">
                    <!-- Cart items will be populated here -->
                </div>
                
                <!-- Cart Summary -->
                <div class="cart-summary">
                    <div class="subtotal-row">
                        <span>Subtotal:</span>
                        <span id="cart-subtotal">Mt 0.00</span>
                    </div>
                    <div class="total-row">
                        <span>Total:</span>
                        <span id="cart-total">Mt 0.00</span>
                    </div>
                </div>
                
                <!-- Payment Method -->
                <div class="payment-method-section">
                    <label class="form-label">Método de Pagamento</label>
                    <select id="payment-method-select" class="form-control form-select">
                        <option value="cash">Dinheiro</option>
                        <option value="mpesa">M-Pesa</option>
                        <option value="emola">E-Mola</option>
                        <option value="account">Conta (Cliente com Saldo)</option>
                        <option value="voucher">Voucher</option>
                        <option value="debt">Dívida (Devedor)</option>
                    </select>
                </div>
                
                <!-- Client Selection for Account Payment -->
                <div id="client-selection" class="client-selection" style="display: none;">
                    <label class="form-label">Cliente com Saldo</label>
                    <select id="client-select" class="form-control form-select">
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clients_with_balance as $client): ?>
                            <option value="<?php echo $client['id']; ?>" 
                                    data-balance="<?php echo $client['balance']; ?>"
                                    data-debt="<?php echo $client['debt']; ?>"
                                    data-type="<?php echo $client['type']; ?>">
                                <?php echo htmlspecialchars($client['name']); ?> 
                                (<?php 
                                $type_names = [
                                    'primary' => 'Aluno Primária',
                                    'secondary' => 'Aluno Secundária',
                                    'teacher_primary' => 'Prof. Primária',
                                    'teacher_secondary' => 'Prof. Secundária'
                                ];
                                echo $type_names[$client['type']] ?? ucfirst($client['type']);
                                ?>) - 
                                Saldo: Mt <?php echo number_format($client['balance'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Debtor Selection for Debt Payment -->
                <div id="debtor-selection" class="debtor-selection" style="display: none;">
                    <div class="debtor-options">
                        <label class="debtor-option">
                            <input type="radio" name="debtor_type" value="existing" checked>
                            <span>Devedor Existente</span>
                        </label>
                        <label class="debtor-option">
                            <input type="radio" name="debtor_type" value="new">
                            <span>Criar Nova Dívida</span>
                        </label>
                    </div>
                    
                    <!-- Existing Debtor -->
                    <div id="existing-debtor" class="debtor-section">
                        <label class="form-label">Devedor Existente</label>
                        <select id="debtor-select" class="form-control form-select">
                            <option value="">Selecione um devedor...</option>
                            <?php foreach ($debtors as $debtor): ?>
                                <option value="<?php echo $debtor['id']; ?>" 
                                        data-debt="<?php echo $debtor['debt']; ?>"
                                        data-type="<?php echo $debtor['type']; ?>">
                                    <?php echo htmlspecialchars($debtor['name']); ?> 
                                    (<?php 
                                    echo $type_names[$debtor['type']] ?? ucfirst($debtor['type']);
                                    ?>) - 
                                    Dívida: Mt <?php echo number_format($debtor['debt'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- New Debtor -->
                    <div id="new-debtor" class="debtor-section" style="display: none;">
                        <div class="form-group">
                            <label class="form-label">Nome do Devedor</label>
                            <input type="text" id="new-debtor-name" class="form-control" placeholder="Digite o nome...">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Tipo de Cliente</label>
                            <select id="new-debtor-type" class="form-control form-select">
                                <?php if ($canteen_type === 'primary'): ?>
                                <option value="primary">Aluno Primária</option>
                                <option value="teacher_primary">Professor Primária</option>
                                <?php elseif ($canteen_type === 'secondary'): ?>
                                <option value="secondary">Aluno Secundária</option>
                                <option value="teacher_secondary">Professor Secundária</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Reservation Option -->
                <div class="reservation-section">
                    <label class="reservation-checkbox">
                        <input type="checkbox" id="is-reservation" value="1">
                        <span class="checkmark"></span>
                        <div class="reservation-text">
                            <i class="fas fa-clock"></i>
                            <span>Fazer Reserva (Cliente retira depois)</span>
                        </div>
                    </label>
                    
                    <div id="reservation-client-name" style="display: none; margin-top: 1rem;">
                        <label class="form-label">Nome para a Reserva</label>
                        <input type="text" id="reservation-name" class="form-control" placeholder="Digite o nome do cliente...">
                    </div>
                </div>
            </div>
            
            <!-- Fixed Bottom Section -->
            <div class="cart-bottom-section">
                <div id="payment-warnings" style="display: none;"></div>
                
                <button type="button" id="complete-sale" class="btn btn-success btn-large">
                    <i class="fas fa-check"></i>
                    Finalizar Venda
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Store current canteen type for API calls
const currentCanteenType = '<?php echo $canteen_type; ?>';

// Enhanced POS functionality with zero stock support
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment-method-select');
    const clientSelection = document.getElementById('client-selection');
    const debtorSelection = document.getElementById('debtor-selection');
    const existingDebtorSection = document.getElementById('existing-debtor');
    const newDebtorSection = document.getElementById('new-debtor');
    const isReservationCheckbox = document.getElementById('is-reservation');
    const reservationClientName = document.getElementById('reservation-client-name');
    const reservationNameInput = document.getElementById('reservation-name');
    const paymentWarnings = document.getElementById('payment-warnings');
    const emptyCartState = document.getElementById('empty-cart-state');
    const activeCartState = document.getElementById('active-cart-state');
    
    // Initialize cart if not exists
    if (!window.canteenApp) {
        window.canteenApp = {
            cart: [],
            addToCart: function(productId, productName, productPrice) {
                const existingItem = this.cart.find(item => item.id === productId);
                
                if (existingItem) {
                    existingItem.quantity += 1;
                } else {
                    this.cart.push({
                        id: productId,
                        name: productName,
                        price: productPrice,
                        quantity: 1
                    });
                }
                
                this.updateCartDisplay();
                this.showAlert('success', `${productName} adicionado ao carrinho!`, 2000);
            },
            updateCartQuantity: function(productId, change) {
                const item = this.cart.find(item => item.id === productId);
                if (item) {
                    item.quantity += change;
                    if (item.quantity <= 0) {
                        this.removeFromCart(productId);
                    } else {
                        this.updateCartDisplay();
                    }
                }
            },
            removeFromCart: function(productId) {
                this.cart = this.cart.filter(item => item.id !== productId);
                this.updateCartDisplay();
            },
            clearCart: function() {
                this.cart = [];
                this.updateCartDisplay();
                this.showAlert('info', 'Carrinho limpo!', 1500);
            },
            showAlert: function(type, message, duration = 4000) {
                // Simple alert implementation
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type}`;
                alertDiv.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    padding: 1rem;
                    border-radius: 8px;
                    color: white;
                    font-weight: 600;
                    max-width: 300px;
                `;
                
                switch(type) {
                    case 'success':
                        alertDiv.style.backgroundColor = '#28a745';
                        break;
                    case 'error':
                        alertDiv.style.backgroundColor = '#dc3545';
                        break;
                    case 'warning':
                        alertDiv.style.backgroundColor = '#ffc107';
                        alertDiv.style.color = '#000';
                        break;
                    case 'info':
                        alertDiv.style.backgroundColor = '#17a2b8';
                        break;
                }
                
                alertDiv.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
                document.body.appendChild(alertDiv);
                
                setTimeout(() => {
                    if (alertDiv.parentElement) {
                        alertDiv.remove();
                    }
                }, duration);
            },
            updateCartDisplay: function() {
                const cartItems = document.getElementById('cart-items');
                const cartTotal = document.getElementById('cart-total');
                const cartSubtotal = document.getElementById('cart-subtotal');
                const cartCount = document.getElementById('cart-count');
                
                if (!cartItems) return;

                if (this.cart.length === 0) {
                    cartItems.innerHTML = '';
                    if (cartTotal) cartTotal.textContent = 'Mt 0.00';
                    if (cartSubtotal) cartSubtotal.textContent = 'Mt 0.00';
                    if (cartCount) cartCount.textContent = '0';
                    updateCartVisibility();
                    return;
                }

                let html = '';
                let total = 0;
                let totalItems = 0;

                this.cart.forEach(item => {
                    const itemTotal = item.price * item.quantity;
                    total += itemTotal;
                    totalItems += item.quantity;

                    html += `
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <div class="cart-item-name">${item.name}</div>
                                <div class="cart-item-price">Mt ${item.price.toFixed(2)} cada</div>
                            </div>
                            <div class="cart-item-controls">
                                <button type="button" class="quantity-btn cart-item-minus" data-product-id="${item.id}">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <span class="cart-item-quantity">${item.quantity}</span>
                                <button type="button" class="quantity-btn cart-item-plus" data-product-id="${item.id}">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button type="button" class="quantity-btn cart-item-remove" data-product-id="${item.id}" style="background: var(--error-color); color: white; border-color: var(--error-color); margin-left: 0.5rem;">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="cart-item-total">Mt ${itemTotal.toFixed(2)}</div>
                        </div>
                    `;
                });

                cartItems.innerHTML = html;
                if (cartTotal) cartTotal.textContent = `Mt ${total.toFixed(2)}`;
                if (cartSubtotal) cartSubtotal.textContent = `Mt ${total.toFixed(2)}`;
                if (cartCount) cartCount.textContent = totalItems.toString();
                
                updateCartVisibility();
            }
        };
    }
    
    // Category filtering
    document.querySelectorAll('.category-filter-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            // Update active category
            document.querySelectorAll('.category-filter-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            const category = this.getAttribute('data-category');
            filterProducts(category);
        });
    });
    
    function filterProducts(category) {
        document.querySelectorAll('.product-card-compact').forEach(card => {
            if (category === 'all' || card.getAttribute('data-category').includes(category)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    }
    
    // Product search
    document.getElementById('product-search').addEventListener('input', function(e) {
        const searchTerm = e.target.value.toLowerCase();
        document.querySelectorAll('.product-card-compact').forEach(card => {
            const productName = card.getAttribute('data-product-name').toLowerCase();
            if (productName.includes(searchTerm)) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        });
    });
    
    // Product click handlers with zero stock support
    document.querySelectorAll('.product-card-compact').forEach(card => {
        card.addEventListener('click', function() {
            if (this.classList.contains('out-of-stock')) return;
            
            const productId = this.getAttribute('data-product-id');
            const productName = this.getAttribute('data-product-name');
            const productPrice = parseFloat(this.getAttribute('data-product-price'));
            const productStock = parseInt(this.getAttribute('data-product-stock'));
            const allowZeroStock = this.getAttribute('data-allow-zero-stock') === 'true';
            
            // Allow sale if stock > 0 OR if zero stock is allowed for this product
            if (productStock > 0 || allowZeroStock) {
                window.canteenApp.addToCart(productId, productName, productPrice);
                updateCartVisibility();
            }
        });
    });
    
    // Cart item controls
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('cart-item-plus') || e.target.closest('.cart-item-plus')) {
            const btn = e.target.classList.contains('cart-item-plus') ? e.target : e.target.closest('.cart-item-plus');
            const productId = btn.getAttribute('data-product-id');
            window.canteenApp.updateCartQuantity(productId, 1);
        }
        
        if (e.target.classList.contains('cart-item-minus') || e.target.closest('.cart-item-minus')) {
            const btn = e.target.classList.contains('cart-item-minus') ? e.target : e.target.closest('.cart-item-minus');
            const productId = btn.getAttribute('data-product-id');
            window.canteenApp.updateCartQuantity(productId, -1);
        }
        
        if (e.target.classList.contains('cart-item-remove') || e.target.closest('.cart-item-remove')) {
            const btn = e.target.classList.contains('cart-item-remove') ? e.target : e.target.closest('.cart-item-remove');
            const productId = btn.getAttribute('data-product-id');
            window.canteenApp.removeFromCart(productId);
        }
    });
    
    function updateCartVisibility() {
        if (window.canteenApp.cart.length === 0) {
            emptyCartState.style.display = 'flex';
            activeCartState.style.display = 'none';
        } else {
            emptyCartState.style.display = 'none';
            activeCartState.style.display = 'flex';
        }
    }
    
    // Payment method change handler
    paymentMethodSelect.addEventListener('change', function() {
        const selectedPayment = this.value;
        
        // Hide all sections first
        clientSelection.style.display = 'none';
        debtorSelection.style.display = 'none';
        paymentWarnings.style.display = 'none';
        
        if (selectedPayment === 'account') {
            clientSelection.style.display = 'block';
        } else if (selectedPayment === 'debt') {
            debtorSelection.style.display = 'block';
        }
        
        validatePaymentMethod();
    });
    
    // Debtor type change handler
    document.querySelectorAll('[name="debtor_type"]').forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'existing') {
                existingDebtorSection.style.display = 'block';
                newDebtorSection.style.display = 'none';
            } else {
                existingDebtorSection.style.display = 'none';
                newDebtorSection.style.display = 'block';
            }
        });
    });
    
    // Reservation checkbox handler
    isReservationCheckbox.addEventListener('change', function() {
        if (this.checked) {
            reservationClientName.style.display = 'block';
            reservationNameInput.required = true;
        } else {
            reservationClientName.style.display = 'none';
            reservationNameInput.required = false;
            reservationNameInput.value = '';
        }
    });
    
    function validatePaymentMethod() {
        const selectedPayment = paymentMethodSelect.value;
        paymentWarnings.style.display = 'none';
        paymentWarnings.innerHTML = '';
        
        if (selectedPayment === 'account') {
            const selectedClient = document.getElementById('client-select').value;
            if (selectedClient) {
                const selectedOption = document.getElementById('client-select').options[document.getElementById('client-select').selectedIndex];
                const balance = parseFloat(selectedOption.dataset.balance);
                const cartTotal = parseFloat(document.getElementById('cart-total').textContent.replace('Mt ', ''));
                
                if (balance < cartTotal) {
                    paymentWarnings.style.display = 'block';
                    paymentWarnings.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            Saldo insuficiente! Saldo: Mt ${balance.toFixed(2)} | Necessário: Mt ${cartTotal.toFixed(2)}
                            <br>
                            <small>O sistema transferirá automaticamente o cliente para os devedores.</small>
                        </div>
                    `;
                }
            }
        }
    }
    
    // Client select change handler - validate balance
    document.getElementById('client-select')?.addEventListener('change', validatePaymentMethod);
    
    // Enhanced complete sale function
    document.getElementById('complete-sale').addEventListener('click', function() {
        console.log('Complete sale button clicked');
        
        if (window.canteenApp.cart.length === 0) {
            window.canteenApp.showAlert('error', 'Carrinho está vazio!');
            return;
        }

        const paymentMethod = paymentMethodSelect.value;
        const isReservation = isReservationCheckbox.checked;
        const reservationName = reservationNameInput.value.trim();
        
        console.log('Payment method:', paymentMethod);
        console.log('Is reservation:', isReservation);
        console.log('Cart:', window.canteenApp.cart);
        
        let clientId = null;
        let finalPaymentMethod = paymentMethod;
        
        // Validation for reservation
        if (isReservation && !reservationName) {
            window.canteenApp.showAlert('error', 'Digite o nome para a reserva!');
            return;
        }
        
        // Handle different payment methods
        if (paymentMethod === 'account') {
            clientId = document.getElementById('client-select').value;
            if (!clientId) {
                window.canteenApp.showAlert('error', 'Selecione um cliente para pagamento por conta!');
                return;
            }
        } else if (paymentMethod === 'debt') {
            const debtorType = document.querySelector('[name="debtor_type"]:checked').value;
            
            if (debtorType === 'existing') {
                clientId = document.getElementById('debtor-select').value;
                if (!clientId) {
                    window.canteenApp.showAlert('error', 'Selecione um devedor existente!');
                    return;
                }
            } else {
                const newDebtorName = document.getElementById('new-debtor-name').value.trim();
                const newDebtorType = document.getElementById('new-debtor-type').value;
                
                if (!newDebtorName) {
                    window.canteenApp.showAlert('error', 'Digite o nome do novo devedor!');
                    return;
                }
                
                // We'll create the debtor in the backend
                clientId = 'new_debtor';
            }
        }
        
        // Set final payment method for reservations
        if (isReservation) {
            finalPaymentMethod = 'request';
        }
        
        const saleData = {
            items: window.canteenApp.cart,
            payment_method: finalPaymentMethod,
            client_id: clientId,
            total: window.canteenApp.cart.reduce((sum, item) => sum + (item.price * item.quantity), 0),
            is_reservation: isReservation,
            reservation_name: reservationName,
            new_debtor_name: paymentMethod === 'debt' && document.querySelector('[name="debtor_type"]:checked').value === 'new' ? 
                            document.getElementById('new-debtor-name').value.trim() : null,
            new_debtor_type: paymentMethod === 'debt' && document.querySelector('[name="debtor_type"]:checked').value === 'new' ? 
                            document.getElementById('new-debtor-type').value : null
        };

        console.log('Sale data:', saleData);

        // Show loading state
        this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
        this.disabled = true;

        fetch('api/complete_sale.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(saleData)
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                let message = isReservation ? 'Reserva criada com sucesso!' : 'Venda concluída com sucesso!';
                
                // Check if there was an auto-transfer to debt
                if (data.auto_transfer) {
                    window.canteenApp.showAlert('warning', data.notification, 6000);
                }
                
                window.canteenApp.showAlert('success', message);
                window.canteenApp.clearCart();
                
                // Reset form
                paymentMethodSelect.value = 'cash';
                clientSelection.style.display = 'none';
                debtorSelection.style.display = 'none';
                document.getElementById('client-select').value = '';
                document.getElementById('debtor-select').value = '';
                document.getElementById('new-debtor-name').value = '';
                isReservationCheckbox.checked = false;
                reservationClientName.style.display = 'none';
                reservationNameInput.value = '';
                paymentWarnings.style.display = 'none';
                document.querySelector('[name="debtor_type"][value="existing"]').checked = true;
                existingDebtorSection.style.display = 'block';
                newDebtorSection.style.display = 'none';
                
                updateCartVisibility();
            } else {
                window.canteenApp.showAlert('error', data.message || 'Erro ao processar venda');
            }
        })
        .catch(error => {
            console.error('Error completing sale:', error);
            window.canteenApp.showAlert('error', 'Erro ao processar venda: ' + error.message);
        })
        .finally(() => {
            // Reset button state
            this.innerHTML = '<i class="fas fa-check"></i> Finalizar Venda';
            this.disabled = false;
        });
    });
    
    // Initialize cart visibility
    updateCartVisibility();
});
</script>

<style>
/* Estilos para as abas de cantina */
.tabs-container {
    margin-bottom: 1.5rem;
}

.tabs {
    display: flex;
    gap: 0.5rem;
    border-bottom: 2px solid var(--border-color);
    overflow-x: auto;
    padding-bottom: 0.5rem;
}

.tab {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    padding: 1rem 1.25rem;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 500;
    transition: var(--transition);
    white-space: nowrap;
    border: 2px solid transparent;
    border-bottom: none;
    min-width: 120px;
    background: var(--card-background);
    font-size: 0.85rem;
    touch-action: manipulation;
}

.tab:hover {
    background: var(--background-color);
    color: var(--text-primary);
}

.tab.active {
    background: var(--primary-color);
    color: white;
    border-color: var(--primary-color);
}

.tab i {
    font-size: 1.1rem;
}

.tab small {
    font-size: 0.7rem;
    opacity: 0.8;
}

/* Additional styles for negative stock indication */
.product-stock-compact {
    font-size: 0.7rem;
    color: var(--text-secondary);
    text-align: right;
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
    background: var(--background-color);
    padding: 0.25rem 0.5rem;
    border-radius: 8px;
    font-weight: 600;
    border: 1px solid var(--border-color);
}

.product-stock-compact.low-stock {
    background: var(--warning-color);
    color: white;
    border-color: var(--warning-color);
}

/* Special styling for negative stock products (sold items) */
.product-stock-compact.negative-stock {
    background: #ff5722;
    color: white;
    border-color: #e64a19;
    font-weight: bold;
}

/* Ensure prepared food products are always clickable */
.product-card-compact[data-allow-zero-stock="true"]:not(.out-of-stock) {
    cursor: pointer;
    opacity: 1;
}

.product-card-compact[data-allow-zero-stock="true"]:not(.out-of-stock):hover {
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
    border-color: var(--primary-color);
}

/* Visual indicator for prepared foods */
.product-card-compact[data-allow-zero-stock="true"]::before {
    content: "🍽️";
    position: absolute;
    top: 0.25rem;
    left: 0.25rem;
    font-size: 0.8rem;
    background: rgba(255, 152, 0, 0.9);
    border-radius: 50%;
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1;
}
</style>

<?php include 'includes/footer.php'; ?>