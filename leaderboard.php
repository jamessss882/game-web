<?php
require_once 'auth.php';
require_once 'db_connect.php';
require_once 'header.php';

// Get all games for filter dropdown
$games = $conn->query("SELECT id, title FROM games")->fetch_all(MYSQLI_ASSOC);

// Get current filter
$game_filter = isset($_GET['game_id']) ? intval($_GET['game_id']) : null;

// Base query
$query = "SELECT 
            u.username,
            u.avatar,
            g.title AS game_title,
            l.score,
            l.last_updated,
            RANK() OVER (ORDER BY l.score DESC) AS overall_rank,
            RANK() OVER (PARTITION BY l.game_id ORDER BY l.score DESC) AS game_rank
          FROM leaderboard l
          JOIN users u ON l.user_id = u.id
          JOIN games g ON l.game_id = g.id";

// Add filter if specified
if ($game_filter) {
    $query .= " WHERE l.game_id = $game_filter";
}

$query .= " ORDER BY l.score DESC LIMIT 100";

$leaderboard = $conn->query($query)->fetch_all(MYSQLI_ASSOC);
?>

<div class="container mt-4">
    <h1 class="mb-4">GameSphere Leaderboard</h1>
    
    <!-- Game Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label for="game_id" class="form-label">Filter by Game</label>
                    <select name="game_id" id="game_id" class="form-select" onchange="this.form.submit()">
                        <option value="">All Games</option>
                        <?php foreach ($games as $game): ?>
                            <option value="<?= $game['id'] ?>" <?= $game_filter == $game['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($game['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Apply Filter</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Leaderboard Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Rank</th>
                            <th>Player</th>
                            <th>Game</th>
                            <th>Score</th>
                            <th>Last Updated</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($leaderboard)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No records found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($leaderboard as $entry): ?>
                                <tr>
                                    <td>
                                        <?= $game_filter ? $entry['game_rank'] : $entry['overall_rank'] ?>
                                        <?php if ($entry['overall_rank'] <= 3): ?>
                                            <span class="badge bg-<?= 
                                                $entry['overall_rank'] == 1 ? 'gold' : 
                                                ($entry['overall_rank'] == 2 ? 'silver' : 'bronze')
                                            ?> ms-2">
                                                <?= $entry['overall_rank'] == 1 ? '🥇' : 
                                                   ($entry['overall_rank'] == 2 ? '🥈' : '🥉') ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <img src="/gamesphere/assets/avatars/<?= htmlspecialchars($entry['avatar']) ?>" 
                                             alt="<?= htmlspecialchars($entry['username']) ?>" 
                                             class="rounded-circle me-2" width="30" height="30">
                                        <?= htmlspecialchars($entry['username']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($entry['game_title']) ?></td>
                                    <td><?= number_format($entry['score']) ?></td>
                                    <td><?= date('M j, Y g:i a', strtotime($entry['last_updated'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .badge-gold { background-color: #FFD700; color: #000; }
    .badge-silver { background-color: #C0C0C0; color: #000; }
    .badge-bronze { background-color: #CD7F32; color: #000; }
</style>

<?php require_once 'footer.php'; ?>
