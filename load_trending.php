<?php
include 'db_connect.php';

$sql = "SELECT * FROM games ORDER BY rating DESC LIMIT 4";
$result = $conn->query($sql);

// Mapping of game titles to default images
$defaultImages = [
    'Star Wars Jedi: Fallen Order' => 'jedi_fallen_order.jpg',
    'Elden Ring' => 'elden_ring.jpg',
    'Valorant' => 'valorant.jpg',
    'League of Legends' => 'league_of_legends.jpg',
    'Brawlhalla' => 'Brawlhalla.jpg',
    'NBA 2K22' => 'nba_2k22.jpg',
    'Black Myth Wukong' => 'black_myth_wukong.jpg',
    'Devil May Cry 5' => 'devil_may_cry_5.jpg',
    'Need For Speed Payback' => 'need_for_speed_payback.jpg'
];

while($row = $result->fetch_assoc()) {
    // Determine the image path
    $imagePath = 'assets/uploads/';
    
    if (!empty($row['image'])) {
        $imagePath .= $row['image'];
    } else {
        // Use the default image if available, otherwise fall back to generic
        $imagePath .= isset($defaultImages[$row['title']]) ? 
                     $defaultImages[$row['title']] : 
                     'default_game.jpg';
    }
    
    // Ensure the path is safe for output
    $safeImagePath = htmlspecialchars($imagePath);
    $safeTitle = htmlspecialchars($row['title']);
    
    echo '
    <div class="col-md-3">
        <div class="card game-card">
            <img src="'.$safeImagePath.'" class="card-img-top" alt="'.$safeTitle.'">
            <div class="card-body">
                <h5>'.$safeTitle.'</h5>
                <div class="rating">⭐ '.htmlspecialchars($row['rating']).'</div>
            </div>
        </div>
    </div>';
}
?>