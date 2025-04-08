<?php
header('Content-Type: application/json');

require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

// Validate required environment variables
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

    // Get and validate input
    $jsonData = file_get_contents('php://input');
    if (empty($jsonData)) {
        throw new Exception('No data received');
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required = ['session_id', 'username'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate and decrypt session
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

    list($exp_time, $requestingUser, $email) = $parts;

    if($exp_time < time()) {
        throw new Exception('Session expired');
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

    // Validate target username exists
    $targetUsername = pg_escape_string($conn, $data['username']);
    $result = pg_query_params($conn,
        'SELECT u.email, p.biography, p.image 
         FROM users u
         LEFT JOIN profiles p ON u.username = p.username
         WHERE u.username = $1',
        [$targetUsername]
    );

    if (!$result) {
        throw new Exception('Database query failed: ' . pg_last_error());
    }

    if (pg_num_rows($result) === 0) {
        throw new Exception('Requested user not found');
    }

    $profile = pg_fetch_assoc($result);

    // Process image data (stored as TEXT)
    $imageData = $profile['image'] ?? '';
    if (!empty($imageData)) {
        // Add proper Base64 padding if missing
        if (strlen($imageData) % 4 !== 0) {
            $imageData = str_pad($imageData, strlen($imageData) + (4 - strlen($imageData) % 4), '=', STR_PAD_RIGHT);
        }
    }
    
    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'username' => $targetUsername,
            'email' => $profile['email'],
            'biography' => $profile['biography'] ?? '',
            'profile_image' => 'data:image/jpeg;base64,' . $profile['image']
        ]
    ];

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
