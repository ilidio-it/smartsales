<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

// Check if user has permission
if (!hasPermission('manage_expenses') && $_SESSION['user_type'] !== 'admin' && $_SESSION['user_type'] !== 'manager') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$conn = $database->connect();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_expense':
                $stmt = $conn->prepare("INSERT INTO expenses (category, description, amount, expense_date, user_id) 
                                       VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['category'],
                    $_POST['description'],
                    $_POST['amount'],
                    $_POST['expense_date'],
                    $_SESSION['user_id']
                ]);
                $success_message = 'Despesa adicionada com sucesso!';
                break;
                
            case 'update_expense':
                $stmt = $conn->prepare("UPDATE expenses SET category = ?, description = ?, amount = ?, expense_date = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['category'],
                    $_POST['description'],
                    $_POST['amount'],
                    $_POST['expense_date'],
                    $_POST['expense_id']
                ]);
                $success_message = 'Despesa atualizada com sucesso!';
                break;
                
            case 'delete_expense':
                $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
                $stmt->execute([$_POST['expense_id']]);
                $success_message = 'Despesa excluída com sucesso!';
                break;
        }
    }
}

// Get expenses
$stmt = $conn->prepare("SELECT e.*, u.name as user_name FROM expenses e 
                       LEFT JOIN users u ON e.user_id = u.id 
                       ORDER BY e.expense_date DESC, e.created_at DESC");
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monthly totals
$stmt = $conn->prepare("SELECT 
                           SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as this_month,
                           SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) - 1 AND YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END) as last_month
                       FROM expenses");
$stmt->execute();
$totals = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-receipt"></i>
            Despesas
        </h1>
        <button type="button" class="btn btn-primary" data-modal="add-expense-modal">
            <i class="fas fa-plus"></i>
            Adicionar Despesa
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--error-color);">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($totals['this_month'] ?? 0, 2); ?></div>
            <div class="stat-label">Este Mês</div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon" style="color: var(--secondary-color);">
                <i class="fas fa-calendar"></i>
            </div>
            <div class="stat-value">Mt <?php echo number_format($totals['last_month'] ?? 0, 2); ?></div>
            <div class="stat-label">Mês Passado</div>
        </div>
    </div>
    
    <div class="card">
        <div class="form-group">
            <input type="text" id="expense-search" class="form-control" 
                   placeholder="Buscar despesas..." data-search-table="expenses-table">
        </div>
        
        <div class="table-container">
            <table class="table" id="expenses-table">
                <thead>
                    <tr>
                        <th data-sort="expense_date">Data</th>
                        <th data-sort="category">Categoria</th>
                        <th data-sort="description">Descrição</th>
                        <th data-sort="amount">Valor</th>
                        <th>Registrado por</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $expense): ?>
                    <tr>
                        <td data-sort="expense_date"><?php echo date('d/m/Y', strtotime($expense['expense_date'])); ?></td>
                        <td data-sort="category">
                            <span class="badge badge-<?php echo $expense['category']; ?>">
                                <?php echo ucfirst($expense['category']); ?>
                            </span>
                        </td>
                        <td data-sort="description"><?php echo htmlspecialchars($expense['description']); ?></td>
                        <td data-sort="amount">Mt <?php echo number_format($expense['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($expense['user_name']); ?></td>
                        <td>
                            <button type="button" class="btn btn-small btn-warning" 
                                    onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Excluir esta despesa?')">
                                <input type="hidden" name="action" value="delete_expense">
                                <input type="hidden" name="expense_id" value="<?php echo $expense['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Expense Modal -->
<div id="add-expense-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Adicionar Despesa</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_expense">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="category" class="form-control form-select" required>
                        <option value="rent">Renda</option>
                        <option value="salary">Salário</option>
                        <option value="utilities">Utilidades</option>
                        <option value="supplies">Suprimentos</option>
                        <option value="maintenance">Manutenção</option>
                        <option value="other">Outros</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" name="expense_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <input type="text" name="description" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Valor (Mt)</label>
                <input type="number" name="amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="edit-expense-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Editar Despesa</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_expense">
            <input type="hidden" name="expense_id" id="edit-expense-id">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="category" id="edit-expense-category" class="form-control form-select" required>
                        <option value="rent">Renda</option>
                        <option value="salary">Salário</option>
                        <option value="utilities">Utilidades</option>
                        <option value="supplies">Suprimentos</option>
                        <option value="maintenance">Manutenção</option>
                        <option value="other">Outros</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Data</label>
                    <input type="date" name="expense_date" id="edit-expense-date" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <input type="text" name="description" id="edit-expense-description" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Valor (Mt)</label>
                <input type="number" name="amount" id="edit-expense-amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<script>
function editExpense(expense) {
    document.getElementById('edit-expense-id').value = expense.id;
    document.getElementById('edit-expense-category').value = expense.category;
    document.getElementById('edit-expense-date').value = expense.expense_date;
    document.getElementById('edit-expense-description').value = expense.description;
    document.getElementById('edit-expense-amount').value = expense.amount;
    
    window.canteenApp.openModal('edit-expense-modal');
}
</script>

<style>
.badge-rent { background: #fef3c7; color: #92400e; }
.badge-salary { background: #dbeafe; color: #1e40af; }
.badge-utilities { background: #f3e8ff; color: #7c3aed; }
.badge-supplies { background: #ecfdf5; color: #059669; }
.badge-maintenance { background: #fef2f2; color: #dc2626; }
.badge-other { background: #f1f5f9; color: #475569; }
</style>

<?php include 'includes/footer.php'; ?>