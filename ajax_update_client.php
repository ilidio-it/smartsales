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
if (!isset($_POST['client_id']) || !isset($_POST['name']) || !isset($_POST['type'])) {
    echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
    exit();
}

try {
    $database = new Database();
    $conn = $database->connect();
    
    // Buscar dados atuais do cliente para comparação
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->execute([$_POST['client_id']]);
    $oldClient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$oldClient) {
        echo json_encode(['success' => false, 'message' => 'Cliente não encontrado']);
        exit();
    }
    
    // Atualizar cliente
    $stmt = $conn->prepare("UPDATE clients SET 
                           name = ?, 
                           type = ?, 
                           contact = ?, 
                           balance = ?, 
                           debt = ? 
                           WHERE id = ?");
    
    $result = $stmt->execute([
        $_POST['name'],
        $_POST['type'],
        $_POST['contact'] ?? '',
        $_POST['balance'],
        $_POST['debt'],
        $_POST['client_id']
    ]);
    
    if ($result) {
        // Registrar mudanças significativas no log
        $logChanges = [];
        
        if ($oldClient['balance'] != $_POST['balance']) {
            $logChanges[] = "Saldo: " . number_format($oldClient['balance'], 2) . " → " . number_format($_POST['balance'], 2);
        }
        
        if ($oldClient['debt'] != $_POST['debt']) {
            $logChanges[] = "Dívida: " . number_format($oldClient['debt'], 2) . " → " . number_format($_POST['debt'], 2);
        }
        
        if ($oldClient['name'] != $_POST['name']) {
            $logChanges[] = "Nome: " . $oldClient['name'] . " → " . $_POST['name'];
        }
        
        if ($oldClient['type'] != $_POST['type']) {
            $type_names = [
                'primary' => 'Aluno Primária',
                'secondary' => 'Aluno Secundária',
                'teacher_primary' => 'Professor Primária',
                'teacher_secondary' => 'Professor Secundária',
                'reservation' => 'Reserva'
            ];
            
            $oldType = $type_names[$oldClient['type']] ?? ucfirst($oldClient['type']);
            $newType = $type_names[$_POST['type']] ?? ucfirst($_POST['type']);
            
            $logChanges[] = "Tipo: " . $oldType . " → " . $newType;
        }
        
        // Se houve mudanças significativas, registrar no log
        if (!empty($logChanges)) {
            require_once 'api/log_action.php';
            logAction(
                'client', 
                "Cliente atualizado: " . $_POST['name'] . " - " . implode(", ", $logChanges), 
                null, 
                null, 
                $_SESSION['canteen_type'] === 'both' ? 'primary' : $_SESSION['canteen_type']
            );
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Cliente atualizado com sucesso'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar cliente']);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Erro de banco de dados: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
}
?>