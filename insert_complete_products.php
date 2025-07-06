<?php
echo "<h2>🍽️ SmartSales - Inserção Completa de Produtos</h2>";
echo "<p><strong>Inserindo lista definitiva de produtos com categorias</strong></p>";

require_once 'config/database.php';

try {
    $database = new Database();
    $conn = $database->connect();
    
    if ($conn) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Conexão estabelecida com sucesso!</strong>";
        echo "</div>";
        
        // Start transaction
        $conn->beginTransaction();
        
        // 1. Clear existing products and categories
        echo "<h3>1. Limpando produtos e categorias existentes...</h3>";
        $conn->exec("DELETE FROM sale_items WHERE product_id IN (SELECT id FROM products)");
        $conn->exec("DELETE FROM stock_movements WHERE product_id IN (SELECT id FROM products)");
        $conn->exec("DELETE FROM products");
        $conn->exec("DELETE FROM product_categories");
        $conn->exec("ALTER TABLE products AUTO_INCREMENT = 1");
        $conn->exec("ALTER TABLE product_categories AUTO_INCREMENT = 1");
        
        echo "<div style='color: green; padding: 5px;'>✅ Produtos e categorias anteriores removidos</div>";
        
        // 2. Insert categories
        echo "<h3>2. Inserindo categorias...</h3>";
        $categories = [
            ['Bebidas', 'Refrigerantes, sumos, água'],
            ['Gelados', 'Gelados e picolés'],
            ['Bolachas', 'Bolachas e biscoitos variados'],
            ['Chocolates', 'Chocolates e doces'],
            ['Chips', 'Batatas fritas e snacks salgados'],
            ['Sandes', 'Sanduíches diversos'],
            ['Tosta', 'Tostas quentes'],
            ['Hamburguer', 'Hambúrgueres variados'],
            ['Prego', 'Pregos no pão']
        ];
        
        $stmt = $conn->prepare("INSERT INTO product_categories (name, description) VALUES (?, ?)");
        foreach ($categories as $category) {
            $stmt->execute([$category[0], $category[1]]);
        }
        
        echo "<div style='color: green; padding: 5px;'>✅ " . count($categories) . " categorias inseridas</div>";
        
        // Get category IDs
        $stmt = $conn->prepare("SELECT id, name FROM product_categories");
        $stmt->execute();
        $categories_map = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $categories_map[$row['name']] = $row['id'];
        }
        
        // 3. Insert all products
        echo "<h3>3. Inserindo produtos completos...</h3>";
        
        $products = [
            // BEBIDAS
            ['Refresco Lata (330 ml)', $categories_map['Bebidas'], 60.00, 40.00, 50, 30, 5],
            ['Refresco Garrafa (350 ml)', $categories_map['Bebidas'], 40.00, 28.00, 40, 25, 5],
            ['Ceres (200ml)', $categories_map['Bebidas'], 50.00, 35.00, 35, 20, 5],
            ['Compal (180 ml)', $categories_map['Bebidas'], 45.00, 30.00, 30, 18, 5],
            ['Cappy (330 ml)', $categories_map['Bebidas'], 80.00, 55.00, 25, 15, 5],
            ['Água (500ml)', $categories_map['Bebidas'], 30.00, 20.00, 100, 80, 10],
            
            // GELADOS
            ['Picolé 50', $categories_map['Gelados'], 50.00, 35.00, 30, 20, 5],
            ['Picolé 60', $categories_map['Gelados'], 60.00, 42.00, 25, 15, 5],
            ['Picolé 70', $categories_map['Gelados'], 70.00, 49.00, 20, 12, 3],
            ['Picolé 90', $categories_map['Gelados'], 90.00, 63.00, 15, 10, 3],
            ['Picolé 110', $categories_map['Gelados'], 110.00, 77.00, 12, 8, 2],
            
            // BOLACHAS
            ['Marilan wafers', $categories_map['Bolachas'], 65.00, 45.00, 30, 25, 5],
            ['Marilan tortinhas', $categories_map['Bolachas'], 65.00, 45.00, 25, 20, 5],
            ['Chocolicious bolacha', $categories_map['Bolachas'], 80.00, 56.00, 20, 15, 3],
            ['Sooper Bolacha', $categories_map['Bolachas'], 80.00, 56.00, 20, 15, 3],
            ['Porleo bolacha', $categories_map['Bolachas'], 25.00, 18.00, 40, 30, 8],
            ['Hipopo Waffers', $categories_map['Bolachas'], 40.00, 28.00, 35, 25, 5],
            ['Hipopo tortinhas', $categories_map['Bolachas'], 35.00, 25.00, 35, 25, 5],
            ['Água e Sal (100g)', $categories_map['Bolachas'], 30.00, 21.00, 30, 20, 5],
            ['Lemon cream', $categories_map['Bolachas'], 40.00, 28.00, 25, 18, 5],
            ['Mini Oreo', $categories_map['Bolachas'], 15.00, 11.00, 50, 40, 10],
            ['Bolacha coco', $categories_map['Bolachas'], 30.00, 21.00, 30, 25, 5],
            ['Mini Bites', $categories_map['Bolachas'], 10.00, 7.00, 60, 50, 15],
            ['Bites normal (60g)', $categories_map['Bolachas'], 20.00, 14.00, 50, 40, 10],
            ['Bites creme (70g)', $categories_map['Bolachas'], 25.00, 18.00, 45, 35, 8],
            ['Tennis (200g)', $categories_map['Bolachas'], 120.00, 84.00, 20, 15, 3],
            ['Red label', $categories_map['Bolachas'], 120.00, 84.00, 18, 12, 3],
            ['Rommany Cream (200g)', $categories_map['Bolachas'], 170.00, 119.00, 15, 10, 2],
            ['Eat Some More (200g)', $categories_map['Bolachas'], 150.00, 105.00, 18, 12, 3],
            ['Maria SA (200g)', $categories_map['Bolachas'], 90.00, 63.00, 25, 20, 5],
            ['Maria Nacional (100g)', $categories_map['Bolachas'], 30.00, 21.00, 40, 35, 8],
            ['Maria Nacional (200g)', $categories_map['Bolachas'], 50.00, 35.00, 35, 30, 5],
            ['Nutro (73g)', $categories_map['Bolachas'], 40.00, 28.00, 35, 30, 5],
            ['Topper (125g)', $categories_map['Bolachas'], 65.00, 46.00, 25, 20, 5],
            ['Cremby (84g)', $categories_map['Bolachas'], 35.00, 25.00, 40, 35, 8],
            ['Tortinhas', $categories_map['Bolachas'], 55.00, 39.00, 25, 20, 5],
            ['Mousse (130g)', $categories_map['Bolachas'], 55.00, 39.00, 25, 20, 5],
            ['Bolacha Kat kat', $categories_map['Bolachas'], 35.00, 25.00, 30, 25, 5],
            
            // CHOCOLATES
            ['Bon O Bon', $categories_map['Chocolates'], 20.00, 14.00, 50, 40, 10],
            ['Chocolate Dairy milk', $categories_map['Chocolates'], 90.00, 63.00, 20, 15, 3],
            ['Kit kat', $categories_map['Chocolates'], 35.00, 25.00, 30, 25, 5],
            ['Lunch bar', $categories_map['Chocolates'], 35.00, 25.00, 30, 25, 5],
            ['Smarties', $categories_map['Chocolates'], 35.00, 25.00, 30, 25, 5],
            ['Chocolate Rosy', $categories_map['Chocolates'], 30.00, 21.00, 35, 30, 5],
            ['Chocolate Mega', $categories_map['Chocolates'], 30.00, 21.00, 35, 30, 5],
            ['Rich Rolls', $categories_map['Chocolates'], 10.00, 7.00, 60, 50, 15],
            
            // CHIPS
            ['Simba peq (25g)', $categories_map['Chips'], 30.00, 21.00, 40, 35, 8],
            ['Simba grande (145g)', $categories_map['Chips'], 100.00, 70.00, 20, 15, 3],
            ['Doritos peq (30g)', $categories_map['Chips'], 30.00, 21.00, 40, 35, 8],
            ['Doritos grande (145g)', $categories_map['Chips'], 100.00, 70.00, 20, 15, 3],
            ['Lays peq (30g)', $categories_map['Chips'], 30.00, 21.00, 40, 35, 8],
            ['Lays grande (145g)', $categories_map['Chips'], 100.00, 70.00, 20, 15, 3],
            ['Pringles', $categories_map['Chips'], 250.00, 175.00, 10, 8, 2],
            ['Amigo (35g)', $categories_map['Chips'], 20.00, 14.00, 50, 40, 10],
            ['Senor Puff Grande', $categories_map['Chips'], 40.00, 28.00, 30, 25, 5],
            ['Senor Puff Pequeno', $categories_map['Chips'], 20.00, 14.00, 40, 35, 8],
            ['Chipa', $categories_map['Chips'], 10.00, 7.00, 60, 50, 15],
            ['Nick nacks', $categories_map['Chips'], 10.00, 7.00, 60, 50, 15],
            
            // SANDES
            ['Sandes de Ovo', $categories_map['Sandes'], 70.00, 49.00, 25, 20, 5],
            ['Sandes Mista (Ovo e Queijo)', $categories_map['Sandes'], 100.00, 70.00, 20, 15, 3],
            
            // TOSTA
            ['Tosta de queijo', $categories_map['Tosta'], 100.00, 70.00, 20, 15, 3],
            ['Tosta de atum', $categories_map['Tosta'], 150.00, 105.00, 15, 10, 3],
            ['Tosta mista (atum e queijo)', $categories_map['Tosta'], 180.00, 126.00, 12, 8, 2],
            ['Tosta de frango', $categories_map['Tosta'], 180.00, 126.00, 12, 8, 2],
            
            // HAMBURGUER
            ['H. simples (Ovo e salada)', $categories_map['Hamburguer'], 120.00, 84.00, 15, 10, 3],
            ['H. completo - ovo, queijo, salada', $categories_map['Hamburguer'], 180.00, 126.00, 12, 8, 2],
            ['H. duplo', $categories_map['Hamburguer'], 200.00, 140.00, 10, 6, 2],
            
            // PREGO
            ['Prego com Ovo', $categories_map['Prego'], 150.00, 105.00, 15, 10, 3],
            ['Prego com Queijo', $categories_map['Prego'], 150.00, 105.00, 15, 10, 3],
            ['Prego com Ovo e Queijo', $categories_map['Prego'], 180.00, 126.00, 12, 8, 2]
        ];
        
        $stmt = $conn->prepare("INSERT INTO products (name, category_id, selling_price, buying_price, stock_primary, stock_secondary, min_stock) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        $inserted_count = 0;
        foreach ($products as $product) {
            $stmt->execute($product);
            $inserted_count++;
        }
        
        echo "<div style='color: green; padding: 5px;'>✅ " . $inserted_count . " produtos inseridos</div>";
        
        // Commit transaction
        $conn->commit();
        
        // 4. Verification
        echo "<h3>4. Verificação final...</h3>";
        
        // Count products by category
        $stmt = $conn->prepare("SELECT pc.name as category_name, COUNT(p.id) as product_count 
                               FROM product_categories pc 
                               LEFT JOIN products p ON pc.id = p.category_id 
                               GROUP BY pc.id, pc.name 
                               ORDER BY pc.name");
        $stmt->execute();
        $category_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($category_counts as $count) {
            echo "<div style='color: green; padding: 3px;'>✅ {$count['category_name']}: {$count['product_count']} produtos</div>";
        }
        
        // Total verification
        $stmt = $conn->prepare("SELECT COUNT(*) FROM products");
        $stmt->execute();
        $total_products = $stmt->fetchColumn();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM product_categories");
        $stmt->execute();
        $total_categories = $stmt->fetchColumn();
        
        echo "<div style='background: #d4edda; padding: 20px; border-left: 4px solid #28a745; margin: 20px 0;'>";
        echo "<h3 style='color: #155724; margin-top: 0;'>🎉 Lista Definitiva Inserida com Sucesso!</h3>";
        echo "<p><strong>Produtos inseridos na base de dados:</strong></p>";
        echo "<ul>";
        echo "<li>✅ <strong>$total_categories categorias</strong> organizadas</li>";
        echo "<li>✅ <strong>$total_products produtos</strong> completos inseridos</li>";
        echo "<li>✅ <strong>Preços de venda e compra</strong> definidos</li>";
        echo "<li>✅ <strong>Stock inicial</strong> para ambas cantinas</li>";
        echo "<li>✅ <strong>Stock mínimo</strong> configurado</li>";
        echo "</ul>";
        
        echo "<h4 style='color: #155724;'>📊 Resumo por Categoria:</h4>";
        echo "<div style='display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin: 15px 0;'>";
        foreach ($category_counts as $count) {
            echo "<div style='background: white; padding: 10px; border-radius: 5px; text-align: center; border: 1px solid #c3e6cb;'>";
            echo "<strong style='color: #28a745;'>{$count['category_name']}</strong><br>";
            echo "<span style='font-size: 1.2rem; font-weight: bold;'>{$count['product_count']}</span> produtos";
            echo "</div>";
        }
        echo "</div>";
        
        echo "<p><a href='products.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 1.1rem; margin-right: 10px;'>🍽️ Ver Produtos</a>";
        echo "<a href='sales.php' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 1.1rem;'>🛒 Iniciar Vendas</a></p>";
        echo "</div>";
        
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ <strong>Falha na conexão com a base de dados!</strong>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($conn && $conn->inTransaction()) {
        $conn->rollback();
    }
    
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