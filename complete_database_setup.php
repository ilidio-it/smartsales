<?php
echo "<h2>🍽️ SmartSales - Configuração Completa da Base de Dados</h2>";
echo "<p><strong>Sistema de Gestão de Cantina Escolar - WAMP Edition</strong></p>";

// Configurações da base de dados para WAMP
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'school_canteen';

try {
    // Conectar ao MySQL (sem especificar a base de dados)
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Conexão ao MySQL estabelecida!</strong>";
    echo "</div>";
    
    // Criar a base de dados se não existir
    $pdo->exec("DROP DATABASE IF EXISTS $database");
    $pdo->exec("CREATE DATABASE $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Base de dados '$database' criada!</strong>";
    echo "</div>";
    
    // Conectar à base de dados específica
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("SET NAMES utf8mb4");
    
    // Criar tabelas uma por uma para evitar problemas
    echo "<h3>📋 Criando Tabelas...</h3>";
    
    // 1. Users table
    $pdo->exec("
    CREATE TABLE users (
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
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'users' criada</div>";
    
    // 2. Product categories table
    $pdo->exec("
    CREATE TABLE product_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'product_categories' criada</div>";
    
    // 3. Products table
    $pdo->exec("
    CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category_id INT NULL,
        selling_price DECIMAL(10,2) NOT NULL,
        buying_price DECIMAL(10,2) NOT NULL DEFAULT 0,
        stock_primary INT DEFAULT 0,
        stock_secondary INT DEFAULT 0,
        min_stock INT DEFAULT 5,
        photo VARCHAR(255),
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES product_categories(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'products' criada</div>";
    
    // 4. Clients table
    $pdo->exec("
    CREATE TABLE clients (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        type ENUM('primary', 'secondary', 'teacher_primary', 'teacher_secondary', 'reservation') NOT NULL,
        contact VARCHAR(255),
        canteen_type ENUM('primary', 'secondary') NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        debt DECIMAL(10,2) DEFAULT 0.00,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'clients' criada</div>";
    
    // 5. Sales table
    $pdo->exec("
    CREATE TABLE sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT,
        user_id INT NOT NULL,
        canteen_type ENUM('primary', 'secondary') NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        payment_method ENUM('cash', 'mpesa', 'emola', 'account', 'voucher', 'debt', 'request', 'debt_payment') NOT NULL,
        status ENUM('completed', 'pending', 'cancelled') DEFAULT 'completed',
        sale_date DATE NOT NULL,
        sale_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'sales' criada</div>";
    
    // 6. Sale items table
    $pdo->exec("
    CREATE TABLE sale_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sale_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'sale_items' criada</div>";
    
    // 7. Client transactions table
    $pdo->exec("
    CREATE TABLE client_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        type ENUM('payment', 'purchase', 'debt') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        balance_after DECIMAL(10,2) NOT NULL,
        debt_after DECIMAL(10,2) NOT NULL,
        user_id INT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id),
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'client_transactions' criada</div>";
    
    // 8. Stock movements table
    $pdo->exec("
    CREATE TABLE stock_movements (
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
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'stock_movements' criada</div>";
    
    // 9. Requests table
    $pdo->exec("
    CREATE TABLE requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        client_id INT NOT NULL,
        sale_id INT NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        completed_at TIMESTAMP NULL,
        FOREIGN KEY (client_id) REFERENCES clients(id),
        FOREIGN KEY (sale_id) REFERENCES sales(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'requests' criada</div>";
    
    // 10. System logs table
    $pdo->exec("
    CREATE TABLE system_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action_type ENUM('sale', 'payment', 'stock', 'client', 'product') NOT NULL,
        description TEXT NOT NULL,
        amount DECIMAL(10,2) NULL,
        payment_method VARCHAR(50) NULL,
        canteen_type ENUM('primary', 'secondary') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'system_logs' criada</div>";
    
    // 11. Expenses table
    $pdo->exec("
    CREATE TABLE expenses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category ENUM('rent', 'salary', 'utilities', 'supplies', 'maintenance', 'other') NOT NULL,
        description TEXT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        expense_date DATE NOT NULL,
        user_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id)
    )");
    echo "<div style='color: green; padding: 5px;'>✅ Tabela 'expenses' criada</div>";
    
    // Criar usuário admin
    echo "<h3>👤 Criando Usuário Admin...</h3>";
    $password_hash = password_hash('admin', PASSWORD_DEFAULT);
    $permissions = json_encode(['all' => true]);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, name, user_type, canteen_type, permissions) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute(['admin', $password_hash, 'Administrator', 'admin', 'both', $permissions]);
    
    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
    echo "✅ <strong>Usuário admin criado!</strong><br>";
    echo "Username: <strong>admin</strong><br>";
    echo "Password: <strong>admin</strong>";
    echo "</div>";
    
    // Inserir categorias
    echo "<h3>📂 Inserindo Categorias...</h3>";
    $categories = [
        ['Bebidas', 'Refrigerantes, sumos, água'],
        ['Hambúrgueres', 'Hambúrgueres variados'],
        ['Sandes', 'Sanduíches diversos'],
        ['Bolachas', 'Bolachas e biscoitos'],
        ['Tostas', 'Tostas quentes'],
        ['Pregos', 'Pregos no pão'],
        ['Snacks e Doces', 'Chocolates, doces e snacks'],
        ['Salgadinhos', 'Rissóis, chamuças, spring rolls']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO product_categories (name, description) VALUES (?, ?)");
    foreach ($categories as $category) {
        $stmt->execute([$category[0], $category[1]]);
    }
    
    echo "<div style='color: green; padding: 5px;'>✅ " . count($categories) . " categorias inseridas</div>";
    
    // Buscar IDs das categorias
    $stmt = $pdo->prepare("SELECT id, name FROM product_categories");
    $stmt->execute();
    $categories_map = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $categories_map[$row['name']] = $row['id'];
    }
    
    // Inserir todos os produtos
    echo "<h3>🍽️ Inserindo Produtos Completos...</h3>";
    
    $products = [
        // BEBIDAS
        ['Refresco Lata (330 ml)', $categories_map['Bebidas'], 60.00, 40.00, 50, 30, 5],
        ['Sumo Ceres (200ml)', $categories_map['Bebidas'], 50.00, 35.00, 40, 25, 5],
        ['Sumo Compal (128 ml)', $categories_map['Bebidas'], 45.00, 30.00, 35, 20, 5],
        ['Cappy (330 ml)', $categories_map['Bebidas'], 80.00, 55.00, 30, 20, 5],
        ['FruitTree (350 ml)', $categories_map['Bebidas'], 80.00, 55.00, 25, 15, 5],
        ['Água (500ml)', $categories_map['Bebidas'], 30.00, 20.00, 100, 80, 10],
        ['Compal (180ml)', $categories_map['Bebidas'], 35.00, 25.00, 40, 30, 5],
        
        // HAMBÚRGUERES
        ['H. Simples', $categories_map['Hambúrgueres'], 120.00, 80.00, 20, 15, 3],
        ['H. com Ovo e Salada', $categories_map['Hambúrgueres'], 120.00, 85.00, 20, 15, 3],
        ['H. com Ovo, Queijo e Salada', $categories_map['Hambúrgueres'], 150.00, 105.00, 15, 10, 3],
        ['H. Completo - Ovo, Queijo, Salada', $categories_map['Hambúrgueres'], 180.00, 125.00, 15, 10, 3],
        ['H. Duplo', $categories_map['Hambúrgueres'], 200.00, 140.00, 10, 8, 2],
        
        // SANDES
        ['Sandes de Ovo', $categories_map['Sandes'], 70.00, 45.00, 25, 20, 5],
        ['Sandes Mista (Ovo e Queijo)', $categories_map['Sandes'], 100.00, 70.00, 20, 15, 3],
        
        // BOLACHAS
        ['Marilan', $categories_map['Bolachas'], 65.00, 45.00, 30, 25, 5],
        ['Água e Sal (100g)', $categories_map['Bolachas'], 250.00, 180.00, 15, 10, 3],
        ['Bites (60g)', $categories_map['Bolachas'], 20.00, 15.00, 50, 40, 10],
        ['Tennis (200g)', $categories_map['Bolachas'], 120.00, 85.00, 25, 20, 5],
        ['Rommany Cream (200g)', $categories_map['Bolachas'], 170.00, 120.00, 20, 15, 3],
        ['Eat Some More (200g)', $categories_map['Bolachas'], 150.00, 105.00, 20, 15, 3],
        ['Maria SA (200g)', $categories_map['Bolachas'], 90.00, 65.00, 30, 25, 5],
        ['Maria Nacional (100g)', $categories_map['Bolachas'], 30.00, 22.00, 40, 35, 8],
        ['Maria Nacional (200g)', $categories_map['Bolachas'], 50.00, 35.00, 35, 30, 5],
        ['Nutro (73g)', $categories_map['Bolachas'], 40.00, 28.00, 35, 30, 5],
        ['Topper (125g)', $categories_map['Bolachas'], 65.00, 45.00, 30, 25, 5],
        ['Cremby (84g)', $categories_map['Bolachas'], 35.00, 25.00, 40, 35, 8],
        ['Tortinhas', $categories_map['Bolachas'], 55.00, 40.00, 25, 20, 5],
        ['Mousse (130g)', $categories_map['Bolachas'], 55.00, 40.00, 25, 20, 5],
        ['Waffers', $categories_map['Bolachas'], 40.00, 28.00, 35, 30, 5],
        
        // TOSTAS
        ['Tosta de Atum', $categories_map['Tostas'], 150.00, 105.00, 15, 10, 3],
        ['Tosta de Frango', $categories_map['Tostas'], 150.00, 105.00, 15, 10, 3],
        ['Tostão de Queijo', $categories_map['Tostas'], 100.00, 70.00, 20, 15, 3],
        ['Tosta Mista', $categories_map['Tostas'], 180.00, 125.00, 12, 8, 2],
        
        // PREGOS
        ['Prego com Ovo', $categories_map['Pregos'], 100.00, 70.00, 20, 15, 3],
        ['Prego com Queijo', $categories_map['Pregos'], 100.00, 70.00, 20, 15, 3],
        ['Prego com Ovo e Queijo', $categories_map['Pregos'], 150.00, 105.00, 15, 10, 3],
        
        // SNACKS E DOCES
        ['Cachorro Quente', $categories_map['Snacks e Doces'], 100.00, 70.00, 20, 15, 3],
        ['Bon O Bon', $categories_map['Snacks e Doces'], 20.00, 15.00, 50, 40, 10],
        ['Simba (25g)', $categories_map['Snacks e Doces'], 30.00, 22.00, 40, 35, 8],
        ['Simba (145g)', $categories_map['Snacks e Doces'], 100.00, 70.00, 25, 20, 5],
        ['Doritos (30g)', $categories_map['Snacks e Doces'], 30.00, 22.00, 40, 35, 8],
        ['Doritos Grande (145g)', $categories_map['Snacks e Doces'], 100.00, 70.00, 25, 20, 5],
        ['Yogueta', $categories_map['Snacks e Doces'], 10.00, 7.00, 60, 50, 15],
        ['KitKat', $categories_map['Snacks e Doces'], 40.00, 28.00, 35, 30, 5],
        ['Chocolate Cadbury', $categories_map['Snacks e Doces'], 100.00, 70.00, 25, 20, 5],
        ['Amigo (35g)', $categories_map['Snacks e Doces'], 20.00, 15.00, 50, 40, 10],
        ['Senor Puff', $categories_map['Snacks e Doces'], 40.00, 28.00, 35, 30, 5],
        
        // SALGADINHOS
        ['Chamuças', $categories_map['Salgadinhos'], 20.00, 12.00, 30, 25, 5],
        ['Rissóis', $categories_map['Salgadinhos'], 20.00, 12.00, 30, 25, 5],
        ['Spring Rolls', $categories_map['Salgadinhos'], 25.00, 15.00, 25, 20, 5]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO products (name, category_id, selling_price, buying_price, stock_primary, stock_secondary, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($products as $product) {
        $stmt->execute($product);
    }
    
    echo "<div style='color: green; padding: 5px;'>✅ " . count($products) . " produtos inseridos</div>";
    
    // Inserir clientes de exemplo
    echo "<h3>👥 Inserindo Clientes de Exemplo...</h3>";
    $clients = [
        ['João Silva', 'primary', '84123456789', 'primary', 150.00, 0.00],
        ['Maria Santos', 'secondary', '84987654321', 'secondary', 205.00, 0.00],
        ['Ana Costa', 'teacher_primary', '84555666777', 'primary', 250.00, 0.00],
        ['Pedro Oliveira', 'primary', '84111222333', 'primary', 100.00, 0.00],
        ['Sofia Fernandes', 'secondary', '84444555666', 'secondary', 187.50, 0.00],
        ['Carlos Mendes', 'teacher_secondary', '84777888999', 'secondary', 300.00, 0.00]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO clients (name, type, contact, canteen_type, balance, debt) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($clients as $client) {
        $stmt->execute($client);
    }
    
    echo "<div style='color: green; padding: 5px;'>✅ " . count($clients) . " clientes inseridos</div>";
    
    // Verificação final
    echo "<h3>🔍 Verificação Final...</h3>";
    $tables = [
        'users', 'product_categories', 'products', 'clients', 'sales', 
        'sale_items', 'client_transactions', 'stock_movements', 
        'requests', 'system_logs', 'expenses'
    ];
    
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "<div style='color: green; padding: 3px;'>✅ Tabela '$table': $count registros</div>";
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 3px;'>❌ Tabela '$table': Erro</div>";
        }
    }
    
    echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>";
    echo "<h3 style='color: #155724; margin-top: 0;'>🎉 SmartSales Configurado com Sucesso!</h3>";
    echo "<p><strong>Sistema completo instalado:</strong></p>";
    echo "<ul>";
    echo "<li>✅ Base de dados criada e configurada</li>";
    echo "<li>✅ " . count($categories) . " categorias de produtos</li>";
    echo "<li>✅ " . count($products) . " produtos completos inseridos</li>";
    echo "<li>✅ " . count($clients) . " clientes de exemplo</li>";
    echo "<li>✅ Sistema de logs implementado</li>";
    echo "<li>✅ Sistema de reservas funcional</li>";
    echo "<li>✅ Gestão de dívidas operacional</li>";
    echo "<li>✅ Separação: Clientes com Saldo vs Devedores</li>";
    echo "<li>✅ Métodos de pagamento: M-Pesa, E-Mola incluídos</li>";
    echo "</ul>";
    echo "<p><strong>Credenciais de acesso:</strong></p>";
    echo "<p>Username: <strong>admin</strong><br>Password: <strong>admin</strong></p>";
    echo "<p><a href='login.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 1.1rem;'>🚀 Entrar no Sistema</a></p>";
    echo "</div>";
    
    echo "<div style='background: #cce5ff; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0;'>";
    echo "<h4 style='color: #004085; margin-top: 0;'>📋 Produtos Inseridos por Categoria:</h4>";
    echo "<p><strong>🥤 Bebidas:</strong> 7 produtos (Refresco, Sumos, Água, etc.)</p>";
    echo "<p><strong>🍔 Hambúrgueres:</strong> 5 tipos (Simples, Completo, Duplo, etc.)</p>";
    echo "<p><strong>🥪 Sandes:</strong> 2 tipos (Ovo, Mista)</p>";
    echo "<p><strong>🍪 Bolachas:</strong> 13 variedades (Marilan, Tennis, Maria, etc.)</p>";
    echo "<p><strong>🍞 Tostas:</strong> 4 tipos (Atum, Frango, Queijo, Mista)</p>";
    echo "<p><strong>🥩 Pregos:</strong> 3 tipos (Ovo, Queijo, Completo)</p>";
    echo "<p><strong>🍫 Snacks e Doces:</strong> 11 produtos (Chocolates, Doritos, etc.)</p>";
    echo "<p><strong>🥟 Salgadinhos:</strong> 3 tipos (Chamuças, Rissóis, Spring Rolls)</p>";
    echo "</div>";
    
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107; margin: 20px 0;'>";
    echo "<h4 style='color: #856404; margin-top: 0;'>🔧 Melhorias Implementadas:</h4>";
    echo "<p><strong>✅ Página de Vendas:</strong> Carrinho vazio inicial, scroll vertical, produtos pequenos com preços</p>";
    echo "<p><strong>✅ Clientes Separados:</strong> Clientes com Saldo vs Devedores (dívidas)</p>";
    echo "<p><strong>✅ Métodos de Pagamento:</strong> M-Pesa e E-Mola incluídos nos relatórios</p>";
    echo "<p><strong>✅ Tipos de Cliente:</strong> Aluno Primária, Aluno Secundária, Professor Primária, Professor Secundária</p>";
    echo "<p><strong>✅ Sistema de Dívidas:</strong> Diferenciação entre clientes com saldo e devedores</p>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "❌ <strong>Erro:</strong> " . $e->getMessage();
    echo "</div>";
    
    echo "<div style='background: #f8d7da; padding: 15px; border-left: 4px solid #dc3545; margin: 20px 0;'>";
    echo "<h3 style='color: #721c24; margin-top: 0;'>Possíveis soluções:</h3>";
    echo "<ul>";
    echo "<li>Verificar se o WAMP está rodando</li>";
    echo "<li>Verificar se o MySQL está ativo</li>";
    echo "<li>Verificar as credenciais (root sem senha)</li>";
    echo "<li>Verificar se a porta 3306 está livre</li>";
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
h2, h3, h4 {
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