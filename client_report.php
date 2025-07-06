<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

requireLogin();

$client_id = $_GET['client_id'] ?? 0;

if (!$client_id) {
    header("Location: clients.php");
    exit();
}

$database = new Database();
$conn = $database->connect();

// Get client info
$stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
$stmt->execute([$client_id]);
$client = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$client) {
    header("Location: clients.php");
    exit();
}

// Get client transactions
$stmt = $conn->prepare("SELECT * FROM client_transactions WHERE client_id = ? ORDER BY created_at DESC");
$stmt->execute([$client_id]);
$transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get client purchases
$stmt = $conn->prepare("SELECT s.*, si.*, p.name as product_name 
                       FROM sales s 
                       JOIN sale_items si ON s.id = si.sale_id 
                       JOIN products p ON si.product_id = p.id 
                       WHERE s.client_id = ? 
                       ORDER BY s.created_at DESC");
$stmt->execute([$client_id]);
$purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group purchases by sale
$grouped_purchases = [];
foreach ($purchases as $purchase) {
    $grouped_purchases[$purchase['sale_id']]['sale'] = $purchase;
    $grouped_purchases[$purchase['sale_id']]['items'][] = $purchase;
}

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-file-alt"></i>
            Relatório de Cliente: <?php echo htmlspecialchars($client['name']); ?>
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-secondary" onclick="window.print()">
                <i class="fas fa-print"></i>
                Imprimir
            </button>
            <button type="button" class="btn btn-success" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i>
                Exportar PDF
            </button>
        </div>
    </div>
    
    <!-- Client Info -->
    <div class="card">
        <div class="card-header">
            <h3>Informações do Cliente</h3>
        </div>
        
        <div class="grid grid-4">
            <div class="info-item">
                <label>Nome:</label>
                <span><?php echo htmlspecialchars($client['name']); ?></span>
            </div>
            <div class="info-item">
                <label>Tipo:</label>
                <span class="badge badge-<?php echo $client['type']; ?>">
                    <?php echo __($client['type']); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Saldo Atual:</label>
                <span class="<?php echo $client['balance'] < 0 ? 'text-danger' : 'text-success'; ?>">
                    Mt <?php echo number_format($client['balance'], 2); ?>
                </span>
            </div>
            <div class="info-item">
                <label>Dívida Atual:</label>
                <span class="<?php echo $client['debt'] > 0 ? 'text-danger' : ''; ?>">
                    Mt <?php echo number_format($client['debt'], 2); ?>
                </span>
            </div>
        </div>
        
        <?php if ($client['contact']): ?>
        <div class="info-item">
            <label>Contacto:</label>
            <span><?php echo htmlspecialchars($client['contact']); ?></span>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Purchase History -->
    <div class="card">
        <div class="card-header">
            <h3>Histórico de Compras</h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Produtos</th>
                        <th>Método Pagamento</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($grouped_purchases as $sale_id => $purchase_group): ?>
                    <tr>
                        <td>
                            <?php echo date('d/m/Y', strtotime($purchase_group['sale']['sale_date'])); ?><br>
                            <small><?php echo date('H:i', strtotime($purchase_group['sale']['sale_time'])); ?></small>
                        </td>
                        <td>
                            <?php foreach ($purchase_group['items'] as $item): ?>
                                <div class="item-line">
                                    <?php echo $item['quantity']; ?>x <?php echo htmlspecialchars($item['product_name']); ?>
                                    (Mt <?php echo number_format($item['unit_price'], 2); ?>)
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td>
                            <span class="badge badge-<?php echo $purchase_group['sale']['payment_method']; ?>">
                                <?php echo __($purchase_group['sale']['payment_method']); ?>
                            </span>
                        </td>
                        <td>Mt <?php echo number_format($purchase_group['sale']['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($grouped_purchases)): ?>
                    <tr>
                        <td colspan="4" class="text-center">Nenhuma compra registrada</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Transaction History -->
    <div class="card">
        <div class="card-header">
            <h3>Histórico de Transações</h3>
        </div>
        
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Data/Hora</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>Descrição</th>
                        <th>Saldo Após</th>
                        <th>Dívida Após</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'])); ?></td>
                        <td>
                            <span class="badge badge-<?php echo $transaction['type']; ?>">
                                <?php echo ucfirst($transaction['type']); ?>
                            </span>
                        </td>
                        <td>Mt <?php echo number_format($transaction['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                        <td>
                            <span class="<?php echo $transaction['balance_after'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                Mt <?php echo number_format($transaction['balance_after'], 2); ?>
                            </span>
                        </td>
                        <td>
                            <span class="<?php echo $transaction['debt_after'] > 0 ? 'text-danger' : ''; ?>">
                                Mt <?php echo number_format($transaction['debt_after'], 2); ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="6" class="text-center">Nenhuma transação registrada</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function exportToPDF() {
    window.open(`export_client_pdf.php?client_id=<?php echo $client_id; ?>`, '_blank');
}
</script>

<style>
.info-item {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.info-item label {
    font-weight: 600;
    color: var(--text-secondary);
    font-size: 0.875rem;
}

.info-item span {
    font-weight: 500;
}

.item-line {
    padding: 0.25rem 0;
    border-bottom: 1px solid var(--border-color);
}

.item-line:last-child {
    border-bottom: none;
}

@media print {
    .card-header .d-flex {
        display: none !important;
    }
}
</style>

<?php include 'includes/footer.php'; ?>