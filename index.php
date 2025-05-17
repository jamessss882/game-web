<?php include 'D:/xampp/htdocs/gamesphere/header.php'; ?>

<section class="hero-section text-center py-5 bg-secondary text-white">
    <div class="container">
        <h1>Welcome to GameSphere</h1>
        <p>Your ultimate gaming community</p>
    </div>
</section>

<section class="trending-games py-5">
    <div class="container">
        <h2 class="mb-4">🔥 Trending Games</h2>
        <div class="row" id="trending-games-container">
            <!-- Games loaded via AJAX -->
            <?php include 'D:/xampp/htdocs/gamesphere/header.php'; ?>

<section class="hero-section text-center py-5 bg-secondary text-white">
    <div class="container">
        <h1>Welcome to GameSphere</h1>
        <p>Your ultimate gaming community</p>
    </div>
</section>

<section class="trending-games py-5">
    <div class="container">
        <h2 class="mb-4">🔥 Trending Games</h2>
        <div class="row" id="trending-games-container">
            <!-- Games will be loaded here via JavaScript -->
        </div>
    </div>
</section>

<script>
// Load trending games on page load
document.addEventListener('DOMContentLoaded', function() {
    fetch('http://localhost/gamesphere/load_trending.php')
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.text();
        })
        .then(data => {
            const container = document.getElementById('trending-games-container');
            if (container) {
                container.innerHTML = data;
            } else {
                console.error('Trending games container not found');
            }
        })
        .catch(error => {
            console.error('Error loading trending games:', error);
            // Fallback: Show error message to user
            const container = document.getElementById('trending-games-container');
            if (container) {
                container.innerHTML = '<div class="alert alert-danger">Failed to load games. Please try again later.</div>';
            }
        });
});
</script>

<?php include 'D:/xampp/htdocs/gamesphere/footer.php'; ?>