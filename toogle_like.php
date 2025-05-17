<?php
include 'D:/xampp/htdocs/gamesphere/db_connect.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $postId = intval($_POST['post_id']);
    $userId = $_SESSION['user_id'];
    
    // Check if already liked
    $result = $conn->query("SELECT 1 FROM post_likes WHERE post_id = $postId AND user_id = $userId");
    
    if ($result->num_rows > 0) {
        // Unlike
        $conn->query("DELETE FROM post_likes WHERE post_id = $postId AND user_id = $userId");
        echo json_encode(['liked' => false]);
    } else {
        // Like
        $conn->query("INSERT INTO post_likes (post_id, user_id) VALUES ($postId, $userId)");
        echo json_encode(['liked' => true]);
    }
}
?>