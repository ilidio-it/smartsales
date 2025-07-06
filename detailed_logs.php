<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Get filters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$canteen_filter = $_GET['canteen'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';

// Build WHERE conditions
$where_conditions = ["DATE(sl.created_at) = ?"];
$params = [$date_filter];

if ($_SESSION['canteen_type'] !== 'both') {
    $where_conditions[] = "sl.canteen_type = ?";
    $params[] = $_SESSION['canteen_type'];
} elseif ($canteen_filter !== 'all') {
    $where_conditions[] = "sl.canteen_type = ?";
    $params[] = $canteen_filter;
}

if ($type_filter !== 'all') {
    $where_conditions[] = "sl.action_type = ?";
    $params[] = $type_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get system logs
$stmt = $conn->prepare("SELECT sl.*, u.name as user_name 
                       FROM system_logs sl 
                       LEFT JOIN users u ON sl.user_id = u.id 
                       WHERE $where_clause 
                       ORDER BY sl.created_at DESC");
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-list-alt"></i>
            Relatório Detalhado - Logs do Sistema
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i>
                Imprimir
            </button>
            <button type="button" class="btn btn-success" onclick="exportLogs()">
                <i class="fas fa-download"></i>
                Exportar CSV
            </button>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card">
        <div class="card-header">
            <h3>Filtros</h3>
        </div>
        
        <form method="GET" class="grid grid-4">
            <div class="form-group">
                <label class="form-label">Data</label>
                <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
            </div>
            
            <?php if ($_SESSION['canteen_type'] === 'both'): ?>
            <div class="form-group">
                <label class="form-label">Cantina</label>
                <select name="canteen" class="form-control form-select">
                    <option value="all" <?php echo $canteen_filter === 'all' ? 'selected' : ''; ?>>Todas</option>
                    <option value="primary" <?php echo $canteen_filter === 'primary' ? 'selected' : ''; ?>>Primária</option>
                    <option value="secondary" <?php echo $canteen_filter === 'secondary' ? 'selected' : ''; ?>>Secundária</option>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label class="form-label">Tipo de Ação</label>
                <select name="type" class="form-control form-select">
                    <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>Todas</option>
                    <option value="sale" <?php echo $type_filter === 'sale' ? 'selected' : ''; ?>>Vendas</option>
                    <option value="payment" <?php echo $type_filter === 'payment' ? 'selected' : ''; ?>>Pagamentos</option>
                    <option value="stock" <?php echo $type_filter === 'stock' ? 'selected' : ''; ?>>Stock</option>
                    <option value="client" <?php echo $type_filter === 'client' ? 'selected' : ''; ?>>Clientes</option>
                    <option value="product" <?php echo $type_filter === 'product' ? 'selected' : ''; ?>>Produtos</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-full">
                    <i class="fas fa-filter"></i>
                    Filtrar
                </button>
            </div>
        </form>
    </div>
    
    <!-- Logs Table -->
    <div class="card">
        <div class="card-header">
            <h3>
                Logs do Sistema - <?php echo date('d/m/Y', strtotime($date_filter)); ?>
                <span class="badge badge-info"><?php echo count($logs); ?> registros</span>
            </h3>
        </div>
        
        <div class="table-container">
            <table class="table" id="logs-table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cantina</th>
                        <th>Funcionário</th>
                        <th>Ação</th>
                        <th>Detalhes</th>
                        <th>Valor</th>
                        <th>Método</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?php echo date('H:i:s', strtotime($log['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $log['canteen_type']; ?>">
                                <?php echo ucfirst($log['canteen_type']); ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $log['action_type']; ?>">
                                <?php 
                                $action_names = [
                                    'sale' => 'Venda',
                                    'payment' => 'Pagamento',
                                    'stock' => 'Stock',
                                    'client' => 'Cliente',
                                    'product' => 'Produto'
                                ];
                                echo $action_names[$log['action_type']] ?? ucfirst($log['action_type']);
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($log['description']); ?></td>
                        <td>
                            <?php if ($log['amount']): ?>
                                Mt <?php echo number_format($log['amount'], 2); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($log['payment_method']): ?>
                                <span class="badge badge-method-<?php echo $log['payment_method']; ?>">
                                    <?php 
                                    $method_names = [
                                        'cash' => 'Dinheiro',
                                        'mpesa' => 'M-Pesa',
                                        'emola' => 'E-Mola',
                                        'account' => 'Conta',
                                        'voucher' => 'Voucher',
                                        'debt' => 'Dívida',
                                        'request' => 'Reserva'
                                    ];
                                    echo $method_names[$log['payment_method']] ?? ucfirst($log['payment_method']);
                                    ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="7" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-file-alt" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4>Nenhum log encontrado</h4>
                                <p>Não há registros para os filtros selecionados.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportLogs() {
    const date = '<?php echo $date_filter; ?>';
    const canteen = '<?php echo $canteen_filter; ?>';
    const type = '<?php echo $type_filter; ?>';
    
    window.location.href = `export_logs.php?date=${date}&canteen=${canteen}&type=${type}&format=csv`;
}
</script>

<style>
.badge-sale { background: var(--success-color); color: white; }
.badge-payment { background: var(--warning-color); color: white; }
.badge-stock { background: var(--info-color); color: white; }
.badge-client { background: var(--primary-color); color: white; }
.badge-product { background: var(--secondary-color); color: white; }

.badge-method-cash { background: #28a745; color: white; }
.badge-method-mpesa { background: #ff6b35; color: white; }
.badge-method-emola { background: #007bff; color: white; }
.badge-method-account { background: #6f42c1; color: white; }
.badge-method-voucher { background: #fd7e14; color: white; }
.badge-method-debt { background: #dc3545; color: white; }
.badge-method-request { background: #17a2b8; color: white; }

.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}
</style>

<?php include 'includes/footer.php'; ?>