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
            case 'complete_request':
                $request_id = $_POST['request_id'];
                
                // Get request details
                $stmt = $conn->prepare("SELECT r.*, s.*, c.name as client_name FROM requests r 
                                       JOIN sales s ON r.sale_id = s.id 
                                       JOIN clients c ON r.client_id = c.id
                                       WHERE r.id = ?");
                $stmt->execute([$request_id]);
                $request = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($request) {
                    // Update stock
                    $stmt = $conn->prepare("SELECT si.*, p.* FROM sale_items si 
                                           JOIN products p ON si.product_id = p.id 
                                           WHERE si.sale_id = ?");
                    $stmt->execute([$request['sale_id']]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($items as $item) {
                        $stock_field = $request['canteen_type'] === 'primary' ? 'stock_primary' : 'stock_secondary';
                        
                        $stmt = $conn->prepare("UPDATE products SET $stock_field = $stock_field - ? WHERE id = ?");
                        $stmt->execute([$item['quantity'], $item['product_id']]);
                        
                        // Record stock movement
                        $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, canteen_type, type, quantity, reason, user_id) 
                                               VALUES (?, ?, 'out', ?, 'Request completed', ?)");
                        $stmt->execute([
                            $item['product_id'],
                            $request['canteen_type'],
                            $item['quantity'],
                            $_SESSION['user_id']
                        ]);
                    }
                    
                    // Mark request as completed
                    $stmt = $conn->prepare("UPDATE requests SET status = 'completed', completed_at = NOW() WHERE id = ?");
                    $stmt->execute([$request_id]);
                    
                    $success_message = 'Pedido entregue com sucesso!';
                }
                break;
                
            case 'cancel_request':
                $stmt = $conn->prepare("UPDATE requests SET status = 'cancelled' WHERE id = ?");
                $stmt->execute([$_POST['request_id']]);
                $success_message = 'Pedido cancelado com sucesso!';
                break;
        }
    }
}

// Build WHERE condition based on user's canteen access
$canteen_condition = '';
if ($_SESSION['canteen_type'] !== 'both') {
    $canteen_condition = " AND s.canteen_type = '{$_SESSION['canteen_type']}'";
}

// Get pending requests
$stmt = $conn->prepare("SELECT r.*, c.name as client_name, s.total_amount, s.sale_date, s.sale_time, s.canteen_type
                       FROM requests r 
                       JOIN clients c ON r.client_id = c.id 
                       JOIN sales s ON r.sale_id = s.id 
                       WHERE r.status = 'pending' $canteen_condition
                       ORDER BY r.created_at ASC");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-clock"></i>
            Pedidos/Reservas
        </h1>
        <div class="d-flex gap-2">
            <span class="badge badge-info">
                <?php echo count($requests); ?> pedidos pendentes
            </span>
        </div>
    </div>
    
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h3>Lista de Pedidos Pendentes</h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Cliente</th>
                        <th>Cantina</th>
                        <th>Total</th>
                        <th>Produtos</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requests as $request): ?>
                    <?php
                    // Get request items
                    $stmt = $conn->prepare("SELECT si.*, p.name as product_name 
                                           FROM sale_items si 
                                           JOIN products p ON si.product_id = p.id 
                                           WHERE si.sale_id = ?");
                    $stmt->execute([$request['sale_id']]);
                    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    ?>
                    <tr>
                        <td>
                            <?php echo date('d/m/Y', strtotime($request['sale_date'])); ?><br>
                            <small><?php echo date('H:i', strtotime($request['sale_time'])); ?></small>
                        </td>
                        <td>
                            <strong><?php echo htmlspecialchars($request['client_name']); ?></strong><br>
                            <small>Pedido #<?php echo $request['id']; ?></small>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $request['canteen_type']; ?>">
                                <?php echo ucfirst($request['canteen_type']); ?>
                            </span>
                        </td>
                        <td>
                            <strong>Mt <?php echo number_format($request['total_amount'], 2); ?></strong>
                        </td>
                        <td>
                            <div class="products-list">
                                <?php foreach ($items as $item): ?>
                                    <div class="item-line">
                                        <span class="item-quantity"><?php echo $item['quantity']; ?>x</span>
                                        <span class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></span>
                                        <span class="item-price">Mt <?php echo number_format($item['unit_price'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="complete_request">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-success" 
                                            onclick="return confirm('Confirmar entrega do pedido para <?php echo htmlspecialchars($request['client_name']); ?>?')"
                                            title="Entregar Pedido">
                                        <i class="fas fa-check"></i> Entregar
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="cancel_request">
                                    <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                    <button type="submit" class="btn btn-small btn-danger" 
                                            onclick="return confirm('Cancelar este pedido?')"
                                            title="Cancelar Pedido">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($requests)): ?>
                    <tr>
                        <td colspan="6" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-clipboard-check" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4>Nenhum pedido pendente</h4>
                                <p>Todos os pedidos foram processados ou não há pedidos no momento.</p>
                            </div>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Instructions Card -->
    <div class="card">
        <div class="card-header">
            <h3>Como Funciona</h3>
        </div>
        
        <div class="instructions-grid">
            <div class="instruction-item">
                <div class="instruction-icon">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <div class="instruction-content">
                    <h4>1. Cliente Faz Pedido</h4>
                    <p>No sistema POS, o cliente escolhe produtos e seleciona "Fazer Reserva"</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="instruction-content">
                    <h4>2. Pedido Fica Pendente</h4>
                    <p>O pedido aparece nesta lista aguardando a retirada do cliente</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="instruction-content">
                    <h4>3. Cliente Retira</h4>
                    <p>Quando o cliente vem buscar, clique em "Entregar" para finalizar</p>
                </div>
            </div>
            
            <div class="instruction-item">
                <div class="instruction-icon">
                    <i class="fas fa-box"></i>
                </div>
                <div class="instruction-content">
                    <h4>4. Stock Atualizado</h4>
                    <p>O stock é deduzido automaticamente quando o pedido é entregue</p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.products-list {
    max-width: 250px;
}

.item-line {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.25rem 0;
    border-bottom: 1px solid var(--border-color);
    font-size: 0.875rem;
}

.item-line:last-child {
    border-bottom: none;
}

.item-quantity {
    font-weight: 600;
    color: var(--primary-color);
    min-width: 30px;
}

.item-name {
    flex: 1;
    margin: 0 0.5rem;
}

.item-price {
    font-weight: 600;
    color: var(--success-color);
}

.action-buttons {
    display: flex;
    gap: 0.25rem;
    flex-wrap: wrap;
}

.action-buttons .btn {
    min-width: 35px;
    padding: 0.5rem;
    font-size: 0.875rem;
}

.empty-state {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-secondary);
}

.empty-state h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
}

.instructions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.instruction-item {
    display: flex;
    gap: 1rem;
    padding: 1rem;
    background: var(--background-color);
    border-radius: var(--border-radius);
    border-left: 4px solid var(--primary-color);
}

.instruction-icon {
    flex-shrink: 0;
    width: 50px;
    height: 50px;
    background: var(--primary-color);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

.instruction-content h4 {
    margin-bottom: 0.5rem;
    color: var(--text-primary);
    font-size: 1rem;
}

.instruction-content p {
    margin: 0;
    font-size: 0.875rem;
    color: var(--text-secondary);
    line-height: 1.4;
}

.badge-info {
    background: var(--primary-color);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 20px;
    font-size: 0.875rem;
    font-weight: 600;
}

.badge-primary {
    background: #fef3c7;
    color: #92400e;
}

.badge-secondary {
    background: #dbeafe;
    color: #1e40af;
}
</style>

<?php include 'includes/footer.php'; ?>