<?php
include 'D:/xampp/htdocs/gamesphere/db_connect.php';

if (isset($_GET['post_id'])) {
    $postId = intval($_GET['post_id']);
    $result = $conn->query("SELECT c.*, u.username, u.avatar 
                           FROM post_comments c
                           JOIN users u ON c.user_id = u.id
                           WHERE c.post_id = $postId
                           ORDER BY c.created_at DESC");
    
    $comments = [];
    while ($row = $result->fetch_assoc()) {
        $comments[] = [
            'username' => htmlspecialchars($row['username']),
            'avatar' => htmlspecialchars($row['avatar']),
            'content' => nl2br(htmlspecialchars($row['content'])),
            'created_at' => date('M j, Y g:i a', strtotime($row['created_at']))
        ];
    }
    
    echo json_encode($comments);
}
?>
