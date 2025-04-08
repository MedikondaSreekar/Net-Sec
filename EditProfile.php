<?php
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

$requiredEnv = ['SESSION_KEY', 'SESSION_EXPIRATION', 'SESSION_IV', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($requiredEnv as $env) {
    if (!isset($_ENV[$env])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error']);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
        exit;
    }

    $jsonData = file_get_contents('php://input');
    if (empty($jsonData)) {
        throw new Exception('No data received');
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required = ['session_id', 'username', 'email']; // biography, profile_image is optional
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Session validation
    $iv = base64_decode($_ENV['SESSION_IV']);
    if($iv === false) {
        throw new Exception('Invalid IV');
    }
    
    $decrypted = openssl_decrypt(
        $data['session_id'],
        'AES-256-CBC',
        $_ENV['SESSION_KEY'],
        0,
        $iv
    );

    if (!$decrypted) {
        throw new Exception('Invalid session ID');
    }

    $parts = explode('|', $decrypted);
    if (count($parts) !== 3) {
        throw new Exception('Invalid session format');
    }

    list($exp_time, $userId, $sessionEmail) = $parts;

    if($exp_time < time()) {
        throw new Exception('Session expired');
    }

    if($userId !== $data['username']) {
        throw new Exception('he he you cant do that :)');
    }

    // Initialize updates
    $profileUpdates = [];
    $userUpdates = [];
    $params = [];
    $profileParams = [];
    $userParams = [];

    // Biography validation
    if (isset($data['biography'])) {
        $bio = trim($data['biography']);
        if (strpos($bio, '<') !== false || strpos($bio, '>') !== false) {
            throw new Exception('Give valid Biography');
        }
        if (strlen($bio) > 500) {
            throw new Exception('Biography must be 500 characters or less');
        }
        $profileUpdates[] = "biography = $" . (count($profileParams) + 1);
        $profileParams[] = $bio;
    }

    // Email validation
    if (isset($data['email'])) {
        $newEmail = trim($data['email']);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }
        if (strlen($newEmail) > 255) {
            throw new Exception('Email too long');
        }
        $userUpdates[] = "email = $" . (count($userParams) + 1);
        $userParams[] = $newEmail;
    }

    // Profile image validation

    if ($data['profile_image'] !== "" && isset($data['profile_image'])) {
        // Remove Base64 metadata (e.g., "data:image/png;base64,")
        $base64String = $data['profile_image'];
        if (preg_match('/^data:image\/(png|jpeg|gif);base64,/', $base64String, $matches)) {
            $base64String = preg_replace('/^data:image\/(png|jpeg|gif);base64,/', '', $base64String);
        } else {
            throw new Exception('Invalid image format');
        }
    
        // Decode Base64 data
        $imageData = base64_decode($base64String, true);
        if ($imageData === false) {
            throw new Exception('Invalid image data');
        }
    
        // Verify MIME type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($imageData);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        
        if (!in_array($mime, $allowedTypes)) {
            throw new Exception('Invalid image type. Allowed: JPEG, PNG, GIF');
        }
    
        // Check image size (max 2MB)
        if (strlen($imageData) > 2097152) {
            throw new Exception('Image too large (max 2MB)');
        }
    
        // Store the image in Base64 (optional: consider saving as a file)
        $profileUpdates[] = "image = $" . (count($profileParams) + 1);
        $profileParams[] = base64_encode($imageData);
    }
    

    if (empty($profileUpdates) && empty($userUpdates)) {
        throw new Exception('No fields to update');
    }

    // Database connection
    $conn = pg_connect(
        "host=" . $_ENV['DB_HOST'] . 
        " port=" . $_ENV['DB_PORT'] . 
        " dbname=" . $_ENV['DB_NAME'] . 
        " user=" . $_ENV['DB_USER'] . 
        " password=" . $_ENV['DB_PASSWORD']
    );
    
    if (!$conn) {
        throw new Exception('Database connection failed: ' . pg_last_error());
    }

    // Update profiles table if needed
    if (!empty($profileUpdates)) {
        $profileQuery = "UPDATE profiles SET " . 
            implode(', ', $profileUpdates) . 
            " WHERE username = $" . (count($profileParams) + 1);

        // Replace ? with $ placeholders
        $profileQuery = preg_replace('/\?/', '$', $profileQuery);
        $profileParams[] = $userId;
        
        $result = pg_query_params($conn, $profileQuery, $profileParams);
        if (!$result) {
            throw new Exception('Profile update failed: ' . pg_last_error());
        }
    }

    // Update users table if needed
    if (!empty($userUpdates)) {
        $userQuery = "UPDATE users SET " . 
            implode(', ', $userUpdates) . 
            " WHERE username = $" . (count($userParams) + 1);
        $userParams[] = $userId;
        
        $result = pg_query_params($conn, $userQuery, $userParams);
        if (!$result) {
            throw new Exception('Email update failed: ' . pg_last_error());
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Profile updated successfully',
        'data' => [
            'username' => $userId,
            'email' => $data['email'],
            'biography' => $data['biography'],
            'profile_image' => $data['profile_image']
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
