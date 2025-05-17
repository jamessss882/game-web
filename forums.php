<?php
include 'D:/xampp/htdocs/gamesphere/db_connect.php';
include 'D:/xampp/htdocs/gamesphere/header.php';
session_start();

// Get current channel
$current_channel = isset($_GET['channel']) ? $conn->real_escape_string($_GET['channel']) : 'general-chat';

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        exit;
    }

    $message = $conn->real_escape_string($_POST['message']);
    $userId = $_SESSION['user_id'];
    $channel = $conn->real_escape_string($_POST['channel']);
    $mediaUrl = null;
    $mediaType = 'none';

    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'D:/xampp/htdocs/gamesphere/assets/uploads/';
        if (!file_exists($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $fileExt = strtolower(pathinfo($_FILES['media']['name'], PATHINFO_EXTENSION));
        $allowedTypes = [
            'image' => ['jpg', 'jpeg', 'png', 'gif'],
            'video' => ['mp4', 'webm']
        ];

        foreach ($allowedTypes as $type => $extensions) {
            if (in_array($fileExt, $extensions)) {
                $mediaType = $type;
                break;
            }
        }

        if ($mediaType !== 'none') {
            $newFilename = uniqid('media_', true) . '.' . $fileExt;
            if (move_uploaded_file($_FILES['media']['tmp_name'], $uploadDir . $newFilename)) {
                $mediaUrl = $newFilename;
            }
        }
    }

    // Insert message
    $conn->query("INSERT INTO chat_messages (user_id, channel, content, media_url, media_type) 
                 VALUES ($userId, '$channel', '$message', " . ($mediaUrl ? "'$mediaUrl'" : "NULL") . ", '$mediaType')");
    
    if ($conn->error) {
        echo json_encode(['error' => $conn->error]);
        exit;
    }

    // Return new message data
    $newMsg = $conn->query("SELECT m.*, u.username, u.avatar 
                           FROM chat_messages m
                           JOIN users u ON m.user_id = u.id
                           WHERE m.id = " . $conn->insert_id)->fetch_assoc();
    
    echo json_encode($newMsg);
    exit;
}

// AJAX request for new messages
if (isset($_GET['get_messages'])) {
    $lastId = intval($_GET['last_id']);
    $channel = $conn->real_escape_string($_GET['channel']);
    
    $messages = $conn->query("SELECT m.*, u.username, u.avatar 
                            FROM chat_messages m
                            JOIN users u ON m.user_id = u.id
                            WHERE m.channel = '$channel' AND m.id > $lastId
                            ORDER BY m.created_at ASC");
    
    $result = [];
    while ($row = $messages->fetch_assoc()) $result[] = $row;
    
    echo json_encode($result);
    exit;
}

// Get channels
$channels = $conn->query("SELECT * FROM chat_channels ORDER BY name")->fetch_all(MYSQLI_ASSOC);

// Get initial messages
$messages = $conn->query("SELECT m.*, u.username, u.avatar 
                         FROM chat_messages m
                         JOIN users u ON m.user_id = u.id
                         WHERE m.channel = '$current_channel'
                         ORDER BY m.created_at DESC LIMIT 50")->fetch_all(MYSQLI_ASSOC);
$messages = array_reverse($messages); // Show oldest first at top
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameSphere Chat</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #5865F2;
            --dark: #36393f;
            --darker: #2f3136;
            --darkest: #202225;
            --light: #dcddde;
            --lighter: #ffffff;
            --success: #3ba55c;
            --danger: #ed4245;
        }
        
        body {
            margin: 0;
            font-family: 'Whitney', 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: var(--dark);
            color: var(--light);
            height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .chat-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        
        /* Sidebar */
        .sidebar {
            width: 240px;
            background-color: var(--darker);
            display: flex;
            flex-direction: column;
        }
        
        .server-header {
            padding: 16px;
            border-bottom: 1px solid var(--darkest);
            font-weight: bold;
            text-transform: uppercase;
            font-size: 12px;
            letter-spacing: 1px;
            color: #8e9297;
        }
        
        .channels {
            flex: 1;
            overflow-y: auto;
            padding: 8px 0;
        }
        
        .channel {
            padding: 8px 16px;
            margin: 0 8px;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            color: #8e9297;
        }
        
        .channel:hover {
            background-color: var(--darkest);
            color: var(--light);
        }
        
        .channel.active {
            background-color: var(--darkest);
            color: var(--light);
        }
        
        .channel i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        
        /* Main chat */
        .chat-main {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .chat-header {
            padding: 16px;
            border-bottom: 1px solid var(--darkest);
            display: flex;
            align-items: center;
        }
        
        .chat-title {
            font-weight: bold;
        }
        
        .messages-container {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background-color: var(--dark);
        }
        
        .message {
            display: flex;
            margin-bottom: 16px;
            position: relative;
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 16px;
            object-fit: cover;
        }
        
        .message-content {
            flex: 1;
        }
        
        .message-header {
            display: flex;
            align-items: baseline;
            margin-bottom: 4px;
        }
        
        .username {
            font-weight: bold;
            color: var(--lighter);
            margin-right: 8px;
        }
        
        .timestamp {
            color: #72767d;
            font-size: 0.75rem;
        }
        
        .message-text {
            line-height: 1.375;
            word-break: break-word;
        }
        
        .message-text a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .message-text a:hover {
            text-decoration: underline;
        }
        
        .media-container {
            margin-top: 8px;
            max-width: 500px;
        }
        
        .chat-media {
            max-width: 100%;
            max-height: 300px;
            border-radius: 4px;
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
        }
        
        .chat-video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        /* Input area */
        .input-container {
            padding: 16px;
            background-color: var(--darker);
        }
        
        .message-form {
            display: flex;
            background-color: var(--darkest);
            border-radius: 8px;
            padding: 8px;
        }
        
        .message-input {
            flex: 1;
            background: none;
            border: none;
            color: var(--light);
            font-size: 1rem;
            padding: 8px;
            outline: none;
            resize: none;
            max-height: 200px;
        }
        
        .media-preview {
            max-width: 100px;
            max-height: 100px;
            margin-right: 8px;
            border-radius: 4px;
            display: none;
        }
        
        .upload-btn {
            background: none;
            border: none;
            color: #b9bbbe;
            cursor: pointer;
            padding: 8px;
            font-size: 1.25rem;
        }
        
        .upload-btn:hover {
            color: var(--light);
        }
        
        /* User profile */
        .user-profile {
            padding: 16px;
            background-color: var(--darkest);
            border-top: 1px solid var(--dark);
            display: flex;
            align-items: center;
        }
        
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .user-name {
            font-weight: bold;
            font-size: 0.875rem;
        }
        
        /* Link preview */
        .link-preview {
            margin-top: 8px;
            border: 1px solid var(--darkest);
            border-radius: 4px;
            overflow: hidden;
            max-width: 500px;
        }
        
        .link-preview-image {
            width: 100%;
            max-height: 200px;
            object-fit: cover;
        }
        
        .link-preview-content {
            padding: 12px;
        }
        
        .link-preview-title {
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .link-preview-description {
            font-size: 0.875rem;
            color: #b9bbbe;
            margin-bottom: 4px;
        }
        
        .link-preview-domain {
            font-size: 0.75rem;
            color: #72767d;
        }
    </style>
</head>
<body>
    <div class="chat-container">
        <!-- Sidebar with channels -->
        <div class="sidebar">
            <div class="server-header">GameSphere</div>
            <div class="channels">
                <?php foreach ($channels as $channel): ?>
                    <div class="channel <?= $channel['name'] === $current_channel ? 'active' : '' ?>" 
                         data-channel="<?= htmlspecialchars($channel['name']) ?>">
                        <i class="fas fa-hashtag"></i>
                        <?= htmlspecialchars($channel['name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- User profile -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-profile">
                    <img src="/gamesphere/assets/avatars/<?= htmlspecialchars($_SESSION['avatar'] ?? 'default.jpg') ?>" 
                         class="user-avatar" alt="Profile">
                    <div class="user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Main chat area -->
        <div class="chat-main">
            <div class="chat-header">
                <div class="chat-title">
                    <i class="fas fa-hashtag"></i>
                    <?= htmlspecialchars($current_channel) ?>
                </div>
            </div>
            
            <div class="messages-container" id="messages-container">
                <?php foreach ($messages as $message): ?>
                    <div class="message" data-message-id="<?= $message['id'] ?>">
                        <img src="/gamesphere/assets/avatars/<?= htmlspecialchars($message['avatar']) ?>" 
                             class="avatar" alt="<?= htmlspecialchars($message['username']) ?>">
                        <div class="message-content">
                            <div class="message-header">
                                <span class="username"><?= htmlspecialchars($message['username']) ?></span>
                                <span class="timestamp">
                                    <?= date('M j, Y g:i a', strtotime($message['created_at'])) ?>
                                </span>
                            </div>
                            <div class="message-text">
                                <?= parseMessageContent(htmlspecialchars($message['content'])) ?>
                            </div>
                            <?php if ($message['media_url']): ?>
                                <div class="media-container">
                                    <?php if ($message['media_type'] === 'image'): ?>
                                        <img src="/gamesphere/assets/uploads/<?= htmlspecialchars($message['media_url']) ?>" 
                                             class="chat-media" alt="Uploaded image">
                                    <?php elseif ($message['media_type'] === 'video'): ?>
                                        <div class="video-container">
                                            <video controls class="chat-video">
                                                <source src="/gamesphere/assets/uploads/<?= htmlspecialchars($message['media_url']) ?>">
                                            </video>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Message input -->
            <div class="input-container">
                <form class="message-form" id="message-form" enctype="multipart/form-data">
                    <input type="file" id="media-upload" name="media" accept="image/*,video/*" style="display: none;">
                    <label for="media-upload" class="upload-btn">
                        <i class="fas fa-plus"></i>
                    </label>
                    <img id="media-preview" class="media-preview" src="#" alt="Preview">
                    <input type="hidden" name="channel" value="<?= htmlspecialchars($current_channel) ?>">
                    <textarea name="message" class="message-input" id="message-input" 
                              placeholder="Message #<?= htmlspecialchars($current_channel) ?>" 
                              rows="1" required></textarea>
                    <button type="submit" class="upload-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script>
    // Current channel and last message ID
    let currentChannel = '<?= $current_channel ?>';
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let isTyping = false;
    let typingTimeout;

    // Channel switching
    document.querySelectorAll('.channel').forEach(channel => {
        channel.addEventListener('click', function() {
            const channelName = this.dataset.channel;
            window.location.href = `chat.php?channel=${encodeURIComponent(channelName)}`;
        });
    });

    // Auto-scroll to bottom
    function scrollToBottom() {
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }

    // Parse message content (links, markdown, etc.)
    function parseMessageContent(content) {
        // Convert URLs to links
        content = content.replace(
            /(https?:\/\/[^\s]+)/g, 
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Simple markdown parsing (bold, italic)
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        return content;
    }

    // Media upload preview
    document.getElementById('media-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const preview = document.getElementById('media-preview');
        const reader = new FileReader();

        if (file.type.startsWith('image/')) {
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else {
            preview.style.display = 'none';
        }
    });

    // Form submission with AJAX
    document.getElementById('message-form').addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        fetch('chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                console.error(data.error);
                return;
            }
            
            // Clear input
            document.getElementById('message-input').value = '';
            document.getElementById('media-upload').value = '';
            document.getElementById('media-preview').style.display = 'none';
            
            // Add new message to view
            addMessageToView(data);
        });
    });

    // Add new message to view
    function addMessageToView(message) {
        const container = document.getElementById('messages-container');
        
        const messageDiv = document.createElement('div');
        messageDiv.className = 'message';
        messageDiv.dataset.messageId = message.id;
        
        let mediaHtml = '';
        if (message.media_url) {
            if (message.media_type === 'image') {
                mediaHtml = `
                    <div class="media-container">
                        <img src="/gamesphere/assets/uploads/${message.media_url}" 
                             class="chat-media" alt="Uploaded image">
                    </div>
                `;
            } else if (message.media_type === 'video') {
                mediaHtml = `
                    <div class="media-container">
                        <div class="video-container">
                            <video controls class="chat-video">
                                <source src="/gamesphere/assets/uploads/${message.media_url}">
                            </video>
                        </div>
                    </div>
                `;
            }
        }
        
        const date = new Date(message.created_at);
        const formattedDate = date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit'
        });
        
        messageDiv.innerHTML = `
            <img src="/gamesphere/assets/avatars/${message.avatar}" 
                 class="avatar" alt="${message.username}">
            <div class="message-content">
                <div class="message-header">
                    <span class="username">${message.username}</span>
                    <span class="timestamp">${formattedDate}</span>
                </div>
                <div class="message-text">
                    ${parseMessageContent(message.content)}
                </div>
                ${mediaHtml}
            </div>
        `;
        
        container.appendChild(messageDiv);
        scrollToBottom();
        lastMessageId = message.id;
    }

    // Check for new messages periodically
    function checkForNewMessages() {
        if (document.hidden) return;
        
        fetch(`chat.php?get_messages=1&last_id=${lastMessageId}&channel=${encodeURIComponent(currentChannel)}`)
            .then(response => response.json())
            .then(messages => {
                messages.forEach(message => {
                    addMessageToView(message);
                });
                
                if (messages.length > 0) {
                    lastMessageId = messages[messages.length - 1].id;
                }
            })
            .finally(() => {
                setTimeout(checkForNewMessages, 2000);
            });
    }

    // Start checking for new messages
    checkForNewMessages();

    // Auto-resize textarea
    document.getElementById('message-input').addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Initial scroll to bottom
    scrollToBottom();
    </script>
</body>
</html>

<?php
function parseMessageContent($text) {
    // Convert URLs to links
    $text = preg_replace(
        '/(https?:\/\/[^\s]+)/', 
        '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>', 
        $text
    );
    
    // Simple markdown parsing
    $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text);
    $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);
    
    return nl2br($text);
}
?>