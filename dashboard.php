<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

// Redirect employees to sales page instead of dashboard
if ($_SESSION['user_type'] === 'employee') {
    header("Location: sales.php");
    exit();
}

$database = new Database();
$conn = $database->connect();

// Get dashboard statistics
$canteen_condition = $_SESSION['canteen_type'] === 'both' ? '' : "AND canteen_type = '{$_SESSION['canteen_type']}'";

// Today's sales - Separado por cantina
$stmt = $conn->prepare("SELECT 
                          canteen_type,
                          COUNT(*) as count, 
                          COALESCE(SUM(total_amount), 0) as total 
                       FROM sales 
                       WHERE DATE(sale_date) = CURDATE() 
                       GROUP BY canteen_type");
$stmt->execute();
$today_sales_by_canteen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar os resultados para formato mais fácil de usar
$today_sales = [
    'primary' => ['count' => 0, 'total' => 0],
    'secondary' => ['count' => 0, 'total' => 0],
    'total' => ['count' => 0, 'total' => 0]
];

foreach ($today_sales_by_canteen as $sale) {
    $canteen = $sale['canteen_type'];
    $today_sales[$canteen] = [
        'count' => $sale['count'],
        'total' => $sale['total']
    ];
    $today_sales['total']['count'] += $sale['count'];
    $today_sales['total']['total'] += $sale['total'];
}

// This month's sales - Separado por cantina
$stmt = $conn->prepare("SELECT 
                          canteen_type,
                          COUNT(*) as count, 
                          COALESCE(SUM(total_amount), 0) as total 
                       FROM sales 
                       WHERE MONTH(sale_date) = MONTH(CURDATE()) 
                       AND YEAR(sale_date) = YEAR(CURDATE()) 
                       GROUP BY canteen_type");
$stmt->execute();
$month_sales_by_canteen = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Processar os resultados para formato mais fácil de usar
$month_sales = [
    'primary' => ['count' => 0, 'total' => 0],
    'secondary' => ['count' => 0, 'total' => 0],
    'total' => ['count' => 0, 'total' => 0]
];

foreach ($month_sales_by_canteen as $sale) {
    $canteen = $sale['canteen_type'];
    $month_sales[$canteen] = [
        'count' => $sale['count'],
        'total' => $sale['total']
    ];
    $month_sales['total']['count'] += $sale['count'];
    $month_sales['total']['total'] += $sale['total'];
}

// Low stock products
$low_stock_condition = $_SESSION['canteen_type'] === 'primary' ? 'stock_primary <= min_stock' : 
                      ($_SESSION['canteen_type'] === 'secondary' ? 'stock_secondary <= min_stock' : 
                       '(stock_primary <= min_stock OR stock_secondary <= min_stock)');

$stmt = $conn->prepare("SELECT COUNT(*) as count FROM products WHERE {$low_stock_condition} AND status = 'active'");
$stmt->execute();
$low_stock = $stmt->fetch(PDO::FETCH_ASSOC);

// Total clients with debt
$stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(debt), 0) as total FROM clients WHERE debt > 0");
$stmt->execute();
$debts = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent sales
$stmt = $conn->prepare("SELECT s.*, c.name as client_name 
                       FROM sales s 
                       LEFT JOIN clients c ON s.client_id = c.id 
                       WHERE 1=1 {$canteen_condition} 
                       ORDER BY s.created_at DESC 
                       LIMIT 10");
$stmt->execute();
$recent_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current tab (canteen section)
$current_tab = $_GET['tab'] ?? ($_SESSION['canteen_type'] === 'both' ? 'all' : $_SESSION['canteen_type']);

// Ensure user can only access their assigned canteen(s)
if ($_SESSION['canteen_type'] !== 'both' && $current_tab !== $_SESSION['canteen_type'] && $current_tab !== 'all') {
    $current_tab = $_SESSION['canteen_type'];
}

include 'includes/header.php';
?>

<div class="container">
    <?php if ($_SESSION['canteen_type'] === 'both'): ?>
    <!-- Tabs for Canteen Sections -->
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=all" class="tab <?php echo $current_tab === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-store"></i> 
                Todas as Cantinas
                <small>Visão Geral</small>
            </a>
            <a href="?tab=primary" class="tab <?php echo $current_tab === 'primary' ? 'active' : ''; ?>">
                <i class="fas fa-graduation-cap"></i> 
                Cantina Primária
                <small>Mt <?php echo number_format($today_sales['primary']['total'] ?? 0, 2); ?> (<?php echo $today_sales['primary']['count'] ?? 0; ?>)</small>
            </a>
            <a href="?tab=secondary" class="tab <?php echo $current_tab === 'secondary' ? 'active' : ''; ?>">
                <i class="fas fa-user-graduate"></i> 
                Cantina Secundária
                <small>Mt <?php echo number_format($today_sales['secondary']['total'] ?? 0, 2); ?> (<?php echo $today_sales['secondary']['count'] ?? 0; ?>)</small>
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Dashboard Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-cash-register"></i>
            </div>
            <div class="stat-value">
                Mt <?php 
                    if ($current_tab === 'all') {
                        echo number_format($today_sales['total']['total'], 2);
                    } elseif ($current_tab === 'primary') {
                        echo number_format($today_sales['primary']['total'] ?? 0, 2);
                    } else {
                        echo number_format($today_sales['secondary']['total'] ?? 0, 2);
                    }
                ?>
            </div>
            <div class="stat-label">
                <?php echo __('today_sales'); ?> 
                (<?php 
                    if ($current_tab === 'all') {
                        echo $today_sales['total']['count'];
                    } elseif ($current_tab === 'primary') {
                        echo $today_sales['primary']['count'] ?? 0;
                    } else {
                        echo $today_sales['secondary']['count'] ?? 0;
                    }
                ?>)
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="stat-value">
                Mt <?php 
                    if ($current_tab === 'all') {
                        echo number_format($month_sales['total']['total'], 2);
                    } elseif ($current_tab === 'primary') {
                        echo number_format($month_sales['primary']['total'] ?? 0, 2);
                    } else {
                        echo number_format($month_sales['secondary']['total'] ?? 0, 2);
                    }
                ?>
            </div>
            <div class="stat-label">
                <?php echo __('month_sales'); ?> 
                (<?php 
                    if ($current_tab === 'all') {
                        echo $month_sales['total']['count'];
                    } elseif ($current_tab === 'primary') {
                        echo $month_sales['primary']['count'] ?? 0;
                    } else {
                        echo $month_sales['secondary']['count'] ?? 0;
                    }
                ?>)
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="stat-value"><?php echo $low_stock['count']; ?></div>
            <div class="stat-label"><?php echo __('low_stock_products'); ?></div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-hand-holding-usd"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($debts['total'], 2); ?></div>
            <div class="stat-label"><?php echo __('total_debts'); ?> (<?php echo $debts['count']; ?>)</div>
        </div>
    </div>

    <div class="grid grid-2">
        <!-- Recent Sales -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i>
                    <?php echo __('recent_sales'); ?>
                    <?php if ($current_tab !== 'all'): ?>
                        - <?php echo ucfirst($current_tab); ?>
                    <?php endif; ?>
                </h3>
            </div>
            
            <div class="card-body">
                <div class="table-container">
                    <table class="table">
                        <thead>
                            <tr>
                                <th><?php echo __('time'); ?></th>
                                <th><?php echo __('client'); ?></th>
                                <th><?php echo __('payment_method'); ?></th>
                                <th><?php echo __('total'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td><?php echo date('H:i', strtotime($sale['created_at'])); ?></td>
                                <td><?php echo $sale['client_name'] ?? __('cash_customer'); ?></td>
                                <td>
                                    <span class="badge badge-<?php echo $sale['payment_method']; ?>">
                                        <?php echo __($sale['payment_method']); ?>
                                    </span>
                                </td>
                                <td>Mt <?php echo number_format($sale['total_amount'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($recent_sales)): ?>
                            <tr>
                                <td colspan="4" class="text-center">Nenhuma venda recente</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-rocket"></i>
                    <?php echo __('quick_actions'); ?>
                </h3>
            </div>
            
            <div class="card-body">
                <div class="grid grid-2">
                    <?php if ($current_tab === 'all' || $current_tab === 'primary'): ?>
                    <a href="sales.php?canteen=primary" class="btn btn-primary btn-large">
                        <i class="fas fa-cash-register"></i>
                        Venda Primária
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($current_tab === 'all' || $current_tab === 'secondary'): ?>
                    <a href="sales.php?canteen=secondary" class="btn btn-success btn-large">
                        <i class="fas fa-cash-register"></i>
                        Venda Secundária
                    </a>
                    <?php endif; ?>
                    
                    <a href="products.php" class="btn btn-info btn-large">
                        <i class="fas fa-plus"></i>
                        <?php echo __('add_product'); ?>
                    </a>
                    
                    <a href="clients.php" class="btn btn-warning btn-large">
                        <i class="fas fa-user-plus"></i>
                        <?php echo __('add_client'); ?>
                    </a>
                    
                    <a href="reports.php" class="btn btn-secondary btn-large">
                        <i class="fas fa-chart-bar"></i>
                        <?php echo __('view_reports'); ?>
                    </a>
                    
                    <a href="daily_sales_monitor.php" class="btn btn-danger btn-large">
                        <i class="fas fa-hamburger"></i>
                        Monitor de Vendas
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

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
</style>

<?php include 'includes/footer.php'; ?>