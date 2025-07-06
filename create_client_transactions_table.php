
<?php
require_once 'config/database.php';

$database = new Database();
$conn = $database->connect();

try {
    // Create client_transactions table
    $sql = "CREATE TABLE IF NOT EXISTS client_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        user_id INT NOT NULL,
        transaction_type ENUM('credit', 'debit') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description VARCHAR(255),
        reference_type ENUM('sale', 'balance_add', 'debt_payment', 'manual') NOT NULL,
        reference_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )";
    
    $conn->exec($sql);
    echo "✅ Tabela client_transactions criada com sucesso!<br>";
    
    // Add indexes for better performance
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_client_transactions_date ON client_transactions(created_at)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_client_transactions_client ON client_transactions(client_id)");
    $conn->exec("CREATE INDEX IF NOT EXISTS idx_client_transactions_type ON client_transactions(transaction_type)");
    
    echo "✅ Índices criados com sucesso!<br>";
    echo "<br><a href='daily_closure_report.php'>Ver Relatório de Fecho</a>";
    
} catch(PDOException $e) {
    echo "❌ Erro: " . $e->getMessage();
}
?>
