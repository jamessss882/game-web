<?php
include 'D:/xampp/htdocs/gamesphere/db_connect.php';

$genre = $_GET['genre'] ?? '';
$search = $_GET['search'] ?? '';

$sql = "SELECT * FROM games WHERE 1=1";

if (!empty($genre)) {
    $sql .= " AND genre = '$genre'";
}

if (!empty($search)) {
    $sql .= " AND title LIKE '%$search%'";
}

$result = $conn->query($sql);

while($row = $result->fetch_assoc()) {
    echo '
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <img src="../assets/images/'.$row['cover_image'].'" class="card-img-top">
            <div class="card-body">
                <h5 class="card-title">'.$row['title'].'</h5>
                <span class="badge bg-primary">'.$row['genre'].'</span>
            </div>
        </div>
    </div>';
}
?>
