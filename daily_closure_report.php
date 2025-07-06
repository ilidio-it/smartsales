
<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Get report date from URL parameter or use today
$report_date = $_GET['date'] ?? date('Y-m-d');

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $report_date)) {
    $report_date = date('Y-m-d');
}

// Function to get sales data for a specific canteen and date
function getSalesData($conn, $canteen_type, $date) {
    $data = [
        'total_sales' => 0,
        'total_transactions' => 0,
        'payments' => [
            'cash' => ['transactions' => 0, 'amount' => 0],
            'mpesa' => ['transactions' => 0, 'amount' => 0],
            'emola' => ['transactions' => 0, 'amount' => 0],
            'account' => ['transactions' => 0, 'amount' => 0],
            'voucher' => ['transactions' => 0, 'amount' => 0],
            'debt' => ['transactions' => 0, 'amount' => 0]
        ],
        'products_sold' => 0,
        'balance_added' => 0,
        'balance_transactions' => 0,
        'debt_amount' => 0,
        'debt_transactions' => 0
    ];
    
    // Get sales by payment method
    $stmt = $conn->prepare("SELECT 
        payment_method,
        COUNT(*) as transactions,
        SUM(total_amount) as amount,
        SUM(items_count) as products_sold
    FROM sales 
    WHERE canteen_type = ? AND sale_date = ?
    GROUP BY payment_method");
    $stmt->execute([$canteen_type, $date]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($sales as $sale) {
        $method = $sale['payment_method'];
        if (isset($data['payments'][$method])) {
            $data['payments'][$method]['transactions'] = $sale['transactions'];
            $data['payments'][$method]['amount'] = $sale['amount'];
            $data['total_sales'] += $sale['amount'];
            $data['total_transactions'] += $sale['transactions'];
            $data['products_sold'] += $sale['products_sold'] ?? 0;
        }
    }
    
    // Get client transactions (balance additions) for this canteen
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as transactions,
        SUM(amount) as total_amount
    FROM client_transactions 
    WHERE transaction_type = 'credit' 
    AND canteen_type = ? 
    AND DATE(created_at) = ?");
    $stmt->execute([$canteen_type, $date]);
    $balance_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($balance_data) {
        $data['balance_transactions'] = $balance_data['transactions'] ?? 0;
        $data['balance_added'] = $balance_data['total_amount'] ?? 0;
    }
    
    // Get debt transactions for this canteen
    $data['debt_amount'] = $data['payments']['debt']['amount'];
    $data['debt_transactions'] = $data['payments']['debt']['transactions'];
    
    return $data;
}

// Get data for both canteens
$primary_data = getSalesData($conn, 'primary', $report_date);
$secondary_data = getSalesData($conn, 'secondary', $report_date);

// Calculate totals
$totals = [
    'total_sales' => $primary_data['total_sales'] + $secondary_data['total_sales'],
    'total_transactions' => $primary_data['total_transactions'] + $secondary_data['total_transactions'],
    'products_sold' => $primary_data['products_sold'] + $secondary_data['products_sold'],
    'balance_added' => $primary_data['balance_added'] + $secondary_data['balance_added'],
    'balance_transactions' => $primary_data['balance_transactions'] + $secondary_data['balance_transactions'],
    'debt_amount' => $primary_data['debt_amount'] + $secondary_data['debt_amount'],
    'debt_transactions' => $primary_data['debt_transactions'] + $secondary_data['debt_transactions'],
    'payments' => []
];

// Calculate payment method totals
foreach (['cash', 'mpesa', 'emola', 'account', 'voucher', 'debt'] as $method) {
    $totals['payments'][$method] = [
        'transactions' => $primary_data['payments'][$method]['transactions'] + $secondary_data['payments'][$method]['transactions'],
        'amount' => $primary_data['payments'][$method]['amount'] + $secondary_data['payments'][$method]['amount']
    ];
}

// Get top products sold
$stmt = $conn->prepare("SELECT 
    p.name,
    SUM(si.quantity) as total_quantity,
    SUM(si.total_price) as total_value,
    COUNT(DISTINCT s.id) as transactions
FROM sale_items si
JOIN products p ON si.product_id = p.id
JOIN sales s ON si.sale_id = s.id
WHERE s.sale_date = ?
GROUP BY p.id, p.name
ORDER BY total_quantity DESC
LIMIT 10");
$stmt->execute([$report_date]);
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-calculator"></i>
            Relatório de Fecho de Contas Diário
        </h1>
        <div class="header-actions">
            <input type="date" id="report-date" class="form-control" value="<?php echo $report_date; ?>" 
                   onchange="changeDate(this.value)">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i>
                Imprimir
            </button>
            <button type="button" class="btn btn-success" onclick="exportReport()">
                <i class="fas fa-file-excel"></i>
                Exportar
            </button>
        </div>
    </div>
    
    <!-- Date Header -->
    <div class="date-header">
        <h2><?php echo date('d/m/Y', strtotime($report_date)); ?> - <?php echo date('l', strtotime($report_date)); ?></h2>
        <p>Relatório consolidado de vendas e movimentações financeiras</p>
    </div>
    
    <!-- Cash Flow Summary -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 style="color: var(--primary-color);">
                <i class="fas fa-chart-line"></i>
                Resumo de Fluxo de Caixa
            </h3>
        </div>
        <div class="cash-flow-grid">
            <!-- Primary Canteen -->
            <div class="cash-flow-section">
                <h4>Cantina Primária</h4>
                <div class="cash-flow-details">
                    <div class="cash-item">
                        <span>Total de Vendas:</span>
                        <span><strong>Mt <?php echo number_format($primary_data['total_sales'], 2); ?></strong></span>
                    </div>
                    <div class="cash-item">
                        <span>Transações:</span>
                        <span><?php echo $primary_data['total_transactions']; ?></span>
                    </div>
                    <div class="cash-item">
                        <span>Produtos Vendidos:</span>
                        <span><?php echo $primary_data['products_sold']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Secondary Canteen -->
            <div class="cash-flow-section">
                <h4>Cantina Secundária</h4>
                <div class="cash-flow-details">
                    <div class="cash-item">
                        <span>Total de Vendas:</span>
                        <span><strong>Mt <?php echo number_format($secondary_data['total_sales'], 2); ?></strong></span>
                    </div>
                    <div class="cash-item">
                        <span>Transações:</span>
                        <span><?php echo $secondary_data['total_transactions']; ?></span>
                    </div>
                    <div class="cash-item">
                        <span>Produtos Vendidos:</span>
                        <span><?php echo $secondary_data['products_sold']; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Total -->
            <div class="cash-flow-section total-section">
                <h4>Total Geral</h4>
                <div class="cash-flow-details">
                    <div class="cash-item">
                        <span>Total de Vendas:</span>
                        <span><strong style="color: var(--primary-color); font-size: 1.2rem;">Mt <?php echo number_format($totals['total_sales'], 2); ?></strong></span>
                    </div>
                    <div class="cash-item">
                        <span>Transações:</span>
                        <span><strong><?php echo $totals['total_transactions']; ?></strong></span>
                    </div>
                    <div class="cash-item">
                        <span>Produtos Vendidos:</span>
                        <span><strong><?php echo $totals['products_sold']; ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Payment Methods Breakdown -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>
                <i class="fas fa-credit-card"></i>
                Breakdown por Método de Pagamento
            </h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Método de Pagamento</th>
                        <th class="text-center">Primária Trans.</th>
                        <th class="text-right">Primária Valor</th>
                        <th class="text-center">Secundária Trans.</th>
                        <th class="text-right">Secundária Valor</th>
                        <th class="text-center">Total Trans.</th>
                        <th class="text-right">Total Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $payment_method_names = [
                        'cash' => 'Dinheiro',
                        'mpesa' => 'M-Pesa',
                        'emola' => 'E-Mola',
                        'account' => 'Conta',
                        'voucher' => 'Voucher',
                        'debt' => 'Dívida'
                    ];
                    
                    foreach ($payment_method_names as $method => $name): 
                        $primary_trans = $primary_data['payments'][$method]['transactions'];
                        $primary_amount = $primary_data['payments'][$method]['amount'];
                        $secondary_trans = $secondary_data['payments'][$method]['transactions'];
                        $secondary_amount = $secondary_data['payments'][$method]['amount'];
                        $total_trans = $totals['payments'][$method]['transactions'];
                        $total_amount = $totals['payments'][$method]['amount'];
                    ?>
                    <tr>
                        <td>
                            <span class="badge badge-<?php echo $method; ?>">
                                <?php echo $name; ?>
                            </span>
                        </td>
                        <td class="text-center"><?php echo $primary_trans; ?></td>
                        <td class="text-right">Mt <?php echo number_format($primary_amount, 2); ?></td>
                        <td class="text-center"><?php echo $secondary_trans; ?></td>
                        <td class="text-right">Mt <?php echo number_format($secondary_amount, 2); ?></td>
                        <td class="text-center"><strong><?php echo $total_trans; ?></strong></td>
                        <td class="text-right"><strong>Mt <?php echo number_format($total_amount, 2); ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Account Movements -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>
                <i class="fas fa-wallet"></i>
                Movimentações de Contas e Dívidas
            </h3>
        </div>
        <div class="account-movements-grid">
            <div class="movement-section">
                <h4>Saldos Adicionados</h4>
                <div class="movement-details">
                    <div class="movement-item">
                        <span>Primária:</span>
                        <span><?php echo $primary_data['balance_transactions']; ?> trans. - Mt <?php echo number_format($primary_data['balance_added'], 2); ?></span>
                    </div>
                    <div class="movement-item">
                        <span>Secundária:</span>
                        <span><?php echo $secondary_data['balance_transactions']; ?> trans. - Mt <?php echo number_format($secondary_data['balance_added'], 2); ?></span>
                    </div>
                    <div class="movement-total">
                        <span>Total:</span>
                        <span><strong><?php echo $totals['balance_transactions']; ?> trans. - Mt <?php echo number_format($totals['balance_added'], 2); ?></strong></span>
                    </div>
                </div>
            </div>
            
            <div class="movement-section">
                <h4>Dívidas Criadas</h4>
                <div class="movement-details">
                    <div class="movement-item">
                        <span>Primária:</span>
                        <span><?php echo $primary_data['debt_transactions']; ?> trans. - Mt <?php echo number_format($primary_data['debt_amount'], 2); ?></span>
                    </div>
                    <div class="movement-item">
                        <span>Secundária:</span>
                        <span><?php echo $secondary_data['debt_transactions']; ?> trans. - Mt <?php echo number_format($secondary_data['debt_amount'], 2); ?></span>
                    </div>
                    <div class="movement-total">
                        <span>Total:</span>
                        <span><strong><?php echo $totals['debt_transactions']; ?> trans. - Mt <?php echo number_format($totals['debt_amount'], 2); ?></strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Products -->
    <div class="card mb-3">
        <div class="card-header">
            <h3>
                <i class="fas fa-star"></i>
                Top 10 Produtos Mais Vendidos
            </h3>
        </div>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th class="text-center">Quantidade</th>
                        <th class="text-right">Valor Total</th>
                        <th class="text-center">Transações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($top_products)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Nenhum produto vendido hoje</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($top_products as $product): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td class="text-center"><strong><?php echo $product['total_quantity']; ?></strong></td>
                            <td class="text-right">Mt <?php echo number_format($product['total_value'], 2); ?></td>
                            <td class="text-center"><?php echo $product['transactions']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Financial Summary -->
    <div class="card">
        <div class="card-header">
            <h3 style="color: var(--success-color);">
                <i class="fas fa-chart-pie"></i>
                Resumo Financeiro Final
            </h3>
        </div>
        <div class="financial-summary">
            <div class="summary-grid">
                <div class="summary-item">
                    <div class="summary-icon">
                        <i class="fas fa-cash-register"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-value">Mt <?php echo number_format($totals['total_sales'], 2); ?></div>
                        <div class="summary-label">Total de Vendas</div>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon" style="background: var(--info-color);">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-value">Mt <?php echo number_format($totals['balance_added'], 2); ?></div>
                        <div class="summary-label">Saldos Adicionados</div>
                    </div>
                </div>
                
                <div class="summary-item">
                    <div class="summary-icon" style="background: var(--warning-color);">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-value">Mt <?php echo number_format($totals['debt_amount'], 2); ?></div>
                        <div class="summary-label">Dívidas Criadas</div>
                    </div>
                </div>
                
                <div class="summary-item total-item">
                    <div class="summary-icon" style="background: var(--primary-color);">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="summary-content">
                        <div class="summary-value">Mt <?php echo number_format($totals['total_sales'] + $totals['balance_added'], 2); ?></div>
                        <div class="summary-label">Movimento Total</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function changeDate(date) {
    window.location.href = '?date=' + date;
}

function exportReport() {
    window.location.href = 'export_closure.php?date=<?php echo $report_date; ?>&format=csv';
}

// Auto-refresh every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>

<style>
.date-header {
    text-align: center;
    margin: 2rem 0;
    padding: 1rem;
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border-radius: var(--border-radius);
}

.date-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: bold;
}

.date-header p {
    margin: 0.5rem 0 0;
    opacity: 0.9;
}

.header-actions {
    display: flex;
    gap: 1rem;
    align-items: center;
}

.cash-flow-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 1.5rem;
    margin: 1rem 0;
}

.cash-flow-section {
    padding: 1rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
}

.cash-flow-section h4 {
    margin: 0 0 1rem;
    color: var(--text-primary);
    font-weight: 600;
    border-bottom: 2px solid var(--primary-color);
    padding-bottom: 0.5rem;
}

.total-section {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
}

.total-section h4 {
    border-bottom-color: rgba(255, 255, 255, 0.3);
}

.cash-flow-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.cash-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
}

.account-movements-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2rem;
    margin: 1rem 0;
}

.movement-section {
    padding: 1rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--info-color);
}

.movement-section h4 {
    margin: 0 0 1rem;
    color: var(--info-color);
    font-weight: 600;
}

.movement-details {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}

.movement-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid var(--border-color);
}

.movement-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-top: 2px solid var(--info-color);
    margin-top: 0.5rem;
    font-weight: bold;
}

.financial-summary {
    padding: 1rem;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1.5rem;
}

.summary-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    transition: transform 0.2s ease;
}

.summary-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.total-item {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light));
    color: white;
    border: none;
}

.summary-icon {
    width: 60px;
    height: 60px;
    background: var(--success-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.summary-content {
    flex: 1;
}

.summary-value {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}

.summary-label {
    font-size: 0.9rem;
    opacity: 0.8;
    font-weight: 500;
}

/* Badge styles for payment methods */
.badge-cash { background: #28a745; color: white; }
.badge-mpesa { background: #ff6b35; color: white; }
.badge-emola { background: #007bff; color: white; }
.badge-account { background: #6f42c1; color: white; }
.badge-voucher { background: #fd7e14; color: white; }
.badge-debt { background: #dc3545; color: white; }

/* Responsive design */
@media (max-width: 768px) {
    .cash-flow-grid {
        grid-template-columns: 1fr;
    }
    
    .account-movements-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-grid {
        grid-template-columns: 1fr 1fr;
    }
    
    .header-actions {
        flex-direction: column;
        gap: 0.5rem;
    }
}

@media (max-width: 480px) {
    .summary-grid {
        grid-template-columns: 1fr;
    }
    
    .summary-item {
        flex-direction: column;
        text-align: center;
    }
}

/* Print styles */
@media print {
    .header-actions {
        display: none;
    }
    
    .card {
        break-inside: avoid;
        margin-bottom: 1rem;
    }
    
    .cash-flow-grid,
    .account-movements-grid,
    .summary-grid {
        break-inside: avoid;
    }
}
</style>

<?php include 'includes/footer.php'; ?>