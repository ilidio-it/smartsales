<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'includes/lang.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (!empty($username) && !empty($password)) {
        $database = new Database();
        $conn = $database->connect();
        
        $stmt = $conn->prepare("SELECT id, username, password, name, user_type, canteen_type, permissions, status FROM users WHERE username = ? AND status = 'active'");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['canteen_type'] = $user['canteen_type'];
            $_SESSION['permissions'] = $user['permissions'];
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error_message = __('invalid_credentials');
        }
    } else {
        $error_message = __('invalid_credentials');
    }
}

// Check for session timeout
if (isset($_GET['timeout'])) {
    $error_message = __('session_expired');
}
?>

<!DOCTYPE html>
<html lang="<?php echo $current_lang; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('login_title'); ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="public/image.png" type="image/png">
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-logo">
                    <i class="fas fa-utensils"></i>
                </div>
                <h1 class="login-title"><?php echo __('login_title'); ?></h1>
                <p class="login-subtitle">Sistema de Gestão de Cantina</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username" class="form-label">
                        <i class="fas fa-user"></i>
                        <?php echo __('username'); ?>
                    </label>
                    <input type="text" id="username" name="username" class="form-control" required 
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                           autocomplete="username" placeholder="Digite seu usuário">
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">
                        <i class="fas fa-lock"></i>
                        <?php echo __('password'); ?>
                    </label>
                    <input type="password" id="password" name="password" class="form-control" required 
                           autocomplete="current-password" placeholder="Digite sua senha">
                </div>
                
                <button type="submit" class="btn btn-primary btn-large w-full">
                    <i class="fas fa-sign-in-alt"></i>
                    <?php echo __('login'); ?>
                </button>
            </form>
            
            <div class="language-switcher" style="margin-top: 2rem; justify-content: center;">
                <a href="?lang=en" class="lang-btn <?php echo $current_lang == 'en' ? 'active' : ''; ?>">English</a>
                <a href="?lang=pt" class="lang-btn <?php echo $current_lang == 'pt' ? 'active' : ''; ?>">Português</a>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>