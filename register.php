<?php
require_once 'auth.php';
require_guest(); // Redirect if already logged in
require_once 'db_connect.php';

// Initialize variables at the top
$error = '';
$success = '';
$username = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($conn->real_escape_string($_POST['username'] ?? ''));
    $email = trim($conn->real_escape_string($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $avatar = 'default.png';

    // Validate inputs
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters';
    } else {
        // Check if username/email exists
        $result = $conn->query("SELECT id FROM users WHERE username = '$username' OR email = '$email' LIMIT 1");
        
        if ($result->num_rows > 0) {
            $error = 'Username or email already exists';
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);
            
            // Handle avatar upload
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'assets/avatars/';
                if (!file_exists($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                
                $fileExt = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($fileExt, $allowedExtensions)) {
                    $newFilename = uniqid('avatar_', true) . '.' . $fileExt;
                    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $newFilename)) {
                        $avatar = $newFilename;
                    }
                }
            }
            
            // Insert new user
            $insertResult = $conn->query("INSERT INTO users (username, email, password, avatar) 
                         VALUES ('$username', '$email', '$hashed_password', '$avatar')");
            
            if ($insertResult) {
                $_SESSION['user_id'] = $conn->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['avatar'] = $avatar;
                $success = 'Registration successful! Redirecting...';
                header("Refresh: 2; url=chat.php");
            } else {
                $error = 'Registration failed. Please try again. Error: ' . $conn->error;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Register | GameSphere</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background-color: #36393f; color: #fff; }
        .register-box { max-width: 500px; margin: 50px auto; }
        .avatar-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid #5865F2;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-box bg-dark p-4 rounded shadow">
            <h2 class="text-center mb-4">Create Your GameSphere Account</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php else: ?>
                <form method="POST" enctype="multipart/form-data">
                    <div class="text-center mb-3">
                        <img id="avatar-preview" class="avatar-preview" src="#" alt="Avatar Preview">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Avatar (Optional)</label>
                        <input type="file" id="avatar-upload" name="avatar" class="form-control" accept="image/*">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required 
                               value="<?= htmlspecialchars($username) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required
                               value="<?= htmlspecialchars($email) ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password (min 8 characters)</label>
                        <input type="password" name="password" class="form-control" required minlength="8">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8">
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 mb-3">Register</button>
                    
                    <div class="text-center">
                        Already have an account? <a href="login.php">Login here</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Avatar preview
    document.getElementById('avatar-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        const preview = document.getElementById('avatar-preview');
        const reader = new FileReader();
        
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        
        reader.readAsDataURL(file);
    });
    </script>
</body>
</html>