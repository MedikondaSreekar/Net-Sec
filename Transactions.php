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

    list($exp_time, $username, $email) = $parts;

    if($exp_time < time()) {
        throw new Exception('Session expired');
    }

    if($username !== $data['username']) {
        throw new Exception('Hehe dont do that :)');
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

    // Get transactions
    $result = pg_query_params($conn,
        'SELECT * FROM transactions 
        WHERE from_user = $1 OR to_user = $1 
        ORDER BY date DESC',
        [$username]
    );

    if (!$result) {
        throw new Exception('Database query failed: ' . pg_last_error());
    }

    $transactions = [];
    while ($row = pg_fetch_assoc($result)) {
        $transactions[] = [
            'from' => $row['from_user'],
            'to' => $row['to_user'],
            'amount' => (int)$row['amount'],
            'comment' => $row['comment'] ?? '',
            'date' => $row['date'],
            'status' => $row['status']
        ];
    }

    echo json_encode([
        'status' => 'success',
        'data' => [
            'username' => $username,
            'transactions' => $transactions
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
