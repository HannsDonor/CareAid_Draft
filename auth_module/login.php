<?php
session_start();
include '../db_config/connection_db.php';

$message = '';
$redirect_url = '';
$role_popup = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $message = "Both fields are required!";
    } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
        $message = "Invalid username format.";
    } else {
        $stmt = $conn->prepare("SELECT account_id, username, password_hash, role, account_status FROM accounts WHERE username = ? LIMIT 1");
        if (!$stmt) {
            $message = "Login system error. Please try again.";
        } else {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows === 1) {
                $stmt->bind_result($account_id, $db_username, $db_password_hash, $role, $account_status);
                $stmt->fetch();

                if (!password_verify($password, $db_password_hash)) {
                    $message = "Incorrect password!";
                } elseif ($account_status !== 'active') {
                    $message = "Your account is not active. Please contact the administrator.";
                } else {
                    $_SESSION['account_id'] = $account_id;
                    $_SESSION['username'] = $db_username;
                    $_SESSION['role'] = $role;

                    if ($role === 'admin') {
                        $redirect_url = '../admin_module/admin_dashboard.php';
                    } elseif ($role === 'health_worker') {
                        $redirect_url = '../medical_module/health_worker_dashboard.php';
                    } elseif ($role === 'finance_worker') {
                        $redirect_url = '../financial_module/finance_dashboard.php';
                    } else {
                        $redirect_url = '../dashboard.php';
                    }

                    $role_popup = ucwords(str_replace('_', ' ', $role));
                }
            } else {
                $message = "Username not found!";
            }

            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CareAid Login</title>
    <link rel="stylesheet" href="../UI_Design/auth_style.css">
</head>
<body>
    <div class="auth-container">
        <div class="auth-box">
            <h1>CareAid Login</h1>
            
            <?php if (!empty($message)): ?>
                <div class="message error-message">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        placeholder="Enter your username"
                        value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required 
                        placeholder="Enter your password"
                    >
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="auth-footer">
                <p>Don't have an account? <a href="create_account.php">Create one here</a></p>
            </div>
        </div>
    </div>
</body>
<?php if (!empty($redirect_url) && !empty($role_popup)): ?>
<script>
    alert('Login successful! Role: <?php echo htmlspecialchars($role_popup, ENT_QUOTES, 'UTF-8'); ?>');
    window.location.href = '<?php echo htmlspecialchars($redirect_url, ENT_QUOTES, 'UTF-8'); ?>';
</script>
<?php endif; ?>
</html>