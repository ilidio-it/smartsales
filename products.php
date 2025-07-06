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
            case 'add_product':
                $stmt = $conn->prepare("INSERT INTO products (name, category_id, selling_price, buying_price, stock_primary, stock_secondary, min_stock) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['selling_price'],
                    $_POST['buying_price'],
                    $_POST['stock_primary'],
                    $_POST['stock_secondary'],
                    $_POST['min_stock']
                ]);
                
                // Log the action
                require_once 'api/log_action.php';
                logAction(
                    'product', 
                    "Produto adicionado: " . $_POST['name'], 
                    $_POST['selling_price'], 
                    null, 
                    $_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']
                );
                
                $success_message = 'Produto adicionado com sucesso!';
                break;
                
            case 'update_product':
                $stmt = $conn->prepare("UPDATE products SET name = ?, category_id = ?, selling_price = ?, buying_price = ?, 
                                       stock_primary = ?, stock_secondary = ?, min_stock = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['name'],
                    $_POST['category_id'],
                    $_POST['selling_price'],
                    $_POST['buying_price'],
                    $_POST['stock_primary'],
                    $_POST['stock_secondary'],
                    $_POST['min_stock'],
                    $_POST['product_id']
                ]);
                
                // Log the action
                require_once 'api/log_action.php';
                logAction(
                    'product', 
                    "Produto atualizado: " . $_POST['name'], 
                    $_POST['selling_price'], 
                    null, 
                    $_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']
                );
                
                $success_message = 'Produto atualizado com sucesso!';
                break;
                
            case 'delete_product':
                $stmt = $conn->prepare("UPDATE products SET status = 'inactive' WHERE id = ?");
                $stmt->execute([$_POST['product_id']]);
                
                // Get product name for log
                $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$_POST['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Log the action
                require_once 'api/log_action.php';
                logAction(
                    'product', 
                    "Produto removido: " . $product['name'], 
                    null, 
                    null, 
                    $_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']
                );
                
                $success_message = 'Produto removido com sucesso!';
                break;
                
            case 'add_category':
                $stmt = $conn->prepare("INSERT INTO product_categories (name, description) VALUES (?, ?)");
                $stmt->execute([$_POST['name'], $_POST['description']]);
                $success_message = 'Categoria adicionada com sucesso!';
                break;
                
            case 'add_stock':
                // Get product info for logging
                $stmt = $conn->prepare("SELECT name FROM products WHERE id = ?");
                $stmt->execute([$_POST['product_id']]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Determine which stock to update
                $stock_field = $_POST['stock_type'] === 'primary' ? 'stock_primary' : 'stock_secondary';
                
                // Update stock
                $stmt = $conn->prepare("UPDATE products SET $stock_field = $stock_field + ? WHERE id = ?");
                $stmt->execute([$_POST['quantity'], $_POST['product_id']]);
                
                // Record stock movement
                $stmt = $conn->prepare("INSERT INTO stock_movements (product_id, canteen_type, type, quantity, reason, user_id) 
                                       VALUES (?, ?, 'in', ?, ?, ?)");
                $stmt->execute([
                    $_POST['product_id'],
                    $_POST['stock_type'],
                    $_POST['quantity'],
                    $_POST['reason'] ?: 'Adição de stock',
                    $_SESSION['user_id']
                ]);
                
                // Log the action
                require_once 'api/log_action.php';
                logAction(
                    'stock', 
                    "Adição de stock: " . $product['name'] . " - " . $_POST['quantity'] . " unidades (" . ucfirst($_POST['stock_type']) . ")", 
                    null, 
                    null, 
                    $_POST['stock_type']
                );
                
                $success_message = 'Stock adicionado com sucesso!';
                break;
        }
    }
}

// Get filter parameters
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';
$stock_filter = isset($_GET['stock']) ? $_GET['stock'] : 'all';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Build query conditions
$conditions = ["p.status = 'active'"];
$params = [];

if ($category_filter !== 'all') {
    $conditions[] = "p.category_id = ?";
    $params[] = $category_filter;
}

if ($stock_filter === 'low_primary') {
    $conditions[] = "p.stock_primary <= p.min_stock AND p.stock_primary > 0";
} elseif ($stock_filter === 'low_secondary') {
    $conditions[] = "p.stock_secondary <= p.min_stock AND p.stock_secondary > 0";
} elseif ($stock_filter === 'out_primary') {
    $conditions[] = "p.stock_primary = 0";
} elseif ($stock_filter === 'out_secondary') {
    $conditions[] = "p.stock_secondary = 0";
}

if (!empty($search_term)) {
    $conditions[] = "p.name LIKE ?";
    $params[] = "%$search_term%";
}

// Build the final query
$where_clause = implode(' AND ', $conditions);
$query = "SELECT p.*, pc.name as category_name FROM products p 
          LEFT JOIN product_categories pc ON p.category_id = pc.id
          WHERE $where_clause 
          ORDER BY p.name";

// Get products
$stmt = $conn->prepare($query);
$stmt->execute($params);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$stmt = $conn->prepare("SELECT * FROM product_categories ORDER BY name");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count products by category for filter badges
$stmt = $conn->prepare("SELECT pc.id, pc.name, COUNT(p.id) as count 
                       FROM product_categories pc
                       LEFT JOIN products p ON pc.id = p.category_id AND p.status = 'active'
                       GROUP BY pc.id, pc.name
                       ORDER BY pc.name");
$stmt->execute();
$category_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count low stock products
$stmt = $conn->prepare("SELECT 
                          COUNT(CASE WHEN stock_primary <= min_stock AND stock_primary > 0 THEN 1 END) as low_primary,
                          COUNT(CASE WHEN stock_secondary <= min_stock AND stock_secondary > 0 THEN 1 END) as low_secondary,
                          COUNT(CASE WHEN stock_primary = 0 THEN 1 END) as out_primary,
                          COUNT(CASE WHEN stock_secondary = 0 THEN 1 END) as out_secondary
                       FROM products
                       WHERE status = 'active'");
$stmt->execute();
$stock_counts = $stmt->fetch(PDO::FETCH_ASSOC);

include 'includes/header.php';
?>

<div class="container">
    <div class="card-header">
        <h1 class="card-title">
            <i class="fas fa-box"></i>
            <?php echo __('products'); ?>
        </h1>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary" data-modal="add-product-modal">
                <i class="fas fa-plus"></i>
                <?php echo __('add_product'); ?>
            </button>
            <button type="button" class="btn btn-success" data-modal="add-stock-modal">
                <i class="fas fa-cubes"></i>
                Adicionar Stock
            </button>
            <button type="button" class="btn btn-secondary" data-modal="add-category-modal">
                <i class="fas fa-folder-plus"></i>
                Adicionar Categoria
            </button>
        </div>
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
                <div class="grid grid-3">
                    <div class="form-group">
                        <label class="form-label">Buscar Produto</label>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Nome do produto..." value="<?php echo htmlspecialchars($search_term); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Categoria</label>
                        <select name="category" class="form-control form-select">
                            <option value="all">Todas as Categorias</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?> 
                                    <?php 
                                    $count = 0;
                                    foreach ($category_counts as $cat_count) {
                                        if ($cat_count['id'] == $category['id']) {
                                            $count = $cat_count['count'];
                                            break;
                                        }
                                    }
                                    echo "($count)";
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Status de Stock</label>
                        <select name="stock" class="form-control form-select">
                            <option value="all" <?php echo $stock_filter == 'all' ? 'selected' : ''; ?>>Todos</option>
                            <option value="low_primary" <?php echo $stock_filter == 'low_primary' ? 'selected' : ''; ?>>
                                Stock Baixo - Primária (<?php echo $stock_counts['low_primary']; ?>)
                            </option>
                            <option value="low_secondary" <?php echo $stock_filter == 'low_secondary' ? 'selected' : ''; ?>>
                                Stock Baixo - Secundária (<?php echo $stock_counts['low_secondary']; ?>)
                            </option>
                            <option value="out_primary" <?php echo $stock_filter == 'out_primary' ? 'selected' : ''; ?>>
                                Sem Stock - Primária (<?php echo $stock_counts['out_primary']; ?>)
                            </option>
                            <option value="out_secondary" <?php echo $stock_filter == 'out_secondary' ? 'selected' : ''; ?>>
                                Sem Stock - Secundária (<?php echo $stock_counts['out_secondary']; ?>)
                            </option>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Aplicar Filtros
                    </button>
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fas fa-sync-alt"></i>
                        Limpar Filtros
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Resumo dos Filtros Aplicados -->
    <?php if ($category_filter != 'all' || $stock_filter != 'all' || !empty($search_term)): ?>
    <div class="filter-summary">
        <div class="filter-summary-content">
            <span class="filter-summary-title">Filtros aplicados:</span>
            
            <?php if (!empty($search_term)): ?>
            <span class="filter-badge">
                <span class="filter-label">Busca:</span>
                <span class="filter-value"><?php echo htmlspecialchars($search_term); ?></span>
                <a href="<?php echo '?category=' . $category_filter . '&stock=' . $stock_filter; ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <?php if ($category_filter != 'all'): ?>
            <span class="filter-badge">
                <span class="filter-label">Categoria:</span>
                <span class="filter-value">
                    <?php 
                    foreach ($categories as $category) {
                        if ($category['id'] == $category_filter) {
                            echo htmlspecialchars($category['name']);
                            break;
                        }
                    }
                    ?>
                </span>
                <a href="<?php echo '?stock=' . $stock_filter . '&search=' . urlencode($search_term); ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <?php if ($stock_filter != 'all'): ?>
            <span class="filter-badge">
                <span class="filter-label">Stock:</span>
                <span class="filter-value">
                    <?php 
                    $stock_labels = [
                        'low_primary' => 'Stock Baixo - Primária',
                        'low_secondary' => 'Stock Baixo - Secundária',
                        'out_primary' => 'Sem Stock - Primária',
                        'out_secondary' => 'Sem Stock - Secundária'
                    ];
                    echo $stock_labels[$stock_filter] ?? $stock_filter;
                    ?>
                </span>
                <a href="<?php echo '?category=' . $category_filter . '&search=' . urlencode($search_term); ?>" class="filter-remove">×</a>
            </span>
            <?php endif; ?>
            
            <span class="filter-results">
                <?php echo count($products); ?> produtos encontrados
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="card">
        <div class="table-container">
            <table class="table" id="products-table">
                <thead>
                    <tr>
                        <th data-sort="name"><?php echo __('name'); ?></th>
                        <th data-sort="category_name">Categoria</th>
                        <th data-sort="selling_price"><?php echo __('selling_price'); ?></th>
                        <th data-sort="buying_price"><?php echo __('buying_price'); ?></th>
                        <th data-sort="profit"><?php echo __('profit'); ?></th>
                        <th data-sort="stock_primary"><?php echo __('stock_primary'); ?></th>
                        <th data-sort="stock_secondary"><?php echo __('stock_secondary'); ?></th>
                        <th><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr>
                        <td colspan="8" class="text-center">
                            <div class="empty-state">
                                <i class="fas fa-search" style="font-size: 3rem; color: var(--text-secondary); margin-bottom: 1rem;"></i>
                                <h4>Nenhum produto encontrado</h4>
                                <p>Tente ajustar os filtros ou adicione novos produtos.</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($products as $product): ?>
                    <tr>
                        <td data-sort="name"><?php echo htmlspecialchars($product['name']); ?></td>
                        <td data-sort="category_name">
                            <?php if ($product['category_name']): ?>
                            <span class="category-badge">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </span>
                            <?php else: ?>
                            <span class="category-badge category-none">Sem categoria</span>
                            <?php endif; ?>
                        </td>
                        <td data-sort="selling_price">Mt <?php echo number_format($product['selling_price'], 2); ?></td>
                        <td data-sort="buying_price">Mt <?php echo number_format($product['buying_price'], 2); ?></td>
                        <td data-sort="profit">
                            <?php 
                            $profit = $product['selling_price'] - $product['buying_price'];
                            $profit_percentage = ($product['buying_price'] > 0) ? ($profit / $product['buying_price']) * 100 : 0;
                            ?>
                            <span class="text-success">
                                Mt <?php echo number_format($profit, 2); ?> 
                                (<?php echo number_format($profit_percentage, 1); ?>%)
                            </span>
                        </td>
                        <td data-sort="stock_primary">
                            <?php if ($product['stock_primary'] <= $product['min_stock'] && $product['stock_primary'] > 0): ?>
                                <span class="stock-badge stock-low"><?php echo $product['stock_primary']; ?></span>
                            <?php elseif ($product['stock_primary'] == 0): ?>
                                <span class="stock-badge stock-out"><?php echo $product['stock_primary']; ?></span>
                            <?php else: ?>
                                <span class="stock-badge stock-ok"><?php echo $product['stock_primary']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td data-sort="stock_secondary">
                            <?php if ($product['stock_secondary'] <= $product['min_stock'] && $product['stock_secondary'] > 0): ?>
                                <span class="stock-badge stock-low"><?php echo $product['stock_secondary']; ?></span>
                            <?php elseif ($product['stock_secondary'] == 0): ?>
                                <span class="stock-badge stock-out"><?php echo $product['stock_secondary']; ?></span>
                            <?php else: ?>
                                <span class="stock-badge stock-ok"><?php echo $product['stock_secondary']; ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="action-btn action-btn-edit" 
                                        onclick="editProduct(<?php echo $product['id']; ?>)"
                                        title="Editar Produto">
                                    <i class="fas fa-edit"></i>
                                </button>
                                
                                <button type="button" class="action-btn action-btn-add" 
                                        onclick="addStock(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>')"
                                        title="Adicionar Stock">
                                    <i class="fas fa-plus"></i>
                                </button>
                                
                                <form method="POST" style="display: inline;" 
                                      onsubmit="return confirm('<?php echo __('confirm_delete'); ?>')">
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="product_id" value="<?php echo $product['id']; ?>">
                                    <button type="submit" class="action-btn action-btn-delete" title="Remover Produto">
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

<!-- Add Product Modal -->
<div id="add-product-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3><?php echo __('add_product'); ?></h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_product">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('product_name'); ?></label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="category_id" class="form-control form-select">
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('selling_price'); ?> (Mt)</label>
                    <input type="number" name="selling_price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('buying_price'); ?> (Mt)</label>
                    <input type="number" name="buying_price" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label"><?php echo __('stock_primary'); ?></label>
                    <input type="number" name="stock_primary" class="form-control" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('stock_secondary'); ?></label>
                    <input type="number" name="stock_secondary" class="form-control" value="0">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('min_stock'); ?></label>
                    <input type="number" name="min_stock" class="form-control" value="5">
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal - Melhorado com navegação entre produtos -->
<div id="edit-product-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>Editar Produto</h3>
                <div class="product-navigation">
                    <span id="current-product-position">Produto 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <form id="edit-product-form" method="POST">
            <input type="hidden" name="action" value="update_product">
            <input type="hidden" name="product_id" id="edit-product-id">
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('product_name'); ?></label>
                    <input type="text" name="name" id="edit-product-name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Categoria</label>
                    <select name="category_id" id="edit-product-category" class="form-control form-select">
                        <option value="">Selecione uma categoria</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label"><?php echo __('selling_price'); ?> (Mt)</label>
                    <input type="number" name="selling_price" id="edit-product-selling-price" class="form-control" step="0.01" min="0" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('buying_price'); ?> (Mt)</label>
                    <input type="number" name="buying_price" id="edit-product-buying-price" class="form-control" step="0.01" min="0" required>
                </div>
            </div>
            
            <div class="grid grid-3">
                <div class="form-group">
                    <label class="form-label"><?php echo __('stock_primary'); ?></label>
                    <input type="number" name="stock_primary" id="edit-product-stock-primary" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('stock_secondary'); ?></label>
                    <input type="number" name="stock_secondary" id="edit-product-stock-secondary" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label"><?php echo __('min_stock'); ?></label>
                    <input type="number" name="min_stock" id="edit-product-min-stock" class="form-control">
                </div>
            </div>
            
            <div class="d-flex gap-2 justify-between">
                <div class="product-navigation-buttons">
                    <button type="button" id="prev-product" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" id="next-product" class="btn btn-secondary">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                    <button type="button" id="save-product-btn" class="btn btn-primary"><?php echo __('save'); ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Stock Modal -->
<div id="add-stock-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <div class="d-flex justify-between align-center w-full">
                <h3>Adicionar Stock</h3>
                <div class="product-navigation">
                    <span id="stock-product-position">Produto 1 de 10</span>
                </div>
                <button type="button" class="modal-close">&times;</button>
            </div>
        </div>
        
        <form id="add-stock-form" method="POST">
            <input type="hidden" name="action" value="add_stock">
            <input type="hidden" name="product_id" id="stock-product-id">
            
            <div class="form-group">
                <label class="form-label">Produto</label>
                <input type="text" id="stock-product-name" class="form-control" readonly>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Stock Atual Primária</label>
                    <input type="text" id="stock-current-primary" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Stock Atual Secundária</label>
                    <input type="text" id="stock-current-secondary" class="form-control" readonly>
                </div>
            </div>
            
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Tipo de Stock</label>
                    <select name="stock_type" id="stock-type" class="form-control form-select" required>
                        <option value="primary">Primária</option>
                        <option value="secondary">Secundária</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Quantidade a Adicionar</label>
                    <input type="number" name="quantity" id="stock-quantity" class="form-control" min="1" required>
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Motivo (Opcional)</label>
                <input type="text" name="reason" class="form-control" placeholder="Ex: Reposição de stock, Nova entrega, etc.">
            </div>
            
            <div class="d-flex gap-2 justify-between">
                <div class="product-navigation-buttons">
                    <button type="button" id="prev-stock-product" class="btn btn-secondary">
                        <i class="fas fa-chevron-left"></i> Anterior
                    </button>
                    <button type="button" id="next-stock-product" class="btn btn-secondary">
                        Próximo <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-success">Adicionar Stock</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="add-category-modal" class="modal-overlay" style="display: none;">
    <div class="modal">
        <div class="modal-header">
            <h3>Adicionar Categoria</h3>
            <button type="button" class="modal-close">&times;</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_category">
            
            <div class="form-group">
                <label class="form-label">Nome da Categoria</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Descrição</label>
                <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            
            <div class="d-flex gap-2 justify-end">
                <button type="button" class="btn btn-secondary modal-close"><?php echo __('cancel'); ?></button>
                <button type="submit" class="btn btn-primary"><?php echo __('save'); ?></button>
            </div>
        </form>
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
// Array global para armazenar todos os produtos
let allProducts = <?php echo json_encode($products); ?>;
let currentProductIndex = 0;
let currentStockProductIndex = 0;

// Função para editar produto com navegação
function editProduct(productId) {
    // Encontrar o produto pelo ID
    const productIndex = allProducts.findIndex(p => p.id == productId);
    if (productIndex === -1) return;
    
    // Atualizar índice atual
    currentProductIndex = productIndex;
    
    // Carregar dados do produto
    loadProductData(currentProductIndex);
    
    // Abrir modal
    window.canteenApp.openModal('edit-product-modal');
}

// Função para carregar dados do produto no formulário
function loadProductData(index) {
    if (index < 0 || index >= allProducts.length) return;
    
    const product = allProducts[index];
    
    // Atualizar posição atual
    document.getElementById('current-product-position').textContent = 
        `Produto ${index + 1} de ${allProducts.length}`;
    
    // Preencher formulário
    document.getElementById('edit-product-id').value = product.id;
    document.getElementById('edit-product-name').value = product.name;
    document.getElementById('edit-product-category').value = product.category_id || '';
    document.getElementById('edit-product-selling-price').value = product.selling_price;
    document.getElementById('edit-product-buying-price').value = product.buying_price;
    document.getElementById('edit-product-stock-primary').value = product.stock_primary;
    document.getElementById('edit-product-stock-secondary').value = product.stock_secondary;
    document.getElementById('edit-product-min-stock').value = product.min_stock;
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-product').disabled = index === 0;
    document.getElementById('next-product').disabled = index === allProducts.length - 1;
}

// Função para adicionar stock com navegação
function addStock(productId, productName) {
    // Encontrar o produto pelo ID
    const productIndex = allProducts.findIndex(p => p.id == productId);
    if (productIndex === -1) return;
    
    // Atualizar índice atual
    currentStockProductIndex = productIndex;
    
    // Carregar dados do produto
    loadStockProductData(currentStockProductIndex);
    
    // Abrir modal
    window.canteenApp.openModal('add-stock-modal');
}

// Função para carregar dados do produto no formulário de stock
function loadStockProductData(index) {
    if (index < 0 || index >= allProducts.length) return;
    
    const product = allProducts[index];
    
    // Atualizar posição atual
    document.getElementById('stock-product-position').textContent = 
        `Produto ${index + 1} de ${allProducts.length}`;
    
    // Preencher formulário
    document.getElementById('stock-product-id').value = product.id;
    document.getElementById('stock-product-name').value = product.name;
    document.getElementById('stock-current-primary').value = product.stock_primary;
    document.getElementById('stock-current-secondary').value = product.stock_secondary;
    
    // Habilitar/desabilitar botões de navegação
    document.getElementById('prev-stock-product').disabled = index === 0;
    document.getElementById('next-stock-product').disabled = index === allProducts.length - 1;
}

// Navegação entre produtos - Edição
document.getElementById('prev-product').addEventListener('click', function() {
    // Salvar alterações do produto atual antes de navegar
    saveCurrentProduct(function() {
        if (currentProductIndex > 0) {
            currentProductIndex--;
            loadProductData(currentProductIndex);
        }
    });
});

document.getElementById('next-product').addEventListener('click', function() {
    // Salvar alterações do produto atual antes de navegar
    saveCurrentProduct(function() {
        if (currentProductIndex < allProducts.length - 1) {
            currentProductIndex++;
            loadProductData(currentProductIndex);
        }
    });
});

// Navegação entre produtos - Adição de Stock
document.getElementById('prev-stock-product').addEventListener('click', function() {
    if (currentStockProductIndex > 0) {
        currentStockProductIndex--;
        loadStockProductData(currentStockProductIndex);
    }
});

document.getElementById('next-stock-product').addEventListener('click', function() {
    if (currentStockProductIndex < allProducts.length - 1) {
        currentStockProductIndex++;
        loadStockProductData(currentStockProductIndex);
    }
});

// Salvar produto via AJAX
document.getElementById('save-product-btn').addEventListener('click', function() {
    saveCurrentProduct();
});

function saveCurrentProduct(callback) {
    const form = document.getElementById('edit-product-form');
    const formData = new FormData(form);
    
    // Mostrar indicador de carregamento
    document.getElementById('save-product-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Salvando...';
    document.getElementById('save-product-btn').disabled = true;
    
    fetch('ajax_update_product.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar o produto no array
            const updatedProduct = {
                id: formData.get('product_id'),
                name: formData.get('name'),
                category_id: formData.get('category_id'),
                selling_price: formData.get('selling_price'),
                buying_price: formData.get('buying_price'),
                stock_primary: formData.get('stock_primary'),
                stock_secondary: formData.get('stock_secondary'),
                min_stock: formData.get('min_stock'),
                category_name: data.category_name // Vem da resposta do servidor
            };
            
            allProducts[currentProductIndex] = {...allProducts[currentProductIndex], ...updatedProduct};
            
            // Mostrar notificação
            showNotification('Produto atualizado com sucesso!');
            
            // Atualizar a tabela sem recarregar a página
            updateProductInTable(updatedProduct);
            
            // Executar callback se fornecido (para navegação)
            if (typeof callback === 'function') {
                callback();
            }
        } else {
            alert('Erro ao salvar produto: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar produto. Verifique o console para mais detalhes.');
    })
    .finally(() => {
        // Restaurar botão
        document.getElementById('save-product-btn').innerHTML = 'Salvar';
        document.getElementById('save-product-btn').disabled = false;
    });
}

// Atualizar produto na tabela sem recarregar a página
function updateProductInTable(product) {
    const table = document.getElementById('products-table');
    const rows = table.querySelectorAll('tbody tr');
    
    for (let i = 0; i < rows.length; i++) {
        const productIdInput = rows[i].querySelector('input[name="product_id"]');
        if (productIdInput && productIdInput.value == product.id) {
            const cells = rows[i].querySelectorAll('td');
            
            // Atualizar células da tabela
            cells[0].textContent = product.name;
            
            // Atualizar categoria com badge
            if (product.category_name) {
                cells[1].innerHTML = `<span class="category-badge">${product.category_name}</span>`;
            } else {
                cells[1].innerHTML = `<span class="category-badge category-none">Sem categoria</span>`;
            }
            
            cells[2].textContent = 'Mt ' + parseFloat(product.selling_price).toFixed(2);
            cells[3].textContent = 'Mt ' + parseFloat(product.buying_price).toFixed(2);
            
            // Calcular lucro
            const profit = product.selling_price - product.buying_price;
            const profitPercentage = (product.buying_price > 0) ? (profit / product.buying_price) * 100 : 0;
            cells[4].innerHTML = `<span class="text-success">Mt ${profit.toFixed(2)} (${profitPercentage.toFixed(1)}%)</span>`;
            
            // Atualizar stock com badges
            let primaryStockHTML = '';
            if (product.stock_primary <= product.min_stock && product.stock_primary > 0) {
                primaryStockHTML = `<span class="stock-badge stock-low">${product.stock_primary}</span>`;
            } else if (product.stock_primary == 0) {
                primaryStockHTML = `<span class="stock-badge stock-out">${product.stock_primary}</span>`;
            } else {
                primaryStockHTML = `<span class="stock-badge stock-ok">${product.stock_primary}</span>`;
            }
            cells[5].innerHTML = primaryStockHTML;
            
            let secondaryStockHTML = '';
            if (product.stock_secondary <= product.min_stock && product.stock_secondary > 0) {
                secondaryStockHTML = `<span class="stock-badge stock-low">${product.stock_secondary}</span>`;
            } else if (product.stock_secondary == 0) {
                secondaryStockHTML = `<span class="stock-badge stock-out">${product.stock_secondary}</span>`;
            } else {
                secondaryStockHTML = `<span class="stock-badge stock-ok">${product.stock_secondary}</span>`;
            }
            cells[6].innerHTML = secondaryStockHTML;
            
            break;
        }
    }
}

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
</script>

<style>
/* Estilos para navegação de produtos */
.product-navigation {
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 1rem;
}

.product-navigation span {
    font-size: 0.9rem;
    color: var(--text-secondary);
    font-weight: 500;
}

.product-navigation-buttons {
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

/* Ajustes para o modal */
.modal {
    max-width: 600px;
    width: 90%;
}

.modal-header {
    padding: 1rem 1.5rem;
}

.modal-header h3 {
    margin: 0;
}

/* Botões de navegação */
#prev-product, #next-product, #prev-stock-product, #next-stock-product {
    min-width: 100px;
}

#prev-product:disabled, #next-product:disabled, 
#prev-stock-product:disabled, #next-stock-product:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Ajustes para responsividade */
@media (max-width: 768px) {
    .product-navigation-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .d-flex.justify-between {
        flex-direction: column;
        gap: 1rem;
    }
    
    .d-flex.gap-2 {
        width: 100%;
    }
    
    #prev-product, #next-product, #prev-stock-product, #next-stock-product, 
    .btn-secondary, .btn-primary, .btn-success {
        width: 100%;
    }
}

/* Classe utilitária para largura total */
.w-full {
    width: 100%;
}

/* Classe utilitária para alinhamento entre itens */
.justify-between {
    justify-content: space-between;
}

/* Classe utilitária para alinhamento central vertical */
.align-center {
    align-items: center;
}

/* Estilos para botões de ação */
.action-buttons {
    display: flex;
    gap: 4px;
    align-items: center;
    justify-content: center;
    flex-wrap: nowrap;
}

.action-btn {
    width: 44px;
    height: 44px;
    padding: 0;
    border: none;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: var(--transition);
    font-size: 0.9rem;
    margin: 0 2px;
    touch-action: manipulation;
}

.action-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

.action-btn:active {
    transform: translateY(0);
}

.action-btn-edit {
    background: #ff9800;
    color: white;
}

.action-btn-edit:hover {
    background: #f57c00;
}

.action-btn-add {
    background: #4caf50;
    color: white;
}

.action-btn-add:hover {
    background: #388e3c;
}

.action-btn-delete {
    background: #f44336;
    color: white;
}

.action-btn-delete:hover {
    background: #d32f2f;
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

/* Estilos para badges de categoria */
.category-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 500;
    background: var(--primary-color);
    color: white;
}

.category-none {
    background: var(--text-secondary);
}

/* Estilos para badges de stock */
.stock-badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
    text-align: center;
    min-width: 40px;
}

.stock-ok {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #a5d6a7;
}

.stock-low {
    background: #fff3e0;
    color: #e65100;
    border: 1px solid #ffcc80;
}

.stock-out {
    background: #ffebee;
    color: #c62828;
    border: 1px solid #ef9a9a;
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
}
</style>

<?php include 'includes/footer.php'; ?>