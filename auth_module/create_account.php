<?php
include '../db_config/connection_db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $role = $_POST['role'];

    // Basic validation
    if (empty($username) || empty($password) || empty($role)) {
        $message = "All fields are required!";
    } else {
        // Check if username already exists
        $stmt = $conn->prepare("SELECT account_id FROM accounts WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();
        
        if($stmt->num_rows > 0) {
            $message = "Username already taken!";
        } else {
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            // Insert into accounts table
            $stmt = $conn->prepare("INSERT INTO accounts (username, password_hash, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $password_hash, $role);

            if($stmt->execute()) {
                $message = "Account created successfully!";
            } else {
                $message = "Error creating account: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account</title>
<link rel="stylesheet" href="../UI_Design/auth_style.css">
</head>
<body>
<div class="container">
    <h2>Create Account</h2>

    <?php if($message): ?>
        <div class="message <?php echo (strpos($message,'successfully')!==false)?'success':'error'; ?>">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <select name="role" required>
            <option value="">Select Role</option>
            <option value="senior">Senior</option>
            <option value="admin">Admin</option>
            <option value="health_worker">Health Worker</option>
            <option value="finance_officer">Finance Officer</option>
        </select>
        <button type="submit">Create Account</button>
    </form>
</div>
</body>
</html>