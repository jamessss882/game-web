<?php include 'header.php'; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <input type="text" id="game-search" class="form-control" placeholder="Search games...">
    </div>
    <div class="col-md-3">
        <select id="genre-filter" class="form-select">
            <option value="">All Genres</option>
            <option value="RPG">RPG</option>
            <option value="FPS">FPS</option>
            <option value="Racing">Racing</option>
            <option value="Sports">Sports</option>
            <option value="Adventure">Adventure</option>
        </select>
    </div>
</div>

<div class="row" id="games-container">
    <!-- Games loaded via PHP initially -->
    <?php
    include 'db_connect.php';
    
    // Mapping of game titles to default images
    $defaultImages = [
        'Star Wars Jedi: Fallen Order' => 'jedi_fallen_order.jpg',
        'Elden Ring' => 'elden_ring.jpg',
        'FIFA 23' => 'FIFA 23.jpg',
        'League of Legends' => 'league_of_legends.jpg',
        'Brawlhalla' => 'Brawlhalla.jpg',
        'NBA 2K22' => 'nba_2k22.jpg',
        'Black Myth Wukong' => 'black_myth_wukong.jpg',
        'Devil May Cry 5' => 'devil_may_cry_5.jpg',
        'Need For Speed Payback' => 'need_for_speed_payback.jpg'
    ];
    
    $sql = "SELECT * FROM games LIMIT 12";
    $result = $conn->query($sql);
    
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
        
        // Escape output for security
        $safeImagePath = htmlspecialchars($imagePath);
        $safeTitle = htmlspecialchars($row['title']);
        $safeGenre = htmlspecialchars($row['genre']);
        $safeDescription = htmlspecialchars(substr($row['description'], 0, 100) . '...');
        $safeRating = htmlspecialchars($row['rating']);
        
        echo '
        <div class="col-md-3 mb-4 game-card" data-title="'.$safeTitle.'" data-genre="'.$safeGenre.'">
            <div class="card h-100">
                <img src="'.$safeImagePath.'" class="card-img-top" alt="'.$safeTitle.'">
                <div class="card-body">
                    <h5 class="card-title">'.$safeTitle.'</h5>
                    <span class="badge bg-primary">'.$safeGenre.'</span>
                    <p class="card-text mt-2">'.$safeDescription.'</p>
                </div>
                <div class="card-footer">
                    <small class="text-muted">⭐ '.$safeRating.'/5.0</small>
                </div>
            </div>
        </div>';
    }
    ?>
</div>

<script src="game_search.js"></script>
<?php include 'footer.php'; ?>