// Game search and filter functionality
document.getElementById('game-search').addEventListener('input', function() {
    const searchTerm = this.value;
    const genre = document.getElementById('genre-filter').value;
    
    fetch(`assets/php/filter_games.php?search=${searchTerm}&genre=${genre}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('games-container').innerHTML = data;
        });
});

document.getElementById('genre-filter').addEventListener('change', function() {
    const searchTerm = document.getElementById('game-search').value;
    const genre = this.value;
    
    fetch(`assets/php/filter_games.php?search=${searchTerm}&genre=${genre}`)
        .then(response => response.text())
        .then(data => {
            document.getElementById('games-container').innerHTML = data;
        });
});
