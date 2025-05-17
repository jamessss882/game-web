<?php
// Start session at the very beginning
session_start();

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set default session values if not set
$_SESSION['username'] = $_SESSION['username'] ?? 'Guest';
$_SESSION['avatar'] = $_SESSION['avatar'] ?? 'default.jpg';
$_SESSION['user_id'] = $_SESSION['user_id'] ?? null;

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Database connection - Use correct credentials
$conn = new mysqli('localhost', 'root', '', 'gamesphere_db');

// Check connection with better error handling
if ($conn->connect_error) {
    die("Connection failed. Please follow these steps:<br>
         1. Open phpMyAdmin (http://localhost/phpmyadmin)<br>
         2. Create a database named 'gamesphere'<br>
         3. Or edit this file to use an existing database<br>
         Error: " . $conn->connect_error);
}

// Verify tables exist (basic check)
$result = $conn->query("SHOW TABLES LIKE 'chat_messages'");
if ($result->num_rows == 0) {
    die("Required tables are missing. Please import the database structure.");
}

// Get current channel with validation
$current_channel = isset($_GET['channel']) ? $conn->real_escape_string($_GET['channel']) : 'general-chat';
if (!preg_match('/^[a-z0-9\-_]+$/i', $current_channel)) {
    $current_channel = 'general-chat';
}

// Handle new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['message'])) {
    header('Content-Type: application/json');
    
    // Verify user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in', 'redirect' => 'login.php']);
        exit;
    }

    // Validate and sanitize input
    $message = trim($conn->real_escape_string($_POST['message']));
    if (empty($message) && empty($_FILES['media']['name'])) {
        echo json_encode(['error' => 'Message or media is required']);
        exit;
    }

    $userId = (int)$_SESSION['user_id'];
    $channel = $conn->real_escape_string($_POST['channel']);
    $mediaUrl = null;
    $mediaType = 'none';

    // Handle file upload
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'D:/xampp/htdocs/gamesphere/assets/uploads/';
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                echo json_encode(['error' => 'Failed to create upload directory']);
                exit;
            }
        }
        
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
            $targetPath = $uploadDir . $newFilename;
            
            // Additional security checks
            $allowedMimeTypes = [
                'image/jpeg', 'image/png', 'image/gif',
                'video/mp4', 'video/webm'
            ];
            
            $fileMimeType = mime_content_type($_FILES['media']['tmp_name']);
            
            if (!in_array($fileMimeType, $allowedMimeTypes)) {
                echo json_encode(['error' => 'Invalid file type']);
                exit;
            }
            
            // Check file size (max 5MB)
            if ($_FILES['media']['size'] > 5242880) {
                echo json_encode(['error' => 'File too large (max 5MB)']);
                exit;
            }
            
            if (move_uploaded_file($_FILES['media']['tmp_name'], $targetPath)) {
                $mediaUrl = $newFilename;
            } else {
                echo json_encode(['error' => 'Failed to move uploaded file']);
                exit;
            }
        } else {
            echo json_encode(['error' => 'Unsupported file type']);
            exit;
        }
    }

    // Insert message with prepared statement
    $query = "INSERT INTO chat_messages (user_id, channel, content, media_url, media_type) 
             VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("issss", $userId, $channel, $message, $mediaUrl, $mediaType);
    
    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    
    $insertId = $stmt->insert_id;
    $stmt->close();

    // Return new message data
    $newMsgQuery = "SELECT m.*, u.username, u.avatar 
                   FROM chat_messages m
                   JOIN users u ON m.user_id = u.id
                   WHERE m.id = ?";
    
    $stmt = $conn->prepare($newMsgQuery);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("i", $insertId);
    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    $newMsg = $result->fetch_assoc();
    $stmt->close();
    
    echo json_encode($newMsg ?: ['error' => 'Failed to retrieve new message']);
    exit;
}

// AJAX request for new messages
if (isset($_GET['get_messages'])) {
    header('Content-Type: application/json');
    
    $lastId = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $channel = $conn->real_escape_string($_GET['channel']);
    
    $messagesQuery = "SELECT m.*, u.username, u.avatar 
                    FROM chat_messages m
                    JOIN users u ON m.user_id = u.id
                    WHERE m.channel = ? AND m.id > ?
                    ORDER BY m.created_at ASC";
    
    $stmt = $conn->prepare($messagesQuery);
    if (!$stmt) {
        echo json_encode(['error' => 'Prepare failed: ' . $conn->error]);
        exit;
    }
    
    $stmt->bind_param("si", $channel, $lastId);
    if (!$stmt->execute()) {
        echo json_encode(['error' => 'Execute failed: ' . $stmt->error]);
        exit;
    }
    
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();
    
    echo json_encode($messages);
    exit;
}

// Get channels
$channelsQuery = "SELECT * FROM chat_channels ORDER BY name";
$channelsResult = $conn->query($channelsQuery);

if (!$channelsResult) {
    $channels = [];
    error_log("Channel query failed: " . $conn->error);
} else {
    $channels = $channelsResult->fetch_all(MYSQLI_ASSOC);
}

// Get initial messages with prepared statement
$messagesQuery = "SELECT m.*, u.username, u.avatar 
                 FROM chat_messages m
                 JOIN users u ON m.user_id = u.id
                 WHERE m.channel = ?
                 ORDER BY m.created_at DESC LIMIT 50";

$stmt = $conn->prepare($messagesQuery);
if ($stmt) {
    $stmt->bind_param("s", $current_channel);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = $result->fetch_all(MYSQLI_ASSOC);
    $messages = array_reverse($messages); // Show oldest first at top
    $stmt->close();
} else {
    $messages = [];
    error_log("Messages query failed: " . $conn->error);
}

function parseMessageContent($text) {
    if ($text === null) {
        return '';
    }
    
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
        
        /* Top Navigation Bar Styles */
        .top-nav {
            background-color: var(--darkest);
            padding: 10px 20px;
            display: flex;
            justify-content: space-around;
            border-bottom: 1px solid var(--dark);
        }
        
        .top-nav a {
            color: var(--light);
            text-decoration: none;
            font-weight: bold;
            padding: 8px 16px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }
        
        .top-nav a:hover {
            background-color: var(--primary);
            color: white;
        }
        
        .top-nav a i {
            margin-right: 8px;
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
            transition: all 0.2s ease;
        }
        
        .channel:hover {
            background-color: var(--darkest);
            color: var(--light);
        }
        
        .channel.active {
            background-color: var(--primary);
            color: white;
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
            background-color: var(--dark);
        }
        
        .chat-header {
            padding: 16px;
            border-bottom: 1px solid var(--darkest);
            display: flex;
            align-items: center;
            background-color: var(--darker);
        }
        
        .chat-title {
            font-weight: bold;
            color: var(--light);
        }
        
        .messages-container {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            background-color: var(--dark);
            background-image: url('/gamesphere/assets/chat-bg.png');
            background-size: cover;
            background-attachment: fixed;
            background-position: center;
        }
        
        .message {
            display: flex;
            margin-bottom: 16px;
            position: relative;
            padding: 8px;
            border-radius: 8px;
            background-color: rgba(54, 57, 63, 0.8);
            transition: all 0.2s ease;
        }
        
        .message:hover {
            background-color: rgba(47, 49, 54, 0.9);
        }
        
        .avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 16px;
            object-fit: cover;
            border: 2px solid var(--primary);
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
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .video-container {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 */
            height: 0;
            overflow: hidden;
            background-color: #000;
            border-radius: 4px;
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
            border-top: 1px solid var(--darkest);
        }
        
        .message-form {
            display: flex;
            background-color: var(--darkest);
            border-radius: 8px;
            padding: 8px;
            align-items: center;
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
            min-height: 40px;
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
            transition: all 0.2s ease;
        }
        
        .upload-btn:hover {
            color: var(--primary);
            transform: scale(1.1);
        }
        
        .send-btn {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 16px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .send-btn:hover {
            background-color: #4752c4;
        }
        
        .send-btn:disabled {
            background-color: #6a75d1;
            cursor: not-allowed;
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
            border: 2px solid var(--primary);
        }
        
        .user-name {
            font-weight: bold;
            font-size: 0.875rem;
            color: var(--light);
        }
        
        /* Emoji picker */
        .emoji-picker {
            display: flex;
            gap: 5px;
            margin: 10px 0;
            flex-wrap: wrap;
            background-color: var(--darkest);
            padding: 8px;
            border-radius: 8px;
        }
        
        .emoji-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            transition: all 0.2s ease;
        }
        
        .emoji-btn:hover {
            transform: scale(1.2);
        }
        
        /* Typing indicator */
        .typing-indicator {
            color: #72767d;
            font-size: 0.875rem;
            padding: 0 16px;
            margin-bottom: 8px;
            display: none;
        }
        
        /* Loading spinner */
        .fa-spinner {
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Error message */
        .error-message {
            color: var(--danger);
            padding: 8px;
            margin: 8px 0;
            background-color: rgba(237, 66, 69, 0.1);
            border-radius: 4px;
            display: none;
        }
    </style>
</head>
<body>
    <!-- Top Navigation Bar -->
    <div class="top-nav">
        <a href="/gamesphere/index.php"><i class="fas fa-home"></i> Home</a>
        <a href="/gamesphere/chat.php"><i class="fas fa-comments"></i> Chat</a>
        <a href="/gamesphere/leaderboard.php"><i class="fas fa-trophy"></i> Leaderboard</a>
        <a href="/gamesphere/games.php"><i class="fas fa-gamepad"></i> Games</a>
    </div>

    <div class="chat-container">
        <!-- Sidebar with channels -->
        <div class="sidebar">
            <div class="server-header">GameSphere Channels</div>
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
                    <div class="user-name"><?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></div>
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
                        <img src="/gamesphere/assets/avatars/<?= htmlspecialchars($message['avatar'] ?? 'default.jpg') ?>" 
                             class="avatar" alt="<?= htmlspecialchars($message['username']) ?>">
                        <div class="message-content">
                            <div class="message-header">
                                <span class="username"><?= htmlspecialchars($message['username']) ?></span>
                                <span class="timestamp">
                                    <?= date('M j, Y g:i a', strtotime($message['created_at'])) ?>
                                </span>
                            </div>
                            <div class="message-text">
                                <?= parseMessageContent($message['content']) ?>
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
            
            <!-- Error message display -->
            <div class="error-message" id="error-message"></div>
            
            <!-- Typing indicator -->
            <div class="typing-indicator" id="typing-indicator"></div>
            
            <!-- Message input -->
            <div class="input-container">
                <!-- Emoji picker -->
                <div class="emoji-picker" id="emoji-picker">
                    <button type="button" class="emoji-btn" onclick="insertEmoji('😀')">😀</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('😂')">😂</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('❤️')">❤️</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('👍')">👍</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('🙏')">🙏</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('🔥')">🔥</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('🎉')">🎉</button>
                    <button type="button" class="emoji-btn" onclick="insertEmoji('🤔')">🤔</button>
                </div>
                
                <form class="message-form" id="message-form" enctype="multipart/form-data">
                    <input type="file" id="media-upload" name="media" accept="image/*,video/*" style="display: none;">
                    <label for="media-upload" class="upload-btn" title="Upload media">
                        <i class="fas fa-image"></i>
                    </label>
                    <img id="media-preview" class="media-preview" src="#" alt="Preview">
                    <input type="hidden" name="channel" id="channel-input" value="<?= htmlspecialchars($current_channel) ?>">
                    <textarea name="message" class="message-input" id="message-input" 
                              placeholder="Message #<?= htmlspecialchars($current_channel) ?>" 
                              rows="1" required></textarea>
                    <button type="submit" class="send-btn" id="send-btn">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
    // Current channel and last message ID
    let currentChannel = '<?= $current_channel ?>';
    let lastMessageId = <?= !empty($messages) ? end($messages)['id'] : 0 ?>;
    let isTyping = false;
    let typingTimeout;
    let typingUsers = {};

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
        // Use setTimeout to ensure DOM has updated before scrolling
        setTimeout(() => {
            container.scrollTop = container.scrollHeight;
        }, 10);
    }

    // Display error message
    function showError(message) {
        const errorDiv = document.getElementById('error-message');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    // Parse message content (links, markdown, etc.)
    function parseMessageContent(content) {
        if (!content) return '';
        
        // Convert URLs to links
        content = content.replace(
            /(https?:\/\/[^\s]+)/g, 
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Simple markdown parsing (bold, italic)
        content = content.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        content = content.replace(/\*(.*?)\*/g, '<em>$1</em>');
        
        // Convert newlines to <br> tags
        content = content.replace(/\n/g, '<br>');
        
        return content;
    }

    // Media upload preview
    document.getElementById('media-upload').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;

        const preview = document.getElementById('media-preview');
        const reader = new FileReader();

        // Check file size (max 5MB)
        if (file.size > 5242880) {
            showError('File too large (max 5MB)');
            this.value = '';
            return;
        }

        if (file.type.startsWith('image/')) {
            reader.onload = function(e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            }
            reader.readAsDataURL(file);
        } else if (file.type.startsWith('video/')) {
            preview.style.display = 'none';
        } else {
            showError('Unsupported file type');
            this.value = '';
        }
    });

    // Form submission with AJAX
    document.getElementById('message-form').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const messageInput = document.getElementById('message-input');
        const mediaInput = document.getElementById('media-upload');
        
        // Check if both message and media are empty
        if (!messageInput.value.trim() && !mediaInput.files[0]) {
            showError('Please enter a message or attach a file');
            return;
        }
        
        const formData = new FormData(this);
        const sendBtn = document.getElementById('send-btn');
        
        // Update channel value to ensure it matches current channel
        document.getElementById('channel-input').value = currentChannel;
        
        // Disable send button to prevent double-sending
        sendBtn.disabled = true;
        sendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        fetch('chat.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Network response was not ok');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.error) {
                throw new Error(data.error);
            }
            
            // Clear input
            messageInput.value = '';
            mediaInput.value = '';
            document.getElementById('media-preview').style.display = 'none';
            
            // Add new message to view immediately
            addMessageToView(data);
            
            // Update last message ID
            lastMessageId = data.id;
            
            // Reset textarea height
            messageInput.style.height = 'auto';
        })
        .catch(error => {
            console.error('Error sending message:', error);
            
            if (error.message === 'Not logged in') {
                window.location.href = 'login.php';
            } else {
                showError(error.message || 'Failed to send message. Please try again.');
            }
        })
        .finally(() => {
            // Re-enable send button
            sendBtn.disabled = false;
            sendBtn.innerHTML = '<i class="fas fa-paper-plane"></i>';
        });
    });

    // Add new message to view
    function addMessageToView(message) {
        // Safety check to prevent adding duplicate messages
        if (document.querySelector(`.message[data-message-id="${message.id}"]`)) {
            return;
        }
        
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
        
        // Handle date formatting with fallback
        let formattedDate;
        try {
            const date = new Date(message.created_at);
            formattedDate = date.toLocaleString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (e) {
            formattedDate = 'Just now';
        }
        
        const content = message.content ? parseMessageContent(message.content) : '';
        
        messageDiv.innerHTML = `
            <img src="/gamesphere/assets/avatars/${message.avatar || 'default.jpg'}" 
                 class="avatar" alt="${message.username}">
            <div class="message-content">
                <div class="message-header">
                    <span class="username">${message.username}</span>
                    <span class="timestamp">${formattedDate}</span>
                </div>
                <div class="message-text">
                    ${content}
                </div>
                ${mediaHtml}
            </div>
        `;
        
        container.appendChild(messageDiv);
        scrollToBottom();
        
        // Update last message ID if this message is newer
        if (!lastMessageId || parseInt(message.id) > lastMessageId) {
            lastMessageId = parseInt(message.id);
        }
    }

    // Check for new messages periodically
    function checkForNewMessages() {
        fetch(`chat.php?get_messages=1&last_id=${lastMessageId}&channel=${encodeURIComponent(currentChannel)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(messages => {
                if (messages && messages.length > 0) {
                    messages.forEach(message => {
                        // Check if message already exists to avoid duplicates
                        if (!document.querySelector(`.message[data-message-id="${message.id}"]`)) {
                            addMessageToView(message);
                        }
                    });
                    
                    lastMessageId = messages[messages.length - 1].id;
                }
            })
            .catch(error => {
                console.error('Error checking for new messages:', error);
            })
            .finally(() => {
                setTimeout(checkForNewMessages, 2000);
            });
    }

    // Emoji insertion
    function insertEmoji(emoji) {
        const textarea = document.getElementById('message-input');
        const startPos = textarea.selectionStart;
        const endPos = textarea.selectionEnd;
        
        textarea.value = textarea.value.substring(0, startPos) + 
                         emoji + 
                         textarea.value.substring(endPos);
        
        // Set cursor position after inserted emoji
        textarea.selectionStart = textarea.selectionEnd = startPos + emoji.length;
        textarea.focus();
    }

    // Typing indicator
    function updateTypingIndicator() {
        const indicator = document.getElementById('typing-indicator');
        const users = Object.values(typingUsers);
        
        if (users.length === 0) {
            indicator.style.display = 'none';
            return;
        }
        
        let text = '';
        if (users.length === 1) {
            text = `${users[0]} is typing...`;
        } else if (users.length === 2) {
            text = `${users[0]} and ${users[1]} are typing...`;
        } else {
            text = `${users[0]}, ${users[1]}, and others are typing...`;
        }
        
        indicator.textContent = text;
        indicator.style.display = 'block';
    }

    // Handle typing events
    document.getElementById('message-input').addEventListener('input', function() {
        if (!isTyping) {
            isTyping = true;
            // In a real app, you would send a "user is typing" notification to the server
        }
        
        clearTimeout(typingTimeout);
        typingTimeout = setTimeout(() => {
            isTyping = false;
            // In a real app, you would send a "user stopped typing" notification
        }, 2000);
        
        // Auto-resize textarea
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    // Start checking for new messages
    checkForNewMessages();

    // Initial scroll to bottom
    scrollToBottom();
    </script>
</body>
</html>