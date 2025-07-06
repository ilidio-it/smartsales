<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

// Check if user has permission to manage employees
if (!hasPermission('manage_employees') && $_SESSION['user_type'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$database = new Database();
$conn = $database->connect();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_employee':
                $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $permissions = json_encode([
                    'manage_sales' => isset($_POST['permissions']['manage_sales']),
                    'manage_products' => isset($_POST['permissions']['manage_products']),
                    'manage_clients' => isset($_POST['permissions']['manage_clients']),
                    'view_reports' => isset($_POST['permissions']['view_reports']),
                    'manage_employees' => isset($_POST['permissions']['manage_employees'])
                ]);
                
                $stmt = $conn->prepare("INSERT INTO users (username, password, name, user_type, canteen_type, permissions) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['username'],
                    $password_hash,
                    $_POST['name'],
                    $_POST['user_type'],
                    $_POST['canteen_type'],
                    $permissions
                ]);
                $success_message = __('employee_added_successfully');
                break;
                
            case 'update_employee':
                $permissions = json_encode([
                    'manage_sales' => isset($_POST['permissions']['manage_sales']),
                    'manage_products' => isset($_POST['permissions']['manage_products']),
                    'manage_clients' => isset($_POST['permissions']['manage_clients']),
                    'view_reports' => isset($_POST['permissions']['view_reports']),
                    'manage_employees' => isset($_POST['permissions']['manage_employees'])
                ]);
                
                $stmt = $conn->prepare("UPDATE users SET name = ?, user_type = ?, canteen_type = ?, permissions = ?, status = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['user_type'],
                    $_POST['canteen_type'],
                    $permissions,
                    $_POST['status'],
                    $_POST['employee_id']
                ]);
                $success_message = __('employee_updated_successfully');
                break;
                
            case 'change_password':
                $password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$password_hash, $_POST['employee_id']]);
                $success_message = __('password_changed_successfully');
                break;
                
            case 'delete_employee':
                $stmt = $conn->prepare("UPDATE users SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['employee_id']]);
                $success_message = __('employee_deleted_successfully');
                break;
        }
    }
}

// Get employees
$stmt = $conn->prepare("SELECT * FROM users WHERE status = 'active' ORDER BY name");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-user-tie"></i>
            <?php echo __('employees'); ?>
        </h1>
        <button type="button" class="btn btn-primary" data-modal="add-employee-modal">
            <i class="fas fa-plus"></i>
            <?php echo __('add_employee'); ?>
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="form-group">
            <input type="text" id="employee-search" class="form-control" 
                   placeholder="<?php echo __('search'); ?> <?php echo __('employees'); ?>..." data-search-table="employees-table">
        </div>
        
        <div class="table-container">
            <table class="table" id="employees-table">
                <thead>
                    <tr>
                        <th data-sort="name"><?php echo __('name'); ?></th>
                        <th data-sort="username">Username</th>
                        <th data-sort="user_type"><?php echo __('user_type'); ?></th>
                        <th data-sort="canteen_type"><?php echo __('associated_canteen'); ?></th>
                        <th data-sort="status"><?php echo __('status'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td data-sort="name"><?php echo htmlspecialchars($employee['name']); ?></td>
                        <td data-sort="username"><?php echo htmlspecialchars($employee['username']); ?></td>
                        <td data-sort="user_type">
                            <span class="badge badge-<?php echo $employee['user_type']; ?>">
                                <?php echo __($employee['user_type']); ?>
                            </span>
                        </td>
                        <td data-sort="canteen_type">
                            <span class="canteen-badge canteen-<?php echo $employee['canteen_type']; ?>">
                                <?php echo __($employee['canteen_type']); ?>
                            </span>
                        </td>
                        <td data-sort="status">
                            <span class="badge <?php echo $employee['status'] === 'active' ? 'badge-success' : 'badge-danger'; ?>">
                                <?php echo __($employee['status']); ?>
                            </span>
                        </td>
                        <td>
                            <button type="button" class="btn btn-small btn-warning" 
                                    onclick="editEmployee(<?php echo htmlspecialchars(json_encode($employee)); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            
                            <button type="button" class="btn btn-small btn-secondary" 
                                    onclick="changePassword(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['name']); ?>')">
                                <i class="fas fa-key"></i>
                            </button>
                            
                            <?php if ($employee['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('<?php echo __('confirm_delete'); ?>')">
                                <input type="hidden" name="action" value="delete_employee">
                                <input type="hidden" name="employee_id" value="<?php echo $employee['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Employee Modal -->
<div id="add-employee-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo __('add_employee'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_employee">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('employee_name'); ?></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('username'); ?></label>
                    <input type="text" name="username" class="form-control" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('password'); ?></label>
                <input type="password" name="password" class="form-control" required>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('user_type'); ?></label>
                    <select name="user_type" class="form-control form-select" required>
                        <option value="employee"><?php echo __('employee'); ?></option>
                        <option value="manager"><?php echo __('manager'); ?></option>
                        <option value="admin"><?php echo __('admin'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('associated_canteen'); ?></label>
                    <select name="canteen_type" class="form-control form-select" required>
                        <option value="primary"><?php echo __('primary'); ?></option>
                        <option value="secondary"><?php echo __('secondary'); ?></option>
                        <option value="both">Ambas</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('permissions'); ?></label>
                <div class="grid grid-2">
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_sales]" value="1" checked>
                        <span>Gerenciar Vendas</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_products]" value="1">
                        <span>Gerenciar Produtos</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_clients]" value="1">
                        <span>Gerenciar Clientes</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[view_reports]" value="1">
                        <span>Ver Relatórios</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_employees]" value="1">
                        <span>Gerenciar Funcionários</span>
                    </label>
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Employee Modal -->
<div id="edit-employee-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo __('edit_employee'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="update_employee">
            <input type="hidden" name="employee_id" id="edit-employee-id">
            
            <div class="form-group">
                <label class="form-label"><?php echo __('employee_name'); ?></label>
                <input type="text" name="name" id="edit-employee-name" class="form-control" required>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('user_type'); ?></label>
                    <select name="user_type" id="edit-user-type" class="form-control form-select" required>
                        <option value="employee"><?php echo __('employee'); ?></option>
                        <option value="manager"><?php echo __('manager'); ?></option>
                        <option value="admin"><?php echo __('admin'); ?></option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('associated_canteen'); ?></label>
                    <select name="canteen_type" id="edit-canteen-type" class="form-control form-select" required>
                        <option value="primary"><?php echo __('primary'); ?></option>
                        <option value="secondary"><?php echo __('secondary'); ?></option>
                        <option value="both">Ambas</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('account_status'); ?></label>
                <select name="status" id="edit-status" class="form-control form-select" required>
                    <option value="active"><?php echo __('active'); ?></option>
                    <option value="inactive"><?php echo __('inactive'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('permissions'); ?></label>
                <div class="grid grid-2" id="edit-permissions">
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_sales]" value="1">
                        <span>Gerenciar Vendas</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_products]" value="1">
                        <span>Gerenciar Produtos</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_clients]" value="1">
                        <span>Gerenciar Clientes</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[view_reports]" value="1">
                        <span>Ver Relatórios</span>
                    </label>
                    <label class="permission-checkbox">
                        <input type="checkbox" name="permissions[manage_employees]" value="1">
                        <span>Gerenciar Funcionários</span>
                    </label>
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Change Password Modal -->
<div id="change-password-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo __('change_password'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="change_password">
            <input type="hidden" name="employee_id" id="password-employee-id">
            
            <div class="form-group">
                <label class="form-label"><?php echo __('employee_name'); ?></label>
                <input type="text" id="password-employee-name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('new_password'); ?></label>
                <input type="password" name="new_password" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label"><?php echo __('confirm_password'); ?></label>
                <input type="password" name="confirm_password" class="form-control" required>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-warning"><?php echo __('change_password'); ?></button>
            </div>
        </form>
    </div>
</div>

<script>
function editEmployee(employee) {
    document.getElementById('edit-employee-id').value = employee.id;
    document.getElementById('edit-employee-name').value = employee.name;
    document.getElementById('edit-user-type').value = employee.user_type;
    document.getElementById('edit-canteen-type').value = employee.canteen_type;
    document.getElementById('edit-status').value = employee.status;
    
    // Set permissions
    const permissions = JSON.parse(employee.permissions || '{}');
    const checkboxes = document.querySelectorAll('#edit-permissions input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        const permission = checkbox.name.match(/\[(.*?)\]/)[1];
        checkbox.checked = permissions[permission] || false;
    });
    
    window.canteenApp.openModal('edit-employee-modal');
}

function changePassword(employeeId, employeeName) {
    document.getElementById('password-employee-id').value = employeeId;
    document.getElementById('password-employee-name').value = employeeName;
    
    window.canteenApp.openModal('change-password-modal');
}

// Validate password confirmation
document.querySelector('#change-password-modal form').addEventListener('submit', function(e) {
    const newPassword = this.querySelector('[name="new_password"]').value;
    const confirmPassword = this.querySelector('[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('As senhas não coincidem!');
    }
});
</script>

<style>
.permission-checkbox {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem;
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: var(--transition);
}

.permission-checkbox:hover {
    background: var(--background-color);
}

.permission-checkbox input[type="checkbox"] {
    margin: 0;
}

.badge-success {
    background: var(--success-color);
    color: white;
}

.badge-danger {
    background: var(--error-color);
    color: white;
}

.badge-admin {
    background: var(--error-color);
    color: white;
}

.badge-manager {
    background: var(--warning-color);
    color: white;
}

.badge-employee {
    background: var(--primary-color);
    color: white;
}
</style>

<?php include 'includes/footer.php'; ?>