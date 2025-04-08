<?php
header('Content-Type: application/json');

// Load environment variables
require_once __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Validate required environment variables
$requiredEnv = ['SESSION_KEY', 'SESSION_IV', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
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

    // Retrieve and validate JSON data
    $jsonData = file_get_contents('php://input');
    if (empty($jsonData)) {
        throw new Exception('No data received');
    }

    $data = json_decode($jsonData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON data');
    }

    // Validate required fields
    $required = ['session_id', 'to_username', 'quantity'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Validate session ID
    if (empty($data['session_id'])) {
        throw new Exception('Session ID is required');
    }
    
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

    list($exp_time, $userId, $email) = $parts;

    if($userId=== $data['to_username']) {
        throw new Exception('Cannot transfer to self');
    }

    if($exp_time < time()) {
        throw new Exception('Session expired');
    }
    //  validate the comments saying there should not be any > or < symbols
    if (strpos($data['comments'], '>') !== false || strpos($data['comments'], '<') !== false) {
        throw new Exception('Invalid comment');
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

    // Start transaction
    pg_query($conn, "BEGIN");

    try {
        // Validate recipient exists
        $result = pg_query_params($conn,
            'SELECT 1 FROM users WHERE username = $1',
            [$data['to_username']]
        );

        if (pg_num_rows($result) === 0) {
            throw new Exception('Recipient account not found');
        }
        
        // Validate sender balance
        $result = pg_query_params($conn,
            'SELECT balance FROM bankbalance WHERE username = $1',
            [$userId]
        );

        if (pg_num_rows($result) === 0) {
            throw new Exception('Sender account not found');
        }

        $senderBalance = pg_fetch_assoc($result)['balance'];
        $quantity = (float)$data['quantity'];

        if ($senderBalance < $quantity) {
            throw new Exception('Insufficient funds');
        }

        // Perform transfer
        // Deduct from sender
        $remainingBalance = $senderBalance - $quantity;
        $result = pg_query_params($conn,
            'UPDATE bankbalance SET balance = $1 WHERE username = $2',
            [$remainingBalance, $userId]
        );

        if (!$result) {
            throw new Exception('Failed to deduct funds');
        }

        // Add to recipient
        $result = pg_query_params($conn,
            'UPDATE bankbalance SET balance = balance + $1 WHERE username = $2',
            [$quantity, $data['to_username']]
        );

        if (!$result) {
            throw new Exception('Failed to add funds');
        }

        // Commit transaction
        pg_query($conn, "COMMIT");

        // Log transaction
        $logEntry = sprintf(
            "[SUCCESSFUL TRANSACTION] | [%s] | FROM - %s | TO - %s | AMOUNT - %.2f | COMMENT - %s\n",
            date('Y-m-d H:i:s'),
            $userId,
            $data['to_username'],
            $quantity,
            substr(trim($data['comments']), 0, 200)
        );

        // add to the table of transactions
        $result = pg_query_params($conn,
            'INSERT INTO transactions (from_user, to_user, amount, comment, date, status) VALUES ($1, $2, $3, $4, $5, $6)',
            [$userId, $data['to_username'], $quantity, $data['comments'], date('Y-m-d H:i:s'), 'SUCCESS']
        );

        $logFile = __DIR__ . '/../logs/moneyTransfer.log';
        if (!file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX)) {
            error_log('Failed to write to transaction log');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Transfer completed successfully',
            'new_balance' => $senderBalance - $quantity
        ]);

    } catch (Exception $e) {
        pg_query($conn, "ROLLBACK");
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(400);
    $message = $e->getMessage();
    $logEntry = sprintf(
        "[FAILED TRANSACTION] - %s | %s | FROM - %s | TO - %s | AMOUNT - %.2f | COMMENT - %s\n",
        $message,
        date('Y-m-d H:i:s'),
        $userId,
        $data['to_username'],
        $data['quantity'],
        substr(trim($data['comments']), 0, 200)
    );
    $conn = pg_connect(
        "host=" . $_ENV['DB_HOST'] . 
        " port=" . $_ENV['DB_PORT'] . 
        " dbname=" . $_ENV['DB_NAME'] . 
        " user=" . $_ENV['DB_USER'] . 
        " password=" . $_ENV['DB_PASSWORD']
    );  
    // add to the table of transactions
    $result = pg_query_params($conn,
        'INSERT INTO transactions (from_user, to_user, amount, comment, date, status) VALUES ($1, $2, $3, $4, $5, $6)',
        [$userId, $data['to_username'], $data['quantity'], $data['comments'], date('Y-m-d H:i:s'), 'FAILED']
    );
    

    $logFile = __DIR__ . '/../logs/moneyTransfer.log';
    if (!file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX)) {
        error_log('Failed to write to transaction log');
    }
    echo json_encode([
        'status' => 'error',
        'message' => $message
    ]);
} 



?>




