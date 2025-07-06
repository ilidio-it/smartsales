<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Handle clear logs action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'clear_logs' && ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'manager')) {
        $clear_type = $_POST['clear_type'] ?? '';
        $clear_date = $_POST['clear_date'] ?? '';
        
        try {
            $conn->beginTransaction();
            
            switch ($clear_type) {
                case 'all':
                    // Clear all sales and related data
                    $conn->exec("DELETE FROM sale_items");
                    $conn->exec("DELETE FROM sales");
                    $conn->exec("DELETE FROM system_logs WHERE action_type = 'sale'");
                    $success_message = 'Todas as vendas foram eliminadas com sucesso!';
                    break;
                    
                case 'date':
                    if ($clear_date) {
                        // Clear sales from specific date
                        $stmt = $conn->prepare("DELETE si FROM sale_items si 
                                               JOIN sales s ON si.sale_id = s.id 
                                               WHERE s.sale_date = ?");
                        $stmt->execute([$clear_date]);
                        
                        $stmt = $conn->prepare("DELETE FROM sales WHERE sale_date = ?");
                        $stmt->execute([$clear_date]);
                        
                        $stmt = $conn->prepare("DELETE FROM system_logs 
                                               WHERE action_type = 'sale' AND DATE(created_at) = ?");
                        $stmt->execute([$clear_date]);
                        
                        $success_message = "Vendas do dia " . date('d/m/Y', strtotime($clear_date)) . " eliminadas com sucesso!";
                    }
                    break;
                    
                case 'month':
                    if ($clear_date) {
                        $year_month = date('Y-m', strtotime($clear_date));
                        
                        // Clear sales from specific month
                        $stmt = $conn->prepare("DELETE si FROM sale_items si 
                                               JOIN sales s ON si.sale_id = s.id 
                                               WHERE DATE_FORMAT(s.sale_date, '%Y-%m') = ?");
                        $stmt->execute([$year_month]);
                        
                        $stmt = $conn->prepare("DELETE FROM sales WHERE DATE_FORMAT(sale_date, '%Y-%m') = ?");
                        $stmt->execute([$year_month]);
                        
                        $stmt = $conn->prepare("DELETE FROM system_logs 
                                               WHERE action_type = 'sale' AND DATE_FORMAT(created_at, '%Y-%m') = ?");
                        $stmt->execute([$year_month]);
                        
                        $success_message = "Vendas do mês " . date('m/Y', strtotime($clear_date)) . " eliminadas com sucesso!";
                    }
                    break;
                    
                case 'logs_only':
                    // Clear only system logs
                    $conn->exec("DELETE FROM system_logs");
                    $success_message = 'Todos os logs do sistema foram eliminados com sucesso!';
                    break;
            }
            
            $conn->commit();
            
        } catch (PDOException $e) {
            $conn->rollback();
            $error_message = 'Erro ao eliminar dados: ' . $e->getMessage();
        }
    }
}

// Get current tab (canteen section)
$current_tab = $_GET['tab'] ?? ($_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']);

// Ensure user can only access their assigned canteen(s)
if ($_SESSION['canteen_type'] !== 'both' && $current_tab !== $_SESSION['canteen_type']) {
    $current_tab = $_SESSION['canteen_type'];
}

// Get report data based on filters
$report_type = $_GET['type'] ?? 'daily';
$from_date = $_GET['from_date'] ?? date('Y-m-d');
$to_date = $_GET['to_date'] ?? date('Y-m-d');

// Build canteen condition based on current tab
$canteen_condition = "AND canteen_type = '{$current_tab}'";

// Get statistics for tabs
$stmt = $conn->prepare("SELECT 
    SUM(CASE WHEN canteen_type = 'primary' THEN total_amount ELSE 0 END) as primary_sales,
    SUM(CASE WHEN canteen_type = 'secondary' THEN total_amount ELSE 0 END) as secondary_sales,
    COUNT(CASE WHEN canteen_type = 'primary' THEN 1 END) as primary_count,
    COUNT(CASE WHEN canteen_type = 'secondary' THEN 1 END) as secondary_count
    FROM sales 
    WHERE sale_date BETWEEN ? AND ?");
$stmt->execute([$from_date, $to_date]);
$tab_stats = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-chart-bar"></i>
            Relatórios - SmartSales
        </h1>
        <div class="d-flex gap-2">
            <?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'manager'): ?>
            <button type="button" class="btn btn-danger" data-modal="clear-logs-modal">
                <i class="fas fa-trash-alt"></i>
                Limpar Dados
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <?php if (isset($error_message)): ?>
        <div class="alert alert-error"><?php echo $error_message; ?></div>
    <?php endif; ?>
    
    <!-- Tabs for Canteen Sections -->
    <?php if ($_SESSION['canteen_type'] === 'both'): ?>
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=primary<?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] . '&from_date=' . $from_date . '&to_date=' . $to_date : ''; ?>" 
               class="tab <?php echo $current_tab === 'primary' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> 
                Cantina Primária
                <small>Mt <?php echo number_format($tab_stats['primary_sales'] ?? 0, 2); ?> (<?php echo $tab_stats['primary_count'] ?? 0; ?>)</small>
            </a>
            <a href="?tab=secondary<?php echo isset($_GET['type']) ? '&type=' . $_GET['type'] . '&from_date=' . $from_date . '&to_date=' . $to_date : ''; ?>" 
               class="tab <?php echo $current_tab === 'secondary' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> 
                Cantina Secundária
                <small>Mt <?php echo number_format($tab_stats['secondary_sales'] ?? 0, 2); ?> (<?php echo $tab_stats['secondary_count'] ?? 0; ?>)</small>
            </a>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>
                Gerar Relatório - 
                <?php 
                if ($current_tab === 'primary') {
                    echo 'Cantina Primária';
                } else {
                    echo 'Cantina Secundária';
                }
                ?>
            </h3>
        </div>
        
        <form method="GET" class="grid grid-4">
            <input type="hidden" name="tab" value="<?php echo $current_tab; ?>">
            
            <div class="form-group">
                <label class="form-label">Tipo de Relatório</label>
                <select name="type" class="form-control form-select">
                    <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Relatório Diário de Stock</option>
                    <option value="sales" <?php echo $report_type === 'sales' ? 'selected' : ''; ?>>Relatório de Vendas</option>
                    <option value="clients" <?php echo $report_type === 'clients' ? 'selected' : ''; ?>>Relatório de Clientes</option>
                    <option value="debts" <?php echo $report_type === 'debts' ? 'selected' : ''; ?>>Relatório de Dívidas</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Data Inicial</label>
                <input type="date" name="from_date" class="form-control" value="<?php echo $from_date; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">Data Final</label>
                <input type="date" name="to_date" class="form-control" value="<?php echo $to_date; ?>">
            </div>
            
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-search"></i>
                    Gerar Relatório
                </button>
            </div>
        </form>
    </div>
    
    <?php if (isset($_GET['type'])): ?>
    <div class="card">
        <div class="card-header">
            <h3>
                <?php 
                switch ($report_type) {
                    case 'daily': echo 'Relatório Diário de Stock'; break;
                    case 'sales': echo 'Relatório de Vendas'; break;
                    case 'clients': echo 'Relatório de Clientes'; break;
                    case 'debts': echo 'Relatório de Dívidas'; break;
                }
                ?>
                - <?php echo ucfirst($current_tab); ?>
                - <?php echo date('d/m/Y', strtotime($from_date)); ?>
                <?php if ($from_date !== $to_date): ?>
                    até <?php echo date('d/m/Y', strtotime($to_date)); ?>
                <?php endif; ?>
            </h3>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-secondary" onclick="window.print()">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
                <button type="button" class="btn btn-success" onclick="exportReport()">
                    <i class="fas fa-download"></i>
                    Exportar CSV
                </button>
            </div>
        </div>
        
        <?php
        switch ($report_type) {
            case 'daily':
                // Daily Stock Report - Enhanced with Sales Value and Profit for specific canteen
                $stock_field = $current_tab === 'primary' ? 'stock_primary' : 'stock_secondary';
                
                // Get all products with their stock info, sales data, and profit calculation for current canteen
                $stmt = $conn->prepare("SELECT 
                    p.name,
                    p.selling_price,
                    p.buying_price,
                    $stock_field as start_stock,
                    COALESCE(sold.total_sold, 0) as sold_quantity,
                    ($stock_field - COALESCE(sold.total_sold, 0)) as final_stock,
                    COALESCE(sold.total_sold, 0) * p.selling_price as sales_value,
                    COALESCE(sold.total_sold, 0) * (p.selling_price - p.buying_price) as profit
                FROM products p
                LEFT JOIN (
                    SELECT 
                        si.product_id,
                        SUM(si.quantity) as total_sold
                    FROM sale_items si
                    JOIN sales s ON si.sale_id = s.id
                    WHERE s.sale_date BETWEEN ? AND ? AND s.canteen_type = ?
                    GROUP BY si.product_id
                ) sold ON p.id = sold.product_id
                WHERE p.status = 'active'
                ORDER BY p.name");
                $stmt->execute([$from_date, $to_date, $current_tab]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Calculate totals
                $total_sales_value = 0;
                $total_profit = 0;
                foreach ($products as $product) {
                    $total_sales_value += $product['sales_value'];
                    $total_profit += $product['profit'];
                }
                
                // Get payment method totals for current canteen
                $stmt = $conn->prepare("SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(total_amount) as total
                FROM sales 
                WHERE sale_date BETWEEN ? AND ? AND canteen_type = ?
                GROUP BY payment_method");
                $stmt->execute([$from_date, $to_date, $current_tab]);
                $payment_methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $payment_totals = [];
                foreach ($payment_methods as $method) {
                    $payment_totals[$method['payment_method']] = $method['total'];
                }
                ?>
                
                <!-- Enhanced Payment Methods Summary with Profit for Current Canteen -->
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary-color);">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_sales_value, 2); ?></div>
                        <div class="stat-label">Total de Vendas - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                    
                    <div class="stat-card" style="border-left: 4px solid var(--success-color);">
                        <div class="stat-icon" style="color: var(--success-color);">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_profit, 2); ?></div>
                        <div class="stat-label">Lucro Total - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info-color);">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($payment_totals['mpesa'] ?? 0, 2); ?></div>
                        <div class="stat-label">M-Pesa</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--info-color);">
                            <i class="fas fa-mobile-alt"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($payment_totals['emola'] ?? 0, 2); ?></div>
                        <div class="stat-label">E-Mola</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--warning-color);">
                            <i class="fas fa-ticket-alt"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($payment_totals['voucher'] ?? 0, 2); ?></div>
                        <div class="stat-label">Voucher</div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--error-color);">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($payment_totals['debt'] ?? 0, 2); ?></div>
                        <div class="stat-label">Dívida</div>
                    </div>
                </div>
                
                <!-- Enhanced Products Stock Report with Sales Value and Profit -->
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Stock Inicial</th>
                                <th>Vendido</th>
                                <th>Stock Final</th>
                                <th>Preço Unitário</th>
                                <th class="sales-value-header">Valor Vendido</th>
                                <th class="profit-header">Lucro</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td><?php echo $product['start_stock']; ?></td>
                                <td class="sold-quantity">
                                    <?php if ($product['sold_quantity'] > 0): ?>
                                        <strong><?php echo $product['sold_quantity']; ?></strong>
                                    <?php else: ?>
                                        0
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $product['final_stock']; ?></td>
                                <td>Mt <?php echo number_format($product['selling_price'], 2); ?></td>
                                <td class="sales-value">
                                    <?php if ($product['sales_value'] > 0): ?>
                                        <strong style="color: var(--primary-color);">Mt <?php echo number_format($product['sales_value'], 2); ?></strong>
                                    <?php else: ?>
                                        Mt 0.00
                                    <?php endif; ?>
                                </td>
                                <td class="profit-value">
                                    <?php if ($product['profit'] > 0): ?>
                                        <strong style="color: var(--success-color);">Mt <?php echo number_format($product['profit'], 2); ?></strong>
                                    <?php else: ?>
                                        Mt 0.00
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="background: var(--background-color); font-weight: bold;">
                                <td colspan="5" style="text-align: right; padding-right: 1rem;">
                                    <strong>TOTAIS - <?php echo strtoupper($current_tab); ?>:</strong>
                                </td>
                                <td class="sales-value">
                                    <strong style="color: var(--primary-color); font-size: 1.1rem;">
                                        Mt <?php echo number_format($total_sales_value, 2); ?>
                                    </strong>
                                </td>
                                <td class="profit-value">
                                    <strong style="color: var(--success-color); font-size: 1.1rem;">
                                        Mt <?php echo number_format($total_profit, 2); ?>
                                    </strong>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <!-- Profit Analysis Section -->
                <div class="profit-analysis">
                    <div class="card" style="margin-top: 2rem;">
                        <div class="card-header">
                            <h4 style="color: var(--success-color);">
                                <i class="fas fa-chart-pie"></i>
                                Análise de Lucro - <?php echo ucfirst($current_tab); ?>
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="grid grid-3">
                                <div class="profit-metric">
                                    <div class="metric-icon">
                                        <i class="fas fa-percentage"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">
                                            <?php 
                                            $profit_margin = $total_sales_value > 0 ? ($total_profit / $total_sales_value) * 100 : 0;
                                            echo number_format($profit_margin, 1); 
                                            ?>%
                                        </div>
                                        <div class="metric-label">Margem de Lucro</div>
                                    </div>
                                </div>
                                
                                <div class="profit-metric">
                                    <div class="metric-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">Mt <?php echo number_format($total_profit, 2); ?></div>
                                        <div class="metric-label">Lucro Total</div>
                                    </div>
                                </div>
                                
                                <div class="profit-metric">
                                    <div class="metric-icon">
                                        <i class="fas fa-calculator"></i>
                                    </div>
                                    <div class="metric-content">
                                        <div class="metric-value">Mt <?php echo number_format($total_sales_value - $total_profit, 2); ?></div>
                                        <div class="metric-label">Custo Total</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php
                break;
                
            case 'sales':
                // Sales Report for current canteen
                $stmt = $conn->prepare("SELECT s.*, c.name as client_name, u.name as user_name 
                                       FROM sales s 
                                       LEFT JOIN clients c ON s.client_id = c.id 
                                       LEFT JOIN users u ON s.user_id = u.id 
                                       WHERE s.sale_date BETWEEN ? AND ? AND s.canteen_type = ?
                                       ORDER BY s.created_at DESC");
                $stmt->execute([$from_date, $to_date, $current_tab]);
                $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $total_sales = array_sum(array_column($sales, 'total_amount'));
                $total_count = count($sales);
                
                // Group by payment method
                $payment_methods = [];
                foreach ($sales as $sale) {
                    $method = $sale['payment_method'];
                    if (!isset($payment_methods[$method])) {
                        $payment_methods[$method] = ['count' => 0, 'total' => 0];
                    }
                    $payment_methods[$method]['count']++;
                    $payment_methods[$method]['total'] += $sale['total_amount'];
                }
                ?>
                
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--primary-color);">
                            <i class="fas fa-cash-register"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_sales, 2); ?></div>
                        <div class="stat-label">Total de Vendas - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success-color);">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_count; ?></div>
                        <div class="stat-label">Total de Transações</div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Hora</th>
                                <th>Cliente</th>
                                <th>Funcionário</th>
                                <th>Método</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo date('d/m/Y', strtotime($sale['sale_date'])); ?></td>
                                <td><?php echo date('H:i', strtotime($sale['sale_time'])); ?></td>
                                <td><?php echo $sale['client_name'] ?? 'Cliente à Vista'; ?></td>
                                <td><?php echo $sale['user_name']; ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $sale['payment_method']; ?>">
                                        <?php 
                                        $method_names = [
                                            'cash' => 'Dinheiro',
                                            'mpesa' => 'M-Pesa',
                                            'emola' => 'E-Mola',
                                            'account' => 'Conta',
                                            'voucher' => 'Vale',
                                            'debt' => 'Dívida',
                                            'request' => 'Reserva'
                                        ];
                                        echo $method_names[$sale['payment_method']] ?? ucfirst($sale['payment_method']);
                                        ?>
                                    </span>
                                </td>
                                <td>Mt <?php echo number_format($sale['total_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
                break;
                
            case 'clients':
                // Clients Report filtered by canteen type
                $canteen_client_condition = '';
                if ($current_tab === 'primary') {
                    $canteen_client_condition = " AND (canteen_type = 'primary' OR type LIKE '%primary%')";
                } else {
                    $canteen_client_condition = " AND (canteen_type = 'secondary' OR type LIKE '%secondary%')";
                }
                
                $stmt = $conn->prepare("SELECT * FROM clients WHERE status = 'active' $canteen_client_condition ORDER BY name");
                $stmt->execute();
                $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $total_balance = array_sum(array_column($clients, 'balance'));
                $total_debt = array_sum(array_column($clients, 'debt'));
                ?>
                
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--success-color);">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_balance, 2); ?></div>
                        <div class="stat-label">Total Saldos - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--error-color);">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_debt, 2); ?></div>
                        <div class="stat-label">Total Dívidas - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Contacto</th>
                                <th>Saldo Atual</th>
                                <th>Dívida Atual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($clients as $client): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($client['name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $client['type']; ?>">
                                        <?php 
                                        $type_names = [
                                            'primary' => 'Aluno Primária',
                                            'secondary' => 'Aluno Secundária',
                                            'teacher_primary' => 'Professor Primária',
                                            'teacher_secondary' => 'Professor Secundária',
                                            'reservation' => 'Reserva'
                                        ];
                                        echo $type_names[$client['type']] ?? ucfirst($client['type']); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($client['contact'] ?? ''); ?></td>
                                <td>
                                    <span class="<?php echo $client['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                        Mt <?php echo number_format($client['balance'], 2); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $client['debt'] > 0 ? 'text-danger' : ''; ?>">
                                        Mt <?php echo number_format($client['debt'], 2); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
                break;
                
            case 'debts':
                // Debts Report filtered by canteen type
                $canteen_debt_condition = '';
                if ($current_tab === 'primary') {
                    $canteen_debt_condition = " AND (canteen_type = 'primary' OR type LIKE '%primary%')";
                } else {
                    $canteen_debt_condition = " AND (canteen_type = 'secondary' OR type LIKE '%secondary%')";
                }
                
                $stmt = $conn->prepare("SELECT * FROM clients WHERE debt > 0 AND status = 'active' $canteen_debt_condition ORDER BY debt DESC");
                $stmt->execute();
                $debtors = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $total_debt = array_sum(array_column($debtors, 'debt'));
                ?>
                
                <div class="stats-grid mb-3">
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--error-color);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="stat-value"><?php echo count($debtors); ?></div>
                        <div class="stat-label">Clientes com Dívida - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon" style="color: var(--error-color);">
                            <i class="fas fa-hand-holding-usd"></i>
                        </div>
                        <div class="stat-value">Mt <?php echo number_format($total_debt, 2); ?></div>
                        <div class="stat-label">Total a Receber - <?php echo ucfirst($current_tab); ?></div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Tipo</th>
                                <th>Contacto</th>
                                <th>Dívida Atual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debtors as $debtor): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($debtor['name']); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo str_replace('_', '-', $debtor['type']); ?>">
                                        <?php 
                                        $type_names = [
                                            'primary' => 'Aluno Primária',
                                            'secondary' => 'Aluno Secundária',
                                            'teacher_primary' => 'Professor Primária',
                                            'teacher_secondary' => 'Professor Secundária',
                                            'reservation' => 'Reserva'
                                        ];
                                        echo $type_names[$debtor['type']] ?? ucfirst($debtor['type']); 
                                        ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($debtor['contact'] ?? ''); ?></td>
                                <td>
                                    <span class="text-danger">
                                        Mt <?php echo number_format($debtor['debt'], 2); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php
                break;
        }
        ?>
    </div>
    <?php endif; ?>
</div>

<!-- Clear Logs Modal -->
<?php if ($_SESSION['user_type'] === 'admin' || $_SESSION['user_type'] === 'manager'): ?>
<div id="clear-logs-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3 style="color: var(--error-color);">
                <i class="fas fa-exclamation-triangle"></i>
                Limpar Dados do Sistema
            </h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <div class="modal-body">
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>ATENÇÃO:</strong> Esta ação é irreversível! Os dados eliminados não podem ser recuperados.
            </div>
            
            <form method="POST" onsubmit="return confirmClearLogs()">
                <input type="hidden" name="action" value="clear_logs">
                
                <div class="form-group">
                    <label class="form-label">Tipo de Limpeza</label>
                    <select name="clear_type" id="clear-type-select" class="form-control form-select" required>
                        <option value="">Selecione o tipo...</option>
                        <option value="logs_only">Apenas Logs do Sistema</option>
                        <option value="date">Vendas de um Dia Específico</option>
                        <option value="month">Vendas de um Mês Específico</option>
                        <option value="all">TODAS as Vendas e Logs</option>
                    </select>
                </div>
                
                <div class="form-group" id="date-selection" style="display: none;">
                    <label class="form-label">Data</label>
                    <input type="date" name="clear_date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="clear-info" id="clear-info" style="display: none;">
                    <!-- Info will be populated by JavaScript -->
                </div>
                
                <div class="d-flex gap-2 justify-end">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i>
                        Confirmar Limpeza
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function exportReport() {
    const reportType = '<?php echo $report_type; ?>';
    const fromDate = '<?php echo $from_date; ?>';
    const toDate = '<?php echo $to_date; ?>';
    const canteenType = '<?php echo $current_tab; ?>';
    
    window.location.href = `export.php?type=${reportType}&from_date=${fromDate}&to_date=${toDate}&canteen=${canteenType}&format=csv`;
}

// Clear logs functionality
document.getElementById('clear-type-select')?.addEventListener('change', function() {
    const clearType = this.value;
    const dateSelection = document.getElementById('date-selection');
    const clearInfo = document.getElementById('clear-info');
    
    if (clearType === 'date' || clearType === 'month') {
        dateSelection.style.display = 'block';
    } else {
        dateSelection.style.display = 'none';
    }
    
    // Show info about what will be cleared
    let infoText = '';
    switch (clearType) {
        case 'logs_only':
            infoText = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Serão eliminados apenas os logs do sistema. As vendas permanecerão intactas.</div>';
            break;
        case 'date':
            infoText = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Serão eliminadas todas as vendas e logs da data selecionada.</div>';
            break;
        case 'month':
            infoText = '<div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> Serão eliminadas todas as vendas e logs do mês selecionado.</div>';
            break;
        case 'all':
            infoText = '<div class="alert alert-error"><i class="fas fa-skull-crossbones"></i> <strong>PERIGO:</strong> Serão eliminadas TODAS as vendas e logs do sistema!</div>';
            break;
    }
    
    if (infoText) {
        clearInfo.innerHTML = infoText;
        clearInfo.style.display = 'block';
    } else {
        clearInfo.style.display = 'none';
    }
});

function confirmClearLogs() {
    const clearType = document.getElementById('clear-type-select').value;
    let confirmMessage = '';
    
    switch (clearType) {
        case 'logs_only':
            confirmMessage = 'Confirma que deseja eliminar todos os logs do sistema?';
            break;
        case 'date':
            const selectedDate = document.querySelector('[name="clear_date"]').value;
            confirmMessage = `Confirma que deseja eliminar todas as vendas do dia ${selectedDate}?`;
            break;
        case 'month':
            const selectedMonth = document.querySelector('[name="clear_date"]').value;
            const monthYear = new Date(selectedMonth).toLocaleDateString('pt-PT', { month: 'long', year: 'numeric' });
            confirmMessage = `Confirma que deseja eliminar todas as vendas de ${monthYear}?`;
            break;
        case 'all':
            confirmMessage = 'ATENÇÃO: Confirma que deseja eliminar TODAS as vendas e logs do sistema? Esta ação é IRREVERSÍVEL!';
            break;
    }
    
    return confirm(confirmMessage);
}
</script>

<style>
.badge-cash { background: #28a745; color: white; }
.badge-mpesa { background: #ff6b35; color: white; }
.badge-emola { background: #007bff; color: white; }
.badge-account { background: #6f42c1; color: white; }
.badge-voucher { background: #fd7e14; color: white; }
.badge-debt { background: #dc3545; color: white; }
.badge-request { background: #17a2b8; color: white; }

.badge-primary { background: #007bff; color: white; }
.badge-secondary { background: #6c757d; color: white; }
.badge-teacher-primary { background: #28a745; color: white; }
.badge-teacher-secondary { background: #17a2b8; color: white; }

/* Enhanced styling for sales value and profit columns */
.sales-value-header,
.profit-header {
    background: linear-gradient(135deg, var(--primary-color), var(--primary-light)) !important;
    color: white !important;
    text-align: center !important;
    font-weight: bold !important;
    position: sticky;
    top: 0;
    z-index: 10;
}

.profit-header {
    background: linear-gradient(135deg, var(--success-color), #4caf50) !important;
}

.sales-value,
.profit-value {
    text-align: center !important;
    font-weight: 600 !important;
    padding: 0.75rem !important;
}

.sold-quantity {
    text-align: center !important;
    font-weight: 600 !important;
}

/* Profit Analysis Styling */
.profit-analysis {
    margin-top: 2rem;
}

.profit-metric {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.5rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--success-color);
}

.metric-icon {
    width: 60px;
    height: 60px;
    background: var(--success-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.metric-content {
    flex: 1;
}

.metric-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--success-color);
    margin-bottom: 0.25rem;
}

.metric-label {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

/* Table footer styling */
tfoot tr {
    border-top: 3px solid var(--primary-color);
}

tfoot td {
    padding: 1rem !important;
    font-size: 1.1rem;
}

/* Tab styling enhancements */
.tabs {
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 2rem;
}

.tab {
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius) var(--border-radius) 0 0;
    text-decoration: none;
    color: var(--text-secondary);
    font-weight: 500;
    transition: var(--transition);
    border: 2px solid transparent;
    border-bottom: none;
    background: var(--card-background);
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    min-width: 200px;
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
    font-size: 1.25rem;
}

.tab small {
    font-size: 0.75rem;
    opacity: 0.9;
    text-align: center;
}

/* Clear logs modal styling */
.clear-info {
    margin: 1rem 0;
}

.alert-error {
    background: rgba(244, 67, 54, 0.1);
    color: var(--error-color);
    border: 1px solid rgba(244, 67, 54, 0.2);
    padding: 1rem;
    border-radius: var(--border-radius);
    margin: 1rem 0;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .grid-3 {
        grid-template-columns: 1fr;
    }
    
    .profit-metric {
        flex-direction: column;
        text-align: center;
    }
    
    .metric-icon {
        margin-bottom: 0.5rem;
    }
    
    .tabs {
        flex-direction: column;
    }
    
    .tab {
        min-width: auto;
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>