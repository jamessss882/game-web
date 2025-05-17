<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameSphere</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <link href="/gamesphere/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="/gamesphere/index.php">🎮 GameSphere</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="/gamesphere/index.php">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="/gamesphere/games.php">Games</a></li>
                    <li class="nav-item"><a class="nav-link" href="/gamesphere/chat.php">Chat</a></li>
                    <li class="nav-item"><a class="nav-link" href="/gamesphere/leaderboard.php">Leaderboard</a></li>
                </ul>
                <ul class="navbar-nav">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <img src="/gamesphere/assets/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.png') ?>" 
                                     width="30" height="30" class="rounded-circle me-1">
                                <?= htmlspecialchars($_SESSION['username']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="/gamesphere/profile.php">Profile</a></li>
                                <li><a class="dropdown-item" href="/gamesphere/settings.php">Settings</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="/gamesphere/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item"><a class="nav-link" href="/gamesphere/login.php">Login</a></li>
                        <li class="nav-item"><a class="nav-link" href="/gamesphere/register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container mt-4">