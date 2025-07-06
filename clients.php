<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$database = new Database();
$conn = $database->connect();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_client':
                $stmt = $conn->prepare("INSERT INTO clients (name, type, contact, canteen_type, balance, debt) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                
                // Determine canteen_type based on client type
                $canteen_type = 'primary';
                if (in_array($_POST['type'], ['secondary', 'teacher_secondary'])) {
                    $canteen_type = 'secondary';
                }
                
                $stmt->execute([
                    $_POST['name'],
                    $_POST['type'],
                    $_POST['contact'] ?? '',
                    $canteen_type,
                    $_POST['balance'] ?? 0,
                    $_POST['debt'] ?? 0
                ]);
                $success_message = 'Cliente adicionado com sucesso!';
                break;
                
            case 'update_client':
                $stmt = $conn->prepare("UPDATE clients SET name = ?, type = ?, contact = ?, balance = ?, debt = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['type'],
                    $_POST['contact'] ?? '',
                    $_POST['balance'],
                    $_POST['debt'],
                    $_POST['client_id']
                ]);
                $success_message = 'Cliente atualizado com sucesso!';
                break;
                
            case 'delete_client':
                $stmt = $conn->prepare("UPDATE clients SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['client_id']]);
                $success_message = 'Cliente removido com sucesso!';
                break;
                
            case 'add_balance':
                $client_id = $_POST['client_id'];
                $amount = $_POST['amount'];
                
                // Update client balance
                $stmt = $conn->prepare("UPDATE clients SET balance = balance + ? WHERE id = ?");
                $stmt->execute([$amount, $client_id]);
                
                // Get updated balance and debt
                $stmt = $conn->prepare("SELECT balance, debt FROM clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Record transaction
                $stmt = $conn->prepare("INSERT INTO client_transactions (client_id, type, amount, description, balance_after, debt_after, user_id) 
                                       VALUES (?, 'payment', ?, 'Recarga de saldo', ?, ?, ?)");
                $stmt->execute([
                    $client_id,
                    $amount,
                    $client['balance'],
                    $client['debt'],
                    $_SESSION['user_id']
                ]);
                
                $success_message = 'Saldo adicionado com sucesso!';
                break;
                
            case 'pay_debt':
                $client_id = $_POST['client_id'];
                $amount = $_POST['amount'];
                $payment_method = $_POST['payment_method'] ?? 'cash'; // Novo campo
                
                // Update client debt
                $stmt = $conn->prepare("UPDATE clients SET debt = GREATEST(0, debt - ?) WHERE id = ?");
                $stmt->execute([$amount, $client_id]);
                
                // Get updated balance and debt
                $stmt = $conn->prepare("SELECT balance, debt FROM clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Get client name for description
                $stmt = $conn->prepare("SELECT name FROM clients WHERE id = ?");
                $stmt->execute([$client_id]);
                $client_name = $stmt->fetch(PDO::FETCH_ASSOC)['name'];
                
                // Method names for description
                $method_names = [
                    'cash' => 'Dinheiro',
                    'mpesa' => 'M-Pesa',
                    'emola' => 'E-Mola',
                    'voucher' => 'Voucher'
                ];
                
                $method_display = $method_names[$payment_method] ?? ucfirst($payment_method);
                
                // Record transaction with payment method
                $stmt = $conn->prepare("INSERT INTO client_transactions (client_id, type, amount, description, balance_after, debt_after, user_id) 
                                       VALUES (?, 'payment', ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $client_id,
                    $amount,
                    "Pagamento de dívida via {$method_display} - Cliente: {$client_name}",
                    $client['balance'],
                    $client['debt'],
                    $_SESSION['user_id']
                ]);
                
                // Log the debt payment action
                require_once 'api/log_action.php';
                logAction('payment', "Pagamento de dívida - {$client_name} - {$method_display}", $amount, $payment_method);
                
                $success_message = "Dívida paga com sucesso via {$method_display}!";
                break;
        }
    }
}

// Get filter parameters
$client_type = isset($_GET['client_type']) ? $_GET['client_type'] : 'all';
$canteen_filter = isset($_GET['canteen']) ? $_GET['canteen'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'all-clients';

// Build query conditions
$conditions = ["status = 'active'"];
$params = [];

// Apply client type filter
if ($client_type !== 'all') {
    $conditions[] = "type = ?";
    $params[] = $client_type;
}

// Apply canteen filter
if ($canteen_filter !== 'all') {
    $conditions[] = "canteen_type = ?";
    $params[] = $canteen_filter;
}

// Apply search filter
if (!empty($search_term)) {
    $conditions[] = "(name LIKE ? OR contact LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

// Get canteen condition based on user's access
$user_canteen_condition = '';
if ($_SESSION['canteen_type'] !== 'both') {
    $conditions[] = "(canteen_type = ? OR type LIKE ?)";
    $params[] = $_SESSION['canteen_type'];
    $params[] = "%{$_SESSION['canteen_type']}%";
}

// Build the final query
$where_clause = implode(' AND ', $conditions);
$query = "SELECT * FROM clients WHERE $where_clause ORDER BY name";

// Get all clients
$stmt = $conn->prepare($query);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Separate clients with balance and debtors
$clients_with_balance = array_filter($clients, function($client) {
    return $client['balance'] > 0;
});

$debtors = array_filter($clients, function($client) {
    return $client['debt'] > 0;
});

// Count clients by type for filter badges
$stmt = $conn->prepare("SELECT 
                          type, 
                          COUNT(*) as count,
                          SUM(balance) as total_balance,
                          SUM(debt) as total_debt
                       FROM clients 
                       WHERE status = 'active'
                       " . ($user_canteen_condition ? " AND $user_canteen_condition" : "") . "
                       GROUP BY type
                       ORDER BY type");
$stmt->execute();
$client_type_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count clients by canteen for filter badges
$stmt = $conn->prepare("SELECT 
                          canteen_type, 
                          COUNT(*) as count
                       FROM clients 
                       WHERE status = 'active'
                       " . ($user_canteen_condition ? " AND $user_canteen_condition" : "") . "
                       GROUP BY canteen_type");
$stmt->execute();
$canteen_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-users"></i>
            Gestão de Clientes
        </h1>
        <button type="button" class="btn btn-primary" data-modal="add-client-modal">
            <i class="fas fa-plus"></i>
            Adicionar Cliente
        </button>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <!-- Filtros Avançados -->
    <div class="card mb-3">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-filter"></i>
                Filtros
            </h3>
        </div>
        <div class="card-body">
            <form method="GET" class="filter-form">
                <input type="hidden" name="tab" value="<?php echo $tab; ?>">
                
                <div class="grid grid-3">
                    <div class="form-group">
                        <label class="form-label">Buscar Cliente</label>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Nome ou contacto..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Cliente</label>
                        <select name="client_type" class="form-control form-select">
                            <option value="all">Todos os Tipos</option>
                            <option value="primary" <?php echo $client_type == 'primary' ? 'selected' : ''; ?>>
                                Alunos Primária (<?php 
                                $count = 0;
                                foreach ($client_type_counts as $type_count) {
                                    if ($type_count['type'] == 'primary') {
                                        $count = $type_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                            <option value="secondary" <?php echo $client_type == 'secondary' ? 'selected' : ''; ?>>
                                Alunos Secundária (<?php 
                                $count = 0;
                                foreach ($client_type_counts as $type_count) {
                                    if ($type_count['type'] == 'secondary') {
                                        $count = $type_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                            <option value="teacher_primary" <?php echo $client_type == 'teacher_primary' ? 'selected' : ''; ?>>
                                Professores Primária (<?php 
                                $count = 0;
                                foreach ($client_type_counts as $type_count) {
                                    if ($type_count['type'] == 'teacher_primary') {
                                        $count = $type_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                            <option value="teacher_secondary" <?php echo $client_type == 'teacher_secondary' ? 'selected' : ''; ?>>
                                Professores Secundária (<?php 
                                $count = 0;
                                foreach ($client_type_counts as $type_count) {
                                    if ($type_count['type'] == 'teacher_secondary') {
                                        $count = $type_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                        </select>
                    </div>
                    
                    <?php if ($_SESSION['canteen_type'] === 'both'): ?>
                    <div class="form-group">
                        <label class="form-label">Cantina</label>
                        <select name="canteen" class="form-control form-select">
                            <option value="all">Todas as Cantinas</option>
                            <option value="primary" <?php echo $canteen_filter == 'primary' ? 'selected' : ''; ?>>
                                Primária (<?php 
                                $count = 0;
                                foreach ($canteen_counts as $canteen_count) {
                                    if ($canteen_count['canteen_type'] == 'primary') {
                                        $count = $canteen_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                            <option value="secondary" <?php echo $canteen_filter == 'secondary' ? 'selected' : ''; ?>>
                                Secundária (<?php 
                                $count = 0;
                                foreach ($canteen_counts as $canteen_count) {
                                    if ($canteen_count['canteen_type'] == 'secondary') {
                                        $count = $canteen_count['count'];
                                        break;
                                    }
                                }
                                echo $count;
                                ?>)
                            </option>
                        </select>
                    </div>
                    <?php endif; ?>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Aplicar Filtros
                    </button>
                    <a href="clients.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resumo dos Filtros Aplicados -->
    <?php if ($client_type != 'all' || $canteen_filter != 'all' || !empty($search_term)): ?>
    <div class="filter-summary">
        <div class="filter-summary-content">
            <span class="filter-summary-title">Filtros aplicados:</span>
            
            <?php if (!empty($search_term)): ?>
            <span class="filter-badge">
                <span class="filter-label">Busca:</span>
                <span class="filter-value"><?php echo htmlspecialchars($search_term); ?></span>
                <a href="<?php echo '?tab=' . $tab . '&client_type=' . $client_type . '&canteen=' . $canteen_filter; ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <?php if ($client_type != 'all'): ?>
            <span class="filter-badge">
                <span class="filter-label">Tipo:</span>
                <span class="filter-value">
                    <?php 
                    $type_names = [
                        'primary' => 'Alunos Primária',
                        'secondary' => 'Alunos Secundária',
                        'teacher_primary' => 'Professores Primária',
                        'teacher_secondary' => 'Professores Secundária'
                    ];
                    echo $type_names[$client_type] ?? ucfirst($client_type);
                    ?>
                </span>
                <a href="<?php echo '?tab=' . $tab . '&canteen=' . $canteen_filter . '&search=' . urlencode($search_term); ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <?php if ($canteen_filter != 'all' && $_SESSION['canteen_type'] === 'both'): ?>
            <span class="filter-badge">
                <span class="filter-label">Cantina:</span>
                <span class="filter-value">
                    <?php echo $canteen_filter === 'primary' ? 'Primária' : 'Secundária'; ?>
                </span>
                <a href="<?php echo '?tab=' . $tab . '&client_type=' . $client_type . '&search=' . urlencode($search_term); ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <span class="filter-results">
                <?php echo count($clients); ?> clientes encontrados
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Tabs for different client views -->
    <div class="tabs-container">
        <div class="tabs">
            <a href="?tab=all-clients<?php echo isset($_GET['client_type']) ? '&client_type=' . $_GET['client_type'] : ''; ?><?php echo isset($_GET['canteen']) ? '&canteen=' . $_GET['canteen'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="tab <?php echo $tab === 'all-clients' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                Todos os Clientes
                <small><?php echo count($clients); ?> clientes</small>
            </a>
            <a href="?tab=clients-with-balance<?php echo isset($_GET['client_type']) ? '&client_type=' . $_GET['client_type'] : ''; ?><?php echo isset($_GET['canteen']) ? '&canteen=' . $_GET['canteen'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="tab <?php echo $tab === 'clients-with-balance' ? 'active' : ''; ?>">
                <i class="fas fa-wallet"></i>
                Clientes com Saldo
                <small><?php echo count($clients_with_balance); ?> clientes</small>
            </a>
            <a href="?tab=debtors<?php echo isset($_GET['client_type']) ? '&client_type=' . $_GET['client_type'] : ''; ?><?php echo isset($_GET['canteen']) ? '&canteen=' . $_GET['canteen'] : ''; ?><?php echo isset($_GET['search']) ? '&search=' . urlencode($_GET['search']) : ''; ?>" 
               class="tab <?php echo $tab === 'debtors' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding-usd"></i>
                Devedores
                <small><?php echo count($debtors); ?> clientes</small>
            </a>
        </div>
    </div>
    
    <!-- All Clients Tab -->
    <div id="all-clients" class="tab-content <?php echo $tab === 'all-clients' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">
                <h3>Todos os Clientes</h3>
                <div class="card-header-actions">
                    <button type="button" class="btn btn-secondary btn-small" onclick="printClientList('all')">
                        <i class="fas fa-print"></i>
                        Imprimir Lista
                    </button>
                    <button type="button" class="btn btn-success btn-small" onclick="exportClientList('all')">
                        <i class="fas fa-file-excel"></i>
                        Exportar Excel
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table" id="clients-table">
                    <thead>
                        <tr>
                            <th data-sort="name">Nome</th>
                            <th data-sort="type">Tipo</th>
                            <th>Contacto</th>
                            <th data-sort="balance">Saldo Atual</th>
                            <th data-sort="debt">Dívida Atual</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="6" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-search" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                    <h4>Nenhum cliente encontrado</h4>
                                    <p>Tente ajustar os filtros ou adicione novos clientes.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td data-sort="name"><?php echo htmlspecialchars($client['name']); ?></td>
                            <td data-sort="type">
                                <span class="badge badge-<?php echo str_replace('_', '-', $client['type']); ?>">
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
                            <td data-sort="balance">
                                <span class="<?php echo $client['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                    Mt <?php echo number_format($client['balance'], 2); ?>
                                </span>
                            </td>
                            <td data-sort="debt">
                                <span class="<?php echo $client['debt'] > 0 ? 'text-danger' : ''; ?>">
                                    Mt <?php echo number_format($client['debt'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="editClient(<?php echo $client['id']; ?>)"
                                            title="Editar Cliente">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn action-btn-add" 
                                            onclick="addBalance(<?php echo $client['id']; ?>)"
                                            title="Adicionar Saldo">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    
                                    <?php if ($client['debt'] > 0): ?>
                                    <button type="button" class="action-btn action-btn-clear" 
                                            onclick="payDebt(<?php echo $client['id']; ?>)"
                                            title="Pagar Dívida">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="viewClientHistory(<?php echo $client['id']; ?>)"
                                            title="Ver Histórico">
                                        <i class="fas fa-history"></i>
                                    </button>
                                    
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Remover este cliente?')">
                                        <input type="hidden" name="action" value="delete_client">
                                        <input type="hidden" name="client_id" value="<?php echo $client['id']; ?>">
                                        <button type="submit" class="action-btn action-btn-delete" title="Remover Cliente">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
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
    
    <!-- Clients with Balance Tab -->
    <div id="clients-with-balance" class="tab-content <?php echo $tab === 'clients-with-balance' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">
                <h3>Clientes com Saldo Positivo</h3>
                <div class="card-header-actions">
                    <button type="button" class="btn btn-secondary btn-small" onclick="printClientList('balance')">
                        <i class="fas fa-print"></i>
                        Imprimir Lista
                    </button>
                    <button type="button" class="btn btn-success btn-small" onclick="exportClientList('balance')">
                        <i class="fas fa-file-excel"></i>
                        Exportar Excel
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Saldo Atual</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($clients_with_balance)): ?>
                        <tr>
                            <td colspan="4" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-wallet" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                    <h4>Nenhum cliente com saldo positivo</h4>
                                    <p>Adicione saldo aos clientes para que apareçam nesta lista.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($clients_with_balance as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo str_replace('_', '-', $client['type']); ?>">
                                    <?php echo $type_names[$client['type']] ?? ucfirst($client['type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-success">
                                    Mt <?php echo number_format($client['balance'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="editClient(<?php echo $client['id']; ?>)"
                                            title="Editar Cliente">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn action-btn-add" 
                                            onclick="addBalance(<?php echo $client['id']; ?>)"
                                            title="Adicionar Saldo">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="viewClientHistory(<?php echo $client['id']; ?>)"
                                            title="Ver Histórico">
                                        <i class="fas fa-history"></i>
                                    </button>
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
    
    <!-- Debtors Tab -->
    <div id="debtors" class="tab-content <?php echo $tab === 'debtors' ? 'active' : ''; ?>">
        <div class="card">
            <div class="card-header">
                <h3>Clientes com Dívidas</h3>
                <div class="card-header-actions">
                    <button type="button" class="btn btn-secondary btn-small" onclick="printClientList('debt')">
                        <i class="fas fa-print"></i>
                        Imprimir Lista
                    </button>
                    <button type="button" class="btn btn-success btn-small" onclick="exportClientList('debt')">
                        <i class="fas fa-file-excel"></i>
                        Exportar Excel
                    </button>
                </div>
            </div>
            
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Dívida Atual</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($debtors)): ?>
                        <tr>
                            <td colspan="4" class="text-center">
                                <div class="empty-state">
                                    <i class="fas fa-check-circle" style="font-size: 3rem; color: var(--success-color); margin-bottom: 1rem;"></i>
                                    <h4>Nenhum cliente com dívidas</h4>
                                    <p>Todos os clientes estão com suas contas em dia.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($debtors as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo str_replace('_', '-', $client['type']); ?>">
                                    <?php echo $type_names[$client['type']] ?? ucfirst($client['type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="text-danger">
                                    Mt <?php echo number_format($client['debt'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="editClient(<?php echo $client['id']; ?>)"
                                            title="Editar Cliente">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn action-btn-clear" 
                                            onclick="payDebt(<?php echo $client['id']; ?>)"
                                            title="Pagar Dívida">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </button>
                                    
                                    <button type="button" class="action-btn action-btn-edit" 
                                            onclick="viewClientHistory(<?php echo $client['id']; ?>)"
                                            title="Ver Histórico">
                                        <i class="fas fa-history"></i>
                                    </button>
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
</div>

<!-- Add Client Modal -->
<div id="add-client-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Adicionar Cliente</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_client">
            
            <div class="form-group">
                <label class="form-label">Nome do Cliente</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipo de Cliente</label>
                <select name="type" class="form-control form-select" required>
                    <?php if ($_SESSION['canteen_type'] === 'primary' || $_SESSION['canteen_type'] === 'both'): ?>
                    <option value="primary">Aluno Primária</option>
                    <option value="teacher_primary">Professor Primária</option>
                    <?php endif; ?>
                    <?php if ($_SESSION['canteen_type'] === 'secondary' || $_SESSION['canteen_type'] === 'both'): ?>
                    <option value="secondary">Aluno Secundária</option>
                    <option value="teacher_secondary">Professor Secundária</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contacto (Opcional)</label>
                <input type="text" name="contact" class="form-control" placeholder="Telefone, email, etc.">
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Saldo Inicial (Mt)</label>
                    <input type="number" name="balance" class="form-control" step="0.01" min="0" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dívida Inicial (Mt)</label>
                    <input type="number" name="debt" class="form-control" step="0.01" min="0" value="0">
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-end" style="padding: 1.5rem;">
                <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                <button type="submit" class="btn btn-primary">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Client Modal -->
<div id="edit-client-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>Editar Cliente</h3>
                <div class="client-navigation">
                    <span id="current-client-position">Cliente 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <form id="edit-client-form" method="POST">
            <input type="hidden" name="action" value="update_client">
            <input type="hidden" name="client_id" id="edit-client-id">
            
            <div class="form-group">
                <label class="form-label">Nome do Cliente</label>
                <input type="text" name="name" id="edit-client-name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Tipo de Cliente</label>
                <select name="type" id="edit-client-type" class="form-control form-select" required>
                    <?php if ($_SESSION['canteen_type'] === 'primary' || $_SESSION['canteen_type'] === 'both'): ?>
                    <option value="primary">Aluno Primária</option>
                    <option value="teacher_primary">Professor Primária</option>
                    <?php endif; ?>
                    <?php if ($_SESSION['canteen_type'] === 'secondary' || $_SESSION['canteen_type'] === 'both'): ?>
                    <option value="secondary">Aluno Secundária</option>
                    <option value="teacher_secondary">Professor Secundária</option>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Contacto</label>
                <input type="text" name="contact" id="edit-client-contact" class="form-control">
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Saldo Atual (Mt)</label>
                    <input type="number" name="balance" id="edit-client-balance" class="form-control" step="0.01">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dívida Atual (Mt)</label>
                    <input type="number" name="debt" id="edit-client-debt" class="form-control" step="0.01" min="0">
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-between">
                <div class="client-navigation-buttons">
                    <button type="button" id="prev-client" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" id="next-client" class="btn btn-secondary">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="button" id="save-client-btn" class="btn btn-primary">Salvar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Balance Modal -->
<div id="add-balance-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>Adicionar Saldo</h3>
                <div class="client-navigation">
                    <span id="balance-client-position">Cliente 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <form id="add-balance-form" method="POST">
            <input type="hidden" name="action" value="add_balance">
            <input type="hidden" name="client_id" id="balance-client-id">
            
            <div class="form-group">
                <label class="form-label">Cliente</label>
                <input type="text" id="balance-client-name" class="form-control" readonly>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Saldo Atual</label>
                    <input type="text" id="balance-current-amount" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Dívida Atual</label>
                    <input type="text" id="balance-current-debt" class="form-control" readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Valor a Adicionar (Mt)</label>
                <input type="number" name="amount" id="balance-amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <div class="d-flex gap-2 justify-between">
                <div class="client-navigation-buttons">
                    <button type="button" id="prev-balance-client" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" id="next-balance-client" class="btn btn-secondary">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-success">Adicionar Saldo</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Pay Debt Modal - ATUALIZADO COM MÉTODO DE PAGAMENTO -->
<div id="pay-debt-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>
                    <i class="fas fa-hand-holding-usd"></i>
                    Pagar Dívida
                </h3>
                <div class="client-navigation">
                    <span id="debt-client-position">Cliente 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <form id="pay-debt-form" method="POST">
            <input type="hidden" name="action" value="pay_debt">
            <input type="hidden" name="client_id" id="debt-client-id">
            
            <div class="form-group">
                <label class="form-label">Cliente</label>
                <input type="text" id="debt-client-name" class="form-control" readonly>
            </div>
            
            <div class="form-group">
                <label class="form-label">Dívida Atual</label>
                <input type="text" id="debt-current-amount" class="form-control" readonly>
            </div>
            
            <div class="payment-options">
                <div class="payment-option">
                    <input type="radio" name="payment_type" id="payment-full" value="full" checked>
                    <label for="payment-full">Pagamento Total</label>
                </div>
                <div class="payment-option">
                    <input type="radio" name="payment_type" id="payment-partial" value="partial">
                    <label for="payment-partial">Pagamento Parcial</label>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Valor a Pagar (Mt)</label>
                <input type="number" name="amount" id="debt-payment-amount" class="form-control" step="0.01" min="0.01" required>
            </div>
            
            <!-- NOVO: Seleção do Método de Pagamento -->
            <div class="form-group">
                <label class="form-label">
                    <i class="fas fa-credit-card"></i>
                    Método de Pagamento
                </label>
                <select name="payment_method" id="debt-payment-method" class="form-control form-select" required>
                    <option value="cash">
                        <i class="fas fa-money-bill-wave"></i>
                        💵 Dinheiro
                    </option>
                    <option value="mpesa">
                        📱 M-Pesa
                    </option>
                    <option value="emola">
                        📱 E-Mola
                    </option>
                    <option value="voucher">
                        🎫 Voucher
                    </option>
                </select>
            </div>
            
            <!-- Informação sobre o método selecionado -->
            <div class="payment-method-info" id="payment-method-info">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <span id="payment-method-description">Selecione o método de pagamento utilizado pelo cliente.</span>
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-between">
                <div class="client-navigation-buttons">
                    <button type="button" id="prev-debt-client" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" id="next-debt-client" class="btn btn-secondary">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary modal-close">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-hand-holding-usd"></i>
                        Confirmar Pagamento
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Client History Modal -->
<div id="client-history-modal" class="modal-overlay" style="display: none;">
    <div class="modal modal-large">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>Histórico do Cliente</h3>
                <div class="client-navigation">
                    <span id="history-client-position">Cliente 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <div class="modal-body" id="client-history-content">
            <!-- Content will be loaded here -->
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i>
                <span>Carregando histórico...</span>
            </div>
        </div>
        
        <div class="modal-footer">
            <div class="client-navigation-buttons">
                <button type="button" id="prev-history-client" class="btn btn-secondary">
                    <i class="fas fa-chevron-left"></i> Cliente Anterior
                </button>
                <button type="button" id="next-history-client" class="btn btn-secondary">
                    Próximo Cliente <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="notification-toast" class="notification-toast" style="display: none;">
    <div class="notification-content">
        <i class="fas fa-check-circle"></i>
        <span id="notification-message">Alterações salvas com sucesso!</span>
    </div>
</div>

<script>
// Array global para armazenar todos os clientes
let allClients = <?php echo json_encode($clients); ?>;
let allDebtors = <?php echo json_encode($debtors); ?>;
let allClientsWithBalance = <?php echo json_encode($clients_with_balance); ?>;
let currentClientIndex = 0;
let currentBalanceClientIndex = 0;
let currentDebtClientIndex = 0;
let currentHistoryClientIndex = 0;

// Tab functionality
document.querySelectorAll('.tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        // Links já têm href configurado para manter os filtros
    });
});

// Função para editar cliente com navegação
function editClient(clientId) {
    // Encontrar o cliente pelo ID
    const clientIndex = allClients.findIndex(c => c.id == clientId);
    if (clientIndex === -1) return;
    
    // Atualizar índice atual
    currentClientIndex = clientIndex;
    
    // Carregar dados do cliente
    loadClientData(currentClientIndex);
    
    // Abrir modal
    window.canteenApp.openModal('edit-client-modal');
}

// Função para carregar dados do cliente no formulário
function loadClientData(index) {
    if (index < 0 || index >= allClients.length) return;
    
    const client = allClients[index];
    
    // Atualizar posição atual
    document.getElementById('current-client-position').textContent = 
        `Cliente ${index + 1} de ${allClients.length}`;
    
    // Preencher formulário
    document.getElementById('edit-client-id').value = client.id;
    document.getElementById('edit-client-name').value = client.name;
    document.getElementById('edit-client-type').value = client.type;
    document.getElementById('edit-client-contact').value = client.contact || '';
    document.getElementById('edit-client-balance').value = client.balance;
    document.getElementById('edit-client-debt').value = client.debt;
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-client').disabled = index === 0;
    document.getElementById('next-client').disabled = index === allClients.length - 1;
}

// Navegação entre clientes - Edição
document.getElementById('prev-client').addEventListener('click', function() {
    // Salvar alterações do cliente atual antes de navegar
    saveCurrentClient(function() {
        if (currentClientIndex > 0) {
            currentClientIndex--;
            loadClientData(currentClientIndex);
        }
    });
});

document.getElementById('next-client').addEventListener('click', function() {
    // Salvar alterações do cliente atual antes de navegar
    saveCurrentClient(function() {
        if (currentClientIndex < allClients.length - 1) {
            currentClientIndex++;
            loadClientData(currentClientIndex);
        }
    });
});

// Salvar cliente via AJAX
document.getElementById('save-client-btn').addEventListener('click', function() {
    saveCurrentClient();
});

function saveCurrentClient(callback) {
    const form = document.getElementById('edit-client-form');
    const formData = new FormData(form);
    
    // Mostrar indicador de carregamento
    document.getElementById('save-client-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    document.getElementById('save-client-btn').disabled = true;
    
    fetch('ajax_update_client.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar o cliente no array
            const updatedClient = {
                id: formData.get('client_id'),
                name: formData.get('name'),
                type: formData.get('type'),
                contact: formData.get('contact'),
                balance: formData.get('balance'),
                debt: formData.get('debt')
            };
            
            allClients[currentClientIndex] = {...allClients[currentClientIndex], ...updatedClient};
            
            // Atualizar arrays de devedores e clientes com saldo
            updateClientArrays();
            
            // Mostrar notificação
            showNotification('Cliente atualizado com sucesso!');
            
            // Atualizar a tabela sem recarregar a página
            updateClientInTable(updatedClient);
            
            // Executar callback se fornecido (para navegação)
            if (typeof callback === 'function') {
                callback();
            }
        } else {
            alert('Erro ao salvar cliente: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar cliente. Verifique o console para mais detalhes.');
    })
    .finally(() => {
        // Restaurar botão
        document.getElementById('save-client-btn').innerHTML = 'Salvar';
        document.getElementById('save-client-btn').disabled = false;
    });
}

// Atualizar arrays de clientes
function updateClientArrays() {
    // Atualizar array de devedores
    allDebtors = allClients.filter(client => client.debt > 0);
    
    // Atualizar array de clientes com saldo
    allClientsWithBalance = allClients.filter(client => client.balance > 0);
}

// Atualizar cliente na tabela sem recarregar a página
function updateClientInTable(client) {
    const tables = document.querySelectorAll('.table');
    
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        
        for (let i = 0; i < rows.length; i++) {
            const clientIdInput = rows[i].querySelector('input[name="client_id"]');
            if (clientIdInput && clientIdInput.value == client.id) {
                const cells = rows[i].querySelectorAll('td');
                
                // Atualizar células da tabela (nome e tipo são comuns a todas as tabelas)
                if (cells[0]) cells[0].textContent = client.name;
                
                if (cells[1]) {
                    const type_names = {
                        'primary': 'Aluno Primária',
                        'secondary': 'Aluno Secundária',
                        'teacher_primary': 'Professor Primária',
                        'teacher_secondary': 'Professor Secundária',
                        'reservation': 'Reserva'
                    };
                    
                    cells[1].innerHTML = `<span class="badge badge-${client.type.replace('_', '-')}">${type_names[client.type] || client.type}</span>`;
                }
                
                // Verificar se é a tabela completa (com saldo e dívida)
                if (cells.length >= 5) {
                    if (cells[2]) cells[2].textContent = client.contact || '';
                    if (cells[3]) cells[3].innerHTML = `<span class="${client.balance < 0 ? 'text-danger' : 'text-success'}">Mt ${parseFloat(client.balance).toFixed(2)}</span>`;
                    if (cells[4]) cells[4].innerHTML = `<span class="${client.debt > 0 ? 'text-danger' : ''}">Mt ${parseFloat(client.debt).toFixed(2)}</span>`;
                }
                // Verificar se é a tabela de clientes com saldo
                else if (table.closest('#clients-with-balance')) {
                    if (cells[2]) cells[2].innerHTML = `<span class="text-success">Mt ${parseFloat(client.balance).toFixed(2)}</span>`;
                }
                // Verificar se é a tabela de devedores
                else if (table.closest('#debtors')) {
                    if (cells[2]) cells[2].innerHTML = `<span class="text-danger">Mt ${parseFloat(client.debt).toFixed(2)}</span>`;
                }
                
                break;
            }
        }
    });
}

// Função para adicionar saldo com navegação
function addBalance(clientId) {
    // Encontrar o cliente pelo ID
    const clientIndex = allClients.findIndex(c => c.id == clientId);
    if (clientIndex === -1) return;
    
    // Atualizar índice atual
    currentBalanceClientIndex = clientIndex;
    
    // Carregar dados do cliente
    loadBalanceClientData(currentBalanceClientIndex);
    
    // Abrir modal
    window.canteenApp.openModal('add-balance-modal');
}

// Função para carregar dados do cliente no formulário de saldo
function loadBalanceClientData(index) {
    if (index < 0 || index >= allClients.length) return;
    
    const client = allClients[index];
    
    // Atualizar posição atual
    document.getElementById('balance-client-position').textContent = 
        `Cliente ${index + 1} de ${allClients.length}`;
    
    // Preencher formulário
    document.getElementById('balance-client-id').value = client.id;
    document.getElementById('balance-client-name').value = client.name;
    document.getElementById('balance-current-amount').value = `Mt ${parseFloat(client.balance).toFixed(2)}`;
    document.getElementById('balance-current-debt').value = `Mt ${parseFloat(client.debt).toFixed(2)}`;
    document.getElementById('balance-amount').value = '';
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-balance-client').disabled = index === 0;
    document.getElementById('next-balance-client').disabled = index === allClients.length - 1;
}

// Navegação entre clientes - Adição de Saldo
document.getElementById('prev-balance-client').addEventListener('click', function() {
    if (currentBalanceClientIndex > 0) {
        currentBalanceClientIndex--;
        loadBalanceClientData(currentBalanceClientIndex);
    }
});

document.getElementById('next-balance-client').addEventListener('click', function() {
    if (currentBalanceClientIndex < allClients.length - 1) {
        currentBalanceClientIndex++;
        loadBalanceClientData(currentBalanceClientIndex);
    }
});

// Função para pagar dívida com navegação
function payDebt(clientId) {
    // Filtrar apenas clientes com dívida
    const debtorIndex = allDebtors.findIndex(c => c.id == clientId);
    if (debtorIndex === -1) return;
    
    // Atualizar índice atual
    currentDebtClientIndex = debtorIndex;
    
    // Carregar dados do cliente
    loadDebtClientData(currentDebtClientIndex);
    
    // Abrir modal
    window.canteenApp.openModal('pay-debt-modal');
}

// Função para carregar dados do cliente no formulário de dívida
function loadDebtClientData(index) {
    if (index < 0 || index >= allDebtors.length) return;
    
    const client = allDebtors[index];
    
    // Atualizar posição atual
    document.getElementById('debt-client-position').textContent = 
        `Cliente ${index + 1} de ${allDebtors.length}`;
    
    // Preencher formulário
    document.getElementById('debt-client-id').value = client.id;
    document.getElementById('debt-client-name').value = client.name;
    document.getElementById('debt-current-amount').value = `Mt ${parseFloat(client.debt).toFixed(2)}`;
    document.getElementById('debt-payment-amount').value = parseFloat(client.debt).toFixed(2);
    document.getElementById('debt-payment-amount').max = client.debt;
    
    // Resetar opções de pagamento
    document.getElementById('payment-full').checked = true;
    document.getElementById('debt-payment-method').value = 'cash';
    updatePaymentMethodInfo('cash');
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-debt-client').disabled = index === 0;
    document.getElementById('next-debt-client').disabled = index === allDebtors.length - 1;
}

// Navegação entre clientes - Pagamento de Dívida
document.getElementById('prev-debt-client').addEventListener('click', function() {
    if (currentDebtClientIndex > 0) {
        currentDebtClientIndex--;
        loadDebtClientData(currentDebtClientIndex);
    }
});

document.getElementById('next-debt-client').addEventListener('click', function() {
    if (currentDebtClientIndex < allDebtors.length - 1) {
        currentDebtClientIndex++;
        loadDebtClientData(currentDebtClientIndex);
    }
});

// Função para ver histórico do cliente com navegação
function viewClientHistory(clientId) {
    // Encontrar o cliente pelo ID
    const clientIndex = allClients.findIndex(c => c.id == clientId);
    if (clientIndex === -1) return;
    
    // Atualizar índice atual
    currentHistoryClientIndex = clientIndex;
    
    // Carregar histórico do cliente
    loadClientHistory(currentHistoryClientIndex);
    
    // Abrir modal
    window.canteenApp.openModal('client-history-modal');
}

// Função para carregar histórico do cliente
function loadClientHistory(index) {
    if (index < 0 || index >= allClients.length) return;
    
    const client = allClients[index];
    
    // Atualizar posição atual
    document.getElementById('history-client-position').textContent = 
        `Cliente ${index + 1} de ${allClients.length}`;
    
    // Mostrar loading
    document.getElementById('client-history-content').innerHTML = `
        <div class="loading-spinner">
            <i class="fas fa-spinner fa-spin"></i>
            <span>Carregando histórico...</span>
        </div>
    `;
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-history-client').disabled = index === 0;
    document.getElementById('next-history-client').disabled = index === allClients.length - 1;
    
    // Carregar histórico via AJAX
    fetch(`api/get_client_history.php?client_id=${client.id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('client-history-content').innerHTML = data.html;
            } else {
                document.getElementById('client-history-content').innerHTML = `
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Erro ao carregar histórico: ${data.message}
                    </div>
                `;
            }
        })
        .catch(error => {
            document.getElementById('client-history-content').innerHTML = `
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    Erro ao carregar histórico: ${error.message}
                </div>
            `;
        });
}

// Navegação entre clientes - Histórico
document.getElementById('prev-history-client').addEventListener('click', function() {
    if (currentHistoryClientIndex > 0) {
        currentHistoryClientIndex--;
        loadClientHistory(currentHistoryClientIndex);
    }
});

document.getElementById('next-history-client').addEventListener('click', function() {
    if (currentHistoryClientIndex < allClients.length - 1) {
        currentHistoryClientIndex++;
        loadClientHistory(currentHistoryClientIndex);
    }
});

// Opções de pagamento de dívida
document.querySelectorAll('[name="payment_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const amountInput = document.getElementById('debt-payment-amount');
        const clientId = document.getElementById('debt-client-id').value;
        const client = allDebtors.find(c => c.id == clientId);
        
        if (this.value === 'full' && client) {
            amountInput.value = parseFloat(client.debt).toFixed(2);
            amountInput.readOnly = true;
        } else {
            amountInput.readOnly = false;
        }
    });
});

// NOVO: Função para atualizar informações do método de pagamento
function updatePaymentMethodInfo(method) {
    const descriptions = {
        'cash': '💵 Pagamento em dinheiro vivo. Certifique-se de ter o troco correto.',
        'mpesa': '📱 Pagamento via M-Pesa. Confirme o número e o valor antes de processar.',
        'emola': '📱 Pagamento via E-Mola. Verifique a transação no aplicativo.',
        'voucher': '🎫 Pagamento com voucher. Confirme a validade e o valor do voucher.'
    };
    
    const infoElement = document.getElementById('payment-method-description');
    if (infoElement) {
        infoElement.textContent = descriptions[method] || 'Selecione o método de pagamento utilizado pelo cliente.';
    }
}

// Event listener para mudança do método de pagamento
document.getElementById('debt-payment-method')?.addEventListener('change', function() {
    updatePaymentMethodInfo(this.value);
});

// Mostrar notificação toast
function showNotification(message) {
    const toast = document.getElementById('notification-toast');
    const messageElement = document.getElementById('notification-message');
    
    messageElement.textContent = message;
    toast.style.display = 'block';
    
    // Animar entrada
    toast.style.animation = 'slideInRight 0.3s, fadeOut 0.5s 2.5s';
    
    // Esconder após 3 segundos
    setTimeout(() => {
        toast.style.display = 'none';
        toast.style.animation = '';
    }, 3000);
}

// Funções para impressão e exportação
function printClientList(type) {
    let url = 'print_clients.php?type=' + type;
    
    // Adicionar filtros atuais
    if ('<?php echo $client_type; ?>' !== 'all') {
        url += '&client_type=<?php echo $client_type; ?>';
    }
    
    if ('<?php echo $canteen_filter; ?>' !== 'all') {
        url += '&canteen=<?php echo $canteen_filter; ?>';
    }
    
    if ('<?php echo $search_term; ?>') {
        url += '&search=<?php echo urlencode($search_term); ?>';
    }
    
    window.open(url, '_blank');
}

function exportClientList(type) {
    let url = 'export_clients.php?type=' + type + '&format=excel';
    
    // Adicionar filtros atuais
    if ('<?php echo $client_type; ?>' !== 'all') {
        url += '&client_type=<?php echo $client_type; ?>';
    }
    
    if ('<?php echo $canteen_filter; ?>' !== 'all') {
        url += '&canteen=<?php echo $canteen_filter; ?>';
    }
    
    if ('<?php echo $search_term; ?>') {
        url += '&search=<?php echo urlencode($search_term); ?>';
    }
    
    window.location.href = url;
}

// Functions for PDF and Print
function downloadPDF(clientId) {
    // Show loading state
    const btn = event.target.closest("button");
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Gerando PDF...';
    btn.disabled = true;
    
    // Open PDF in new window
    const pdfUrl = `api/generate_client_pdf.php?client_id=${clientId}`;
    const newWindow = window.open(pdfUrl, "_blank");
    
    // Reset button after a delay
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 2000);
    
    // Check if popup was blocked
    if (!newWindow || newWindow.closed || typeof newWindow.closed == "undefined") {
        alert("Por favor, permita pop-ups para baixar o PDF ou tente novamente.");
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}

function printClientHistory(clientId) {
    // Show loading state
    const btn = event.target.closest("button");
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Preparando...';
    btn.disabled = true;
    
    // Create a new window with printable content
    const printUrl = `api/print_client_history.php?client_id=${clientId}`;
    const printWindow = window.open(printUrl, "_blank", "width=800,height=600");
    
    // Reset button after a delay
    setTimeout(() => {
        btn.innerHTML = originalText;
        btn.disabled = false;
    }, 1500);
    
    // Check if popup was blocked
    if (!printWindow || printWindow.closed || typeof printWindow.closed == "undefined") {
        alert("Por favor, permita pop-ups para imprimir ou tente novamente.");
        btn.innerHTML = originalText;
        btn.disabled = false;
    }
}
</script>

<style>
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Estilos para badges de tipo de cliente */
.badge-primary {
    background: #007bff;
    color: white;
}

.badge-secondary {
    background: #6c757d;
    color: white;
}

.badge-teacher-primary {
    background: #28a745;
    color: white;
}

.badge-teacher-secondary {
    background: #17a2b8;
    color: white;
}

/* Estilos para o modal de histórico */
#client-history-content {
    max-height: 70vh;
    overflow-y: auto;
}

/* Estilos para o modal de pagamento de dívida */
.payment-method-info {
    margin-top: 1rem;
    padding: 0.75rem;
    border-radius: var(--border-radius-small);
    background: rgba(33, 150, 243, 0.1);
    border: 1px solid rgba(33, 150, 243, 0.2);
}

.payment-method-info .alert {
    margin: 0;
    padding: 0.75rem;
    border-radius: var(--border-radius-small);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.payment-method-info .alert-info {
    background: rgba(33, 150, 243, 0.1);
    color: var(--info-color);
    border: 1px solid rgba(33, 150, 243, 0.2);
}

/* Melhorar o visual do select de método de pagamento */
#debt-payment-method {
    font-size: 0.95rem;
    padding: 0.75rem 1rem;
    border: 2px solid var(--border-color);
    border-radius: var(--border-radius-small);
    background: var(--card-background);
    transition: var(--transition);
}

#debt-payment-method:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(45, 125, 50, 0.1);
}

/* Estilo para o botão de confirmar pagamento */
.btn-warning {
    background: var(--warning-color);
    color: white;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.btn-warning:hover {
    background: #f57c00;
    transform: translateY(-1px);
    box-shadow: var(--shadow-lg);
}

/* Estilos para navegação de clientes */
.client-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 1rem;
}

.client-navigation span {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.client-navigation-buttons {
    display: flex;
    gap: 0.5rem;
}

/* Notificação Toast */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    background: var(--success-color);
    color: white;
    padding: 1rem 1.5rem;
    border-radius: var(--border-radius);
    box-shadow: var(--shadow-lg);
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.notification-content {
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.notification-content i {
    font-size: 1.25rem;
}

/* Animações */
@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}

/* Opções de pagamento */
.payment-options {
    display: flex;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 0.75rem;
    background: var(--background-color);
    border-radius: var(--border-radius-small);
}

.payment-option {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
}

.payment-option input[type="radio"] {
    margin: 0;
}

.payment-option label {
    font-weight: 500;
    cursor: pointer;
}

/* Modal grande para histórico */
.modal-large {
    max-width: 800px;
    width: 90%;
}

.modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: center;
}

/* Loading spinner */
.loading-spinner {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 3rem;
    color: var(--text-secondary);
}

.loading-spinner i {
    font-size: 2rem;
    margin-bottom: 1rem;
    color: var(--primary-color);
}

/* Cabeçalho do card com ações */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.card-header-actions {
    display: flex;
    gap: 0.5rem;
}

/* Estilos para filtros */
.filter-form {
    padding: 0.5rem;
}

.filter-actions {
    display: flex;
    gap: 1rem;
    margin-top: 1rem;
    justify-content: flex-end;
}

.filter-summary {
    background: var(--background-color);
    border-radius: var(--border-radius);
    padding: 0.75rem 1rem;
    margin-bottom: 1rem;
    border: 1px solid var(--border-color);
}

.filter-summary-content {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}

.filter-summary-title {
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 0.5rem;
}

.filter-badge {
    display: inline-flex;
    align-items: center;
    background: white;
    border: 1px solid var(--border-color);
    border-radius: 20px;
    padding: 0.25rem 0.75rem;
    font-size: 0.85rem;
}

.filter-label {
    font-weight: 600;
    color: var(--text-secondary);
    margin-right: 0.25rem;
}

.filter-value {
    color: var(--primary-color);
    font-weight: 500;
}

.filter-remove {
    margin-left: 0.5rem;
    color: var(--text-secondary);
    font-weight: bold;
    text-decoration: none;
    font-size: 1.1rem;
    line-height: 1;
}

.filter-remove:hover {
    color: var(--error-color);
}

.filter-results {
    margin-left: auto;
    font-weight: 600;
    color: var(--primary-color);
}

/* Estado vazio */
.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

/* Responsividade para filtros */
@media (max-width: 768px) {
    .grid-3 {
        grid-template-columns: 1fr;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .filter-actions .btn {
        width: 100%;
    }
    
    .filter-summary-content {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-results {
        margin-left: 0;
        margin-top: 0.5rem;
        width: 100%;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.75rem;
    }
    
    .card-header-actions {
        width: 100%;
    }
    
    .card-header-actions .btn {
        flex: 1;
    }
}
</style>

<?php include 'includes/footer.php'; ?>