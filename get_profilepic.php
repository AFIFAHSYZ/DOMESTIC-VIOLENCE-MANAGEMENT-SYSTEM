<?php
require 'conn.php';

// Validate user_id
$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($user_id <= 0) {
    http_response_code(400);
    exit('Invalid user ID');
}

try {
    $stmt = $pdo->prepare("SELECT profilepic FROM SYS_USER WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && !empty($user['profilepic'])) {
        // Handle both resource and string data
        $imageData = is_resource($user['profilepic']) ? 
            stream_get_contents($user['profilepic']) : 
            $user['profilepic'];

        // Detect MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($imageData);
        
        // Caching headers (1 day cache)
        header("Content-Type: $mime");
        header('Cache-Control: public, max-age=86400');
        header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 86400) . ' GMT');
        
        echo $imageData;
    } else {
        // Default avatar with proper error handling
        $defaultAvatar = __DIR__ . '/assets/images/default-avatar.jpg';
        if (file_exists($defaultAvatar)) {
            header("Content-Type: image/jpeg");
            readfile($defaultAvatar);
        } else {
            http_response_code(404);
            exit('Profile picture not found');
        }
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    exit('Error retrieving profile picture');
}
?>