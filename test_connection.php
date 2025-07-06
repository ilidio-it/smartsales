<?php
require_once 'config/database.php';

echo "<h2>Teste de Conexão com a Base de Dados</h2>";

try {
    $database = new Database();
    $conn = $database->connect();
    
    if ($conn) {
        echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
        echo "✅ <strong>Conexão estabelecida com sucesso!</strong><br>";
        echo "Base de dados: school_canteen<br>";
        echo "Host: localhost<br>";
        echo "</div>";
        
        // Testar se as tabelas existem
        echo "<h3>Verificação das Tabelas:</h3>";
        $tables = ['users', 'products', 'clients', 'sales', 'sale_items', 'client_transactions', 'stock_movements'];
        
        foreach ($tables as $table) {
            try {
                $stmt = $conn->query("SELECT COUNT(*) FROM $table");
                $count = $stmt->fetchColumn();
                echo "<div style='color: green; padding: 5px;'>✅ Tabela '$table': $count registros</div>";
            } catch (PDOException $e) {
                echo "<div style='color: red; padding: 5px;'>❌ Tabela '$table': Não encontrada</div>";
            }
        }
        
        // Testar usuário admin
        echo "<h3>Verificação do Usuário Admin:</h3>";
        try {
            $stmt = $conn->prepare("SELECT username, name, user_type, canteen_type FROM users WHERE username = 'admin'");
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin) {
                echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                echo "✅ <strong>Usuário Admin encontrado:</strong><br>";
                echo "Nome: " . $admin['name'] . "<br>";
                echo "Tipo: " . $admin['user_type'] . "<br>";
                echo "Cantina: " . $admin['canteen_type'] . "<br>";
                echo "</div>";
            } else {
                echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                echo "❌ Usuário Admin não encontrado!";
                echo "</div>";
            }
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ Erro ao verificar usuário admin: " . $e->getMessage();
            echo "</div>";
        }
        
    } else {
        echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
        echo "❌ <strong>Falha na conexão!</strong>";
        echo "</div>";
    }
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
    echo "❌ <strong>Erro de conexão:</strong><br>";
    echo "Mensagem: " . $e->getMessage() . "<br>";
    echo "Código: " . $e->getCode();
    echo "</div>";
    
    echo "<h3>Possíveis soluções:</h3>";
    echo "<ul>";
    echo "<li>Verificar se o MySQL está rodando</li>";
    echo "<li>Verificar se a base de dados 'school_canteen' existe</li>";
    echo "<li>Verificar as credenciais em config/database.php</li>";
    echo "<li>Executar o script SQL para criar as tabelas</li>";
    echo "</ul>";
}
?>

<style>
body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: #f5f5f5;
}
h2, h3 {
    color: #333;
}
</style>