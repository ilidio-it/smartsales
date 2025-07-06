<?php
require_once 'config/database.php';

echo "<h2>Correção da Senha do Administrador</h2>";

try {
    $database = new Database();
    $conn = $database->connect();
    
    if ($conn) {
        // Verificar se o usuário admin existe
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = 'admin'");
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            echo "<div style='color: blue; padding: 10px; border: 1px solid blue; margin: 10px 0;'>";
            echo "👤 <strong>Usuário admin encontrado</strong><br>";
            echo "ID: " . $admin['id'] . "<br>";
            echo "Username: " . $admin['username'] . "<br>";
            echo "</div>";
            
            // Gerar nova senha hash
            $new_password = 'admin';
            $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Atualizar a senha
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
            $result = $stmt->execute([$password_hash]);
            
            if ($result) {
                echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                echo "✅ <strong>Senha atualizada com sucesso!</strong><br>";
                echo "Username: <strong>admin</strong><br>";
                echo "Password: <strong>admin</strong><br>";
                echo "</div>";
                
                // Testar a nova senha
                if (password_verify('admin', $password_hash)) {
                    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                    echo "✅ <strong>Verificação da senha: OK</strong><br>";
                    echo "A senha 'admin' foi configurada corretamente.";
                    echo "</div>";
                } else {
                    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                    echo "❌ <strong>Erro na verificação da senha</strong>";
                    echo "</div>";
                }
            } else {
                echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                echo "❌ <strong>Erro ao atualizar a senha</strong>";
                echo "</div>";
            }
        } else {
            echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
            echo "⚠️ <strong>Usuário admin não encontrado. Criando...</strong>";
            echo "</div>";
            
            // Criar usuário admin
            $password_hash = password_hash('admin', PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, name, user_type, canteen_type, permissions) 
                                   VALUES ('admin', ?, 'Administrator', 'admin', 'both', ?)");
            
            $permissions = json_encode(['all' => true]);
            $result = $stmt->execute([$password_hash, $permissions]);
            
            if ($result) {
                echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                echo "✅ <strong>Usuário admin criado com sucesso!</strong><br>";
                echo "Username: <strong>admin</strong><br>";
                echo "Password: <strong>admin</strong><br>";
                echo "</div>";
            } else {
                echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                echo "❌ <strong>Erro ao criar usuário admin</strong>";
                echo "</div>";
            }
        }
        
        echo "<h3>Instruções:</h3>";
        echo "<div style='background: #f0f8ff; padding: 15px; border-left: 4px solid #2196F3;'>";
        echo "<strong>1.</strong> Acesse: <a href='login.php'>login.php</a><br>";
        echo "<strong>2.</strong> Use as credenciais:<br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Username: <strong>admin</strong><br>";
        echo "&nbsp;&nbsp;&nbsp;&nbsp;Password: <strong>admin</strong><br>";
        echo "<strong>3.</strong> Após o login, você pode alterar a senha no painel de administração.";
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
a {
    color: #2196F3;
    text-decoration: none;
}
a:hover {
    text-decoration: underline;
}
</style>