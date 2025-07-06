<?php
require_once 'config/database.php';

echo "<h2>🔧 Atualização da Base de Dados - SmartSales</h2>";

try {
    $database = new Database();
    $conn = $database->connect();
    
    if ($conn) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Conexão estabelecida com sucesso!</strong>";
        echo "</div>";
        
        // 1. Add contact column to clients table
        echo "<h3>1. Adicionando coluna 'contact' na tabela clients...</h3>";
        try {
            $conn->exec("ALTER TABLE clients ADD COLUMN contact VARCHAR(255) AFTER type");
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "✅ Coluna 'contact' adicionada com sucesso!";
            echo "</div>";
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
                echo "ℹ️ Coluna 'contact' já existe.";
                echo "</div>";
            } else {
                throw $e;
            }
        }
        
        // 2. Create requests table
        echo "<h3>2. Criando tabela 'requests'...</h3>";
        try {
            $sql = "CREATE TABLE IF NOT EXISTS requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                sale_id INT NOT NULL,
                total_amount DECIMAL(10,2) NOT NULL,
                status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                FOREIGN KEY (client_id) REFERENCES clients(id),
                FOREIGN KEY (sale_id) REFERENCES sales(id)
            )";
            $conn->exec($sql);
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "✅ Tabela 'requests' criada com sucesso!";
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ Erro ao criar tabela 'requests': " . $e->getMessage();
            echo "</div>";
        }
        
        // 3. Create expenses table
        echo "<h3>3. Criando tabela 'expenses'...</h3>";
        try {
            $sql = "CREATE TABLE IF NOT EXISTS expenses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                category ENUM('rent', 'salary', 'utilities', 'supplies', 'maintenance', 'other') NOT NULL,
                description TEXT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                expense_date DATE NOT NULL,
                user_id INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id)
            )";
            $conn->exec($sql);
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "✅ Tabela 'expenses' criada com sucesso!";
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ Erro ao criar tabela 'expenses': " . $e->getMessage();
            echo "</div>";
        }
        
        // 4. Create debt_payments table
        echo "<h3>4. Criando tabela 'debt_payments'...</h3>";
        try {
            $sql = "CREATE TABLE IF NOT EXISTS debt_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                client_id INT NOT NULL,
                amount DECIMAL(10,2) NOT NULL,
                submitted_by INT NOT NULL,
                approved_by INT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                approved_at TIMESTAMP NULL,
                FOREIGN KEY (client_id) REFERENCES clients(id),
                FOREIGN KEY (submitted_by) REFERENCES users(id),
                FOREIGN KEY (approved_by) REFERENCES users(id)
            )";
            $conn->exec($sql);
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "✅ Tabela 'debt_payments' criada com sucesso!";
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ Erro ao criar tabela 'debt_payments': " . $e->getMessage();
            echo "</div>";
        }
        
        // 5. Update sales table payment methods
        echo "<h3>5. Atualizando métodos de pagamento na tabela 'sales'...</h3>";
        try {
            $conn->exec("ALTER TABLE sales MODIFY COLUMN payment_method ENUM('cash', 'account', 'voucher', 'deposit', 'debt', 'mpesa', 'emola', 'request') NOT NULL");
            echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
            echo "✅ Métodos de pagamento atualizados com sucesso!";
            echo "</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ Erro ao atualizar métodos de pagamento: " . $e->getMessage();
            echo "</div>";
        }
        
        // 6. Verify all tables
        echo "<h3>6. Verificação final das tabelas...</h3>";
        $tables = ['users', 'products', 'clients', 'sales', 'sale_items', 'client_transactions', 'stock_movements', 'requests', 'expenses', 'debt_payments'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<div style='color: green; padding: 5px;'>✅ Tabela '$table': $count registros</div>";
            } catch (PDOException $e) {
                echo "<div style='color: red; padding: 5px;'>❌ Tabela '$table': Erro - " . $e->getMessage() . "</div>";
            }
        }
        
        echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>";
        echo "<h3 style='color: #155724; margin-top: 0;'>🎉 Atualização Completa!</h3>";
        echo "<p><strong>Base de dados atualizada com sucesso:</strong></p>";
        echo "<ul>";
        echo "<li>✅ Coluna 'contact' adicionada aos clientes</li>";
        echo "<li>✅ Tabela 'requests' para pedidos/reservas</li>";
        echo "<li>✅ Tabela 'expenses' para despesas</li>";
        echo "<li>✅ Tabela 'debt_payments' para aprovações</li>";
        echo "<li>✅ Novos métodos de pagamento (M-Pesa, E-Mola, Request)</li>";
        echo "</ul>";
        echo "<p><a href='dashboard.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir para Dashboard</a></p>";
        echo "</div>";
        
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ <strong>Falha na conexão com a base de dados!</strong>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>Possíveis soluções:</h3>";
    echo "<ul>";
    echo "<li>Verificar se o MySQL/XAMPP está rodando</li>";
    echo "<li>Verificar as credenciais em config/database.php</li>";
    echo "<li>Verificar se a base de dados 'school_canteen' existe</li>";
    echo "<li>Executar setup_database.php primeiro se necessário</li>";
    echo "</ul>";
    echo "</div>";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    margin: 20px;
    background-color: #f8f9fa;
    line-height: 1.6;
}
h2, h3 {
    color: #333;
}
a {
    color: #007bff;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>