<?php
require_once 'config/database.php';

echo "<h2>Debug do Sistema de Login</h2>";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    echo "<h3>Dados Recebidos:</h3>";
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0;'>";
    echo "Username: '" . htmlspecialchars($username) . "'<br>";
    echo "Password: '" . htmlspecialchars($password) . "'<br>";
    echo "</div>";
    
    if (!empty($username) && !empty($password)) {
        try {
            $database = new Database();
            $conn = $database->connect();
            
            echo "<h3>Busca no Banco de Dados:</h3>";
            $stmt = $conn->prepare("SELECT id, username, password, name, user_type, canteen_type, permissions, status FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                echo "✅ <strong>Usuário encontrado:</strong><br>";
                echo "ID: " . $user['id'] . "<br>";
                echo "Username: " . $user['username'] . "<br>";
                echo "Name: " . $user['name'] . "<br>";
                echo "Status: " . $user['status'] . "<br>";
                echo "User Type: " . $user['user_type'] . "<br>";
                echo "Canteen Type: " . $user['canteen_type'] . "<br>";
                echo "</div>";
                
                echo "<h3>Verificação da Senha:</h3>";
                if (password_verify($password, $user['password'])) {
                    echo "<div style='color: green; padding: 10px; border: 1px solid green; margin: 10px 0;'>";
                    echo "✅ <strong>Senha correta!</strong><br>";
                    echo "O login deveria funcionar.";
                    echo "</div>";
                } else {
                    echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                    echo "❌ <strong>Senha incorreta!</strong><br>";
                    echo "Hash armazenado: " . substr($user['password'], 0, 50) . "...<br>";
                    echo "Execute o arquivo fix_admin_password.php para corrigir.";
                    echo "</div>";
                }
                
                if ($user['status'] !== 'active') {
                    echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
                    echo "⚠️ <strong>Conta inativa!</strong><br>";
                    echo "Status atual: " . $user['status'];
                    echo "</div>";
                }
            } else {
                echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
                echo "❌ <strong>Usuário não encontrado!</strong><br>";
                echo "Execute o arquivo fix_admin_password.php para criar o usuário admin.";
                echo "</div>";
            }
            
        } catch (PDOException $e) {
            echo "<div style='color: red; padding: 10px; border: 1px solid red; margin: 10px 0;'>";
            echo "❌ <strong>Erro de base de dados:</strong> " . $e->getMessage();
            echo "</div>";
        }
    } else {
        echo "<div style='color: orange; padding: 10px; border: 1px solid orange; margin: 10px 0;'>";
        echo "⚠️ <strong>Dados incompletos!</strong><br>";
        echo "Username e password são obrigatórios.";
        echo "</div>";
    }
}
?>

<h3>Teste de Login:</h3>
<form method="POST" style="background: white; padding: 20px; border-radius: 5px; max-width: 400px;">
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px;">Username:</label>
        <input type="text" name="username" value="admin" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <div style="margin-bottom: 15px;">
        <label style="display: block; margin-bottom: 5px;">Password:</label>
        <input type="password" name="password" value="admin" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
    </div>
    
    <button type="submit" style="background: #2196F3; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
        Testar Login
    </button>
</form>

<div style="margin-top: 20px; background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
    <strong>Passos para resolver:</strong><br>
    1. Execute <a href="fix_admin_password.php">fix_admin_password.php</a><br>
    2. Teste o login acima<br>
    3. Se funcionar, acesse <a href="login.php">login.php</a>
</div>

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