<?php
require_once 'auth.php';
require_guest(); // Redirect if already logged in
require_once 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($conn->real_escape_string($_POST['username']));
    $password = trim($_POST['password']);
    
    $result = $conn->query("SELECT * FROM users WHERE username = '$username' LIMIT 1");
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['avatar'] = $user['avatar'];
            
            header("Location: chat.php");
            exit();
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
}
?>

<!DOCTYPE html>
<html>
<!-- Rest of your login.php content remains the same -->
<head>
    <title>Login | GameSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #36393f; color: #fff; }
        .login-box { max-width: 400px; margin: 100px auto; }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box bg-dark p-4 rounded shadow">
            <h2 class="text-center mb-4">GameSphere Login</h2>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= $error ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
            <div class="mt-3 text-center">
                <a href="register.php" class="text-muted">Create an account</a>
            </div>
        </div>
    </div>
</body>
</html>