document.addEventListener('DOMContentLoaded', function() {
    // Toggle comments
    document.querySelectorAll('.comment-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            const commentsSection = document.getElementById(`comments-${postId}`);
            
            // Toggle visibility
            if (commentsSection.style.display === 'none') {
                commentsSection.style.display = 'block';
                loadComments(postId);
            } else {
                commentsSection.style.display = 'none';
            }
        });
    });

    // Handle comment submission
    document.querySelectorAll('.comment-form').forEach(form => {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const postId = this.dataset.postId;
            const content = this.querySelector('input').value;
            
            fetch('/gamesphere/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}&content=${encodeURIComponent(content)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadComments(postId);
                    this.querySelector('input').value = '';
                }
            });
        });
    });

    // Handle likes
    document.querySelectorAll('.like-btn').forEach(button => {
        button.addEventListener('click', function() {
            const postId = this.dataset.postId;
            
            fetch('/gamesphere/toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `post_id=${postId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.liked) {
                    this.innerHTML = '<i class="bi bi-heart-fill text-danger"></i> Liked';
                } else {
                    this.innerHTML = '<i class="bi bi-heart"></i> Like';
                }
            });
        });
    });
});

function loadComments(postId) {
    fetch(`/gamesphere/get_comments.php?post_id=${postId}`)
        .then(response => response.json())
        .then(comments => {
            const container = document.querySelector(`#comments-${postId} .comments-container`);
            container.innerHTML = '';
            
            comments.forEach(comment => {
                container.innerHTML += `
                    <div class="card mb-2">
                        <div class="card-body py-2">
                            <div class="d-flex">
                                <img src="/gamesphere/assets/avatars/${comment.avatar}" 
                                     class="rounded-circle me-2" width="30" height="30">
                                <div>
                                    <strong>${comment.username}</strong>
                                    <p class="mb-0">${comment.content}</p>
                                    <small class="text-muted">${comment.created_at}</small>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        });
}
