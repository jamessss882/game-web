<?php
include 'D:\xampp\htdocs\gamesphere\header.php';
include 'D:\xampp\htdocs\gamesphere\db_connect.php';

$category_id = intval($_GET['id'] ?? 0);

// Get category info
$category = $conn->query("SELECT * FROM forum_categories WHERE id = $category_id")->fetch_assoc();

if (!$category) {
    header("Location: D:\xampp\htdocs\gamesphere\forums.php");
    exit;
}
?>

<div class="container mt-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="D:\xampp\htdocs\gamesphere\forums.php">Forums</a></li>
            <li class="breadcrumb-item active"><?= htmlspecialchars($category['name']) ?></li>
        </ol>
    </nav>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><?= htmlspecialchars($category['name']) ?></h1>
        <a href="D:\xampp\htdocs\gamesphere\new_thread.php?category=<?= $category_id ?>" class="btn btn-primary">New Thread</a>
    </div>
    
    <!-- Threads list would go here -->
</div>

<?php 
include 'D:\xampp\htdocs\gamesphere\footer.php';
$conn->close();
?>
