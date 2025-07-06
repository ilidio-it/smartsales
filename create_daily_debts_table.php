
<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

echo "<h2>🗃️ Criando tabela de dívidas diárias...</h2>";

try {
    // Criar tabela daily_debts
    $sql = "CREATE TABLE IF NOT EXISTS daily_debts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        user_id INT NOT NULL,
        canteen_type ENUM('primary', 'secondary') NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        debt_date DATE NOT NULL,
        items_json TEXT,
        status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
        paid_date DATE NULL,
        paid_amount DECIMAL(10,2) DEFAULT 0.00,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_debt_date (debt_date),
        INDEX idx_client_id (client_id),
        INDEX idx_canteen_type (canteen_type),
        INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->exec($sql);
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ Tabela 'daily_debts' criada com sucesso!";
    echo "</div>";
    
    echo "<h3>📊 Estrutura da tabela:</h3>";
    echo "<ul>";
    echo "<li><strong>id</strong>: ID único da dívida</li>";
    echo "<li><strong>client_id</strong>: ID do cliente devedor</li>";
    echo "<li><strong>user_id</strong>: ID do usuário que registrou</li>";
    echo "<li><strong>canteen_type</strong>: Cantina (primária/secundária)</li>";
    echo "<li><strong>total_amount</strong>: Valor total da dívida</li>";
    echo "<li><strong>debt_date</strong>: Data da dívida</li>";
    echo "<li><strong>items_json</strong>: Itens comprados (JSON)</li>";
    echo "<li><strong>status</strong>: Status (pendente/pago/cancelado)</li>";
    echo "<li><strong>paid_date</strong>: Data do pagamento</li>";
    echo "<li><strong>paid_amount</strong>: Valor pago</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "❌ Erro ao criar tabela: " . $e->getMessage();
    echo "</div>";
}

echo "<p><a href='dashboard.php'>← Voltar ao Dashboard</a></p>";
?>
