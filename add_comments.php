<?php
include 'D:/xampp/htdocs/gamesphere/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $postId = intval($_POST['post_id']);
    $content = $conn->real_escape_string($_POST['content']);
    $userId = $_SESSION['user_id'];
    
    $conn->query("INSERT INTO post_comments (post_id, user_id, content) 
                 VALUES ($postId, $userId, '$content')");
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>
