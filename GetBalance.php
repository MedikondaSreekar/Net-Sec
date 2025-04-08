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

    $requiredFields = ['session_id', 'username'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception(ucfirst($field) . ' is required');
        }
    }

    if (empty($data['session_id'])) {
        throw new Exception('Session ID is required');
    }

    // Decrypt session token
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

    
    list($exp_time, $username, $email) = $parts;

    if($username !== $data['username']) {
        throw new Exception('Na na dont do that :)');
    }
    
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

    // Get balance data
    $result = pg_query_params($conn,
        'SELECT balance FROM bankbalance WHERE username = $1',
        [$username]
    );

    if (!$result) {
        throw new Exception('Database query failed: ' . pg_last_error());
    }

    if (pg_num_rows($result) === 0) {
        throw new Exception('Account not found');
    }

    $balanceData = pg_fetch_assoc($result);
    
    // Prepare response
    $response = [
        'status' => 'success',
        'data' => [
            'username' => $username,
            'balance' => (float)$balanceData['balance'],
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
