
<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$canteen_filter = $_GET['canteen'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

// Build query conditions
$conditions = ["DATE(dd.debt_date) = ?"];
$params = [$date_filter];

// Apply canteen filter
if ($canteen_filter !== 'all') {
    $conditions[] = "dd.canteen_type = ?";
    $params[] = $canteen_filter;
}

// Apply status filter
if ($status_filter !== 'all') {
    $conditions[] = "dd.status = ?";
    $params[] = $status_filter;
}

// Get canteen condition based on user's access
if ($_SESSION['canteen_type'] !== 'both') {
    $conditions[] = "dd.canteen_type = ?";
    $params[] = $_SESSION['canteen_type'];
}

// Build the final query
$where_clause = implode(' AND ', $conditions);
$query = "SELECT dd.*, c.name as client_name, c.type as client_type, u.name as user_name
          FROM daily_debts dd
          LEFT JOIN clients c ON dd.client_id = c.id
          LEFT JOIN users u ON dd.user_id = u.id
          WHERE $where_clause
          ORDER BY dd.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$daily_debts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $conn->prepare("SELECT 
                          COUNT(*) as total_debts,
                          SUM(total_amount) as total_amount,
                          SUM(CASE WHEN status = 'pending' THEN total_amount ELSE 0 END) as pending_amount,
                          SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END) as paid_amount
                        FROM daily_debts dd
                        WHERE $where_clause");
$stmt->execute($params);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-calendar-alt"></i>
            Dívidas Diárias
        </h1>
        <div class="header-actions">
            <span class="date-display"><?php echo date('d/m/Y', strtotime($date_filter)); ?></span>
        </div>
    </div>
    
    <!-- Filtros -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter"></i>
                Filtros
            </h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <div class="grid grid-4">
                    <div class="form-group">
                        <label class="form-label">Data</label>
                        <input type="date" name="date" class="form-control" value="<?php echo $date_filter; ?>">
                    </div>
                    
                    <?php if ($_SESSION['canteen_type'] === 'both'): ?>
                    <div class="form-group">
                        <label class="form-label">Cantina</label>
                        <select name="canteen" class="form-control form-select">
                            <option value="all">Todas as Cantinas</option>
                            <option value="primary" <?php echo $canteen_filter == 'primary' ? 'selected' : ''; ?>>Primária</option>
                            <option value="secondary" <?php echo $canteen_filter == 'secondary' ? 'selected' : ''; ?>>Secundária</option>
                        </select>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control form-select">
                            <option value="all">Todos os Status</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pendentes</option>
                            <option value="paid" <?php echo $status_filter == 'paid' ? 'selected' : ''; ?>>Pagas</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Canceladas</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="display: flex; align-items: end;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                            Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resumo -->
    <div class="stats-grid mb-3">
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-list"></i>
            </div>
            <div class="stat-value"><?php echo $summary['total_debts']; ?></div>
            <div class="stat-label">Total de Dívidas</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($summary['total_amount'], 2); ?></div>
            <div class="stat-label">Valor Total</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($summary['pending_amount'], 2); ?></div>
            <div class="stat-label">Pendentes</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($summary['paid_amount'], 2); ?></div>
            <div class="stat-label">Pagas</div>
        </div>
    </div>
    
    <!-- Lista de Dívidas -->
    <div class="card">
        <div class="card-header">
            <h3>Dívidas do Dia</h3>
            <div class="card-header-actions">
                <button type="button" class="btn btn-secondary btn-small" onclick="printDebts()">
                    <i class="fas fa-print"></i>
                    Imprimir
                </button>
                <button type="button" class="btn btn-success btn-small" onclick="exportDebts()">
                    <i class="fas fa-file-excel"></i>
                    Exportar
                </button>
            </div>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Tipo</th>
                        <th>Cantina</th>
                        <th>Valor</th>
                        <th>Status</th>
                        <th>Funcionário</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($daily_debts)): ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-calendar-check" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4>Nenhuma dívida registrada</h4>
                                <p>Não há dívidas para o período selecionado.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($daily_debts as $debt): ?>
                    <tr>
                        <td><?php echo date('H:i', strtotime($debt['created_at'])); ?></td>
                        <td><?php echo htmlspecialchars($debt['client_name']); ?></td>
                        <td>
                            <span class="badge badge-<?php echo str_replace('_', '-', $debt['client_type']); ?>">
                                <?php 
                                $type_names = [
                                    'primary' => 'Aluno Primária',
                                    'secondary' => 'Aluno Secundária',
                                    'teacher_primary' => 'Professor Primária',
                                    'teacher_secondary' => 'Professor Secundária'
                                ];
                                echo $type_names[$debt['client_type']] ?? ucfirst($debt['client_type']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $debt['canteen_type']; ?>">
                                <?php echo $debt['canteen_type'] === 'primary' ? 'Primária' : 'Secundária'; ?>
                            </span>
                        </td>
                        <td>Mt <?php echo number_format($debt['total_amount'], 2); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $debt['status']; ?>">
                                <?php 
                                $status_names = [
                                    'pending' => 'Pendente',
                                    'paid' => 'Pago',
                                    'cancelled' => 'Cancelado'
                                ];
                                echo $status_names[$debt['status']] ?? ucfirst($debt['status']);
                                ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($debt['user_name']); ?></td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="action-btn action-btn-edit" 
                                        onclick="viewDebtDetails(<?php echo $debt['id']; ?>)"
                                        title="Ver Detalhes">
                                    <i class="fas fa-eye"></i>
                                </button>
                                
                                <?php if ($debt['status'] === 'pending'): ?>
                                <button type="button" class="action-btn action-btn-clear" 
                                        onclick="markAsPaid(<?php echo $debt['id']; ?>)"
                                        title="Marcar como Pago">
                                    <i class="fas fa-check"></i>
                                </button>
                                
                                <button type="button" class="action-btn action-btn-delete" 
                                        onclick="cancelDebt(<?php echo $debt['id']; ?>)"
                                        title="Cancelar Dívida">
                                    <i class="fas fa-times"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function viewDebtDetails(debtId) {
    // Implementar modal com detalhes da dívida
    console.log('Ver detalhes da dívida:', debtId);
}

function markAsPaid(debtId) {
    if (confirm('Marcar esta dívida como paga?')) {
        // Implementar marcação como pago
        console.log('Marcar como pago:', debtId);
    }
}

function cancelDebt(debtId) {
    if (confirm('Cancelar esta dívida? Esta ação não pode ser desfeita.')) {
        // Implementar cancelamento
        console.log('Cancelar dívida:', debtId);
    }
}

function printDebts() {
    window.print();
}

function exportDebts() {
    // Implementar exportação
    console.log('Exportar dívidas');
}
</script>

<style>
.badge-pending {
    background: #ffc107;
    color: #000;
}

.badge-paid {
    background: #28a745;
    color: white;
}

.badge-cancelled {
    background: #6c757d;
    color: white;
}

.date-display {
    background: var(--primary-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: var(--border-radius);
    font-weight: 600;
}
</style>

<?php include 'includes/footer.php'; ?>
