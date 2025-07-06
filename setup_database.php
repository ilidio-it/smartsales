<?php
echo "<h2>🔧 Configuração da Base de Dados - School Canteen System</h2>";

// Configurações da base de dados
$host = 'localhost';
$username = 'u749500304_smartsales';
$password = 'Schoolcanteen@15';
$database = 'u749500304_smartsales';

try {
    // Conectar ao MySQL (sem especificar a base de dados)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Conexão ao MySQL estabelecida!</strong>";
    echo "</div>";
    
    // Criar a base de dados se não existir
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Base de dados '$database' criada/verificada!</strong>";
    echo "</div>";
    
    // Conectar à base de dados específica
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // SQL para criar as tabelas
    $sql = "
    -- Users table
    CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        name VARCHAR(100) NOT NULL,
        user_type ENUM('admin', 'manager', 'employee') NOT NULL,
        canteen_type ENUM('primary', 'secondary', 'both') NOT NULL,
        permissions JSON,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Products table
    CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        selling_price DECIMAL(10,2) NOT NULL,
        buying_price DECIMAL(10,2) NOT NULL,
        stock_primary INT DEFAULT 0,
        stock_secondary INT DEFAULT 0,
        min_stock INT DEFAULT 5,
        photo VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Clients table
    CREATE TABLE IF NOT EXISTS clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('primary', 'secondary', 'teacher') NOT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        debt DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    -- Sales table
    CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT,
        user_id INT NOT NULL,
        canteen_type ENUM('primary', 'secondary') NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('cash', 'account', 'voucher', 'deposit', 'debt') NOT NULL,
        status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
        sale_date DATE NOT NULL,
        sale_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    );

    -- Sale items table
    CREATE TABLE IF NOT EXISTS sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    );

    -- Client transactions table
    CREATE TABLE IF NOT EXISTS client_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type ENUM('payment', 'purchase', 'debt') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        balance_after DECIMAL(10,2) NOT NULL,
        debt_after DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id)
    );

    -- Stock movements table
    CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        canteen_type ENUM('primary', 'secondary') NOT NULL,
        type ENUM('in', 'out', 'adjustment') NOT NULL,
        quantity INT NOT NULL,
        reason VARCHAR(255),
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ";
    
    // Executar o SQL
    $pdo->exec($sql);
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Tabelas criadas com sucesso!</strong>";
    echo "</div>";
    
    // Verificar se o usuário admin já existe
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = 'admin'");
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        // Criar usuário admin
        $password_hash = password_hash('admin', PASSWORD_DEFAULT);
        $permissions = json_encode(['all' => true]);
        
        $stmt = $pdo->prepare("INSERT INTO users (username, password, name, user_type, canteen_type, permissions) 
                               VALUES ('admin', ?, 'Administrator', 'admin', 'both', ?)");
        $stmt->execute([$password_hash, $permissions]);
        
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Usuário admin criado!</strong><br>";
        echo "Username: <strong>admin</strong><br>";
        echo "Password: <strong>admin</strong>";
        echo "</div>";
    } else {
        echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
        echo "ℹ️ <strong>Usuário admin já existe</strong>";
        echo "</div>";
    }
    
    // Inserir produtos de exemplo se não existirem
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products");
    $stmt->execute();
    $productCount = $stmt->fetchColumn();
    
    if ($productCount == 0) {
        $products = [
            ['Sandwich', 2.50, 1.80, 50, 30],
            ['Apple Juice', 1.50, 1.00, 40, 25],
            ['Chocolate Bar', 1.20, 0.80, 60, 35],
            ['Water Bottle', 0.80, 0.50, 80, 50],
            ['Cookies Pack', 2.00, 1.40, 35, 20]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO products (name, selling_price, buying_price, stock_primary, stock_secondary) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($products as $product) {
            $stmt->execute($product);
        }
        
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Produtos de exemplo inseridos!</strong>";
        echo "</div>";
    }
    
    // Inserir clientes de exemplo se não existirem
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clients");
    $stmt->execute();
    $clientCount = $stmt->fetchColumn();
    
    if ($clientCount == 0) {
        $clients = [
            ['João Silva', 'primary', 15.00],
            ['Maria Santos', 'secondary', 20.50],
            ['Ana Costa', 'teacher', 25.00],
            ['Pedro Oliveira', 'primary', 10.00],
            ['Sofia Fernandes', 'secondary', 18.75]
        ];
        
        $stmt = $pdo->prepare("INSERT INTO clients (name, type, balance) VALUES (?, ?, ?)");
        
        foreach ($clients as $client) {
            $stmt->execute($client);
        }
        
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Clientes de exemplo inseridos!</strong>";
        echo "</div>";
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>🎉 Configuração Completa!</h3>";
    echo "<p><strong>Sistema pronto para usar:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Base de dados criada</li>";
    echo "<li>✅ Tabelas criadas</li>";
    echo "<li>✅ Usuário admin configurado</li>";
    echo "<li>✅ Dados de exemplo inseridos</li>";
    echo "</ul>";
    echo "<p><strong>Credenciais de acesso:</strong></p>";
    echo "<p>Username: <strong>admin</strong><br>Password: <strong>admin</strong></p>";
    echo "<p><a href='login.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🚀 Ir para Login</a></p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>Possíveis soluções:</h3>";
    echo "<ul>";
    echo "<li>Verificar se o MySQL/XAMPP está rodando</li>";
    echo "<li>Verificar as credenciais em config/database.php</li>";
    echo "<li>Verificar se o PHP tem extensão PDO habilitada</li>";
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