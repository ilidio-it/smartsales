<?php
require_once 'config/database.php';
require_once 'config/session.php';

header('Content-Type: application/json');

// Verificar se o usuário está logado
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

// Validar dados
if (!isset($_POST['product_id']) || !isset($_POST['name']) || !isset($_POST['selling_price'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Atualizar produto
    $stmt = $conn->prepare("UPDATE products SET 
                           name = ?, 
                           category_id = ?, 
                           selling_price = ?, 
                           buying_price = ?, 
                           stock_primary = ?, 
                           stock_secondary = ?, 
                           min_stock = ? 
                           WHERE id = ?");
    
    $result = $stmt->execute([
        $_POST['name'],
        $_POST['category_id'] ?: null,
        $_POST['selling_price'],
        $_POST['buying_price'],
        $_POST['stock_primary'],
        $_POST['stock_secondary'],
        $_POST['min_stock'],
        $_POST['product_id']
    ]);
    
    if ($result) {
        // Buscar o nome da categoria para retornar
        $category_name = '';
        if ($_POST['category_id']) {
            $stmt = $conn->prepare("SELECT name FROM product_categories WHERE id = ?");
            $stmt->execute([$_POST['category_id']]);
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            $category_name = $category ? $category['name'] : '';
        }
        
        // Registrar a ação no log do sistema
        require_once 'api/log_action.php';
        logAction(
            'product', 
            "Produto atualizado: " . $_POST['name'], 
            null, 
            null, 
            $_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']
        );
        
        echo json_encode([
            'success' => true, 
            'message' => 'Produto atualizado com sucesso',
            'category_name' => $category_name
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar produto']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>