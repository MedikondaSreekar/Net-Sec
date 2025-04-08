<?php
header('Content-Type: application/json');

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();


// Validate required environment variables
$requiredEnv = ['SESSION_KEY', 'SESSION_EXPIRATION', 'SESSION_IV', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD'];
foreach ($requiredEnv as $envKey) {
    if (empty($_ENV[$envKey])) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Server configuration error', 'missing' => $envKey]);
        exit;
    }
}


function validatePasswordLogin($data) {
    // Validate required fields
    $required = ['username', 'password'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception('Missing required field: $field');
        }
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

    // Sanitize inputs
    $username = $username = pg_escape_string($conn, $data['username']);
    $password = $data['password'];

    try {
        // Check if user exists
        $result = pg_query_params($conn, 
            'SELECT * FROM users WHERE username = $1', 
            [$username]
        );
        
        if (pg_num_rows($result) === 0) {
            throw new Exception('Invalid username');
        }

        $user = pg_fetch_assoc($result);
        
        if (!password_verify($password, $user['password'])) {
            throw new Exception('Invalid password');
        }

        // Generate encrypted session token
        $exp_time = time() + $_ENV['SESSION_EXPIRATION'];
        $sessionData = $exp_time . '|' . $user['username'] . '|' . $user['email'];
        $iv = base64_decode($_ENV['SESSION_IV']);
        if($iv === false) {
            throw new Exception('Invalid IV');
        }
        $sessionId = openssl_encrypt(
            $sessionData,
            'AES-256-CBC',
            $_ENV['SESSION_KEY'],
            0,
            $iv
        );

        setcookie(
            'session_id', 
            $sessionId,
            [
                'expires' => $exp_time,
                'path' => '/', // Accessible across all paths
                'domain' => 'localhost', // Use 'localhost' for local development
                'secure' => false, // Set to false for HTTP
                'httponly' => true,
            ]
        );       

        return [
            'status' => 'success',
            'message' => 'Login successful',
            'session_id' => $sessionId,
            'expires_in' => $exp_time
        ];

    } finally {
        pg_close($conn);
    }
}

function validateSessionLogin($data) {
    $required = ['session_id', 'username'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Decrypt session token
    $sessionId = $data['session_id'];
    $iv = base64_decode($_ENV['SESSION_IV']);
    if($iv === false) {
        throw new Exception('Invalid IV');
    }
    $decrypted = openssl_decrypt(
        $sessionId,
        'AES-256-CBC',
        $_ENV['SESSION_KEY'],
        0,
        $iv
    );

    if ($decrypted === false) {
        return [
            'status' => 'error',
            'message' => 'Invalid session ID'
        ];
    }
    
    $parts = explode('|', $decrypted);
    if (count($parts) !== 3) {
        return [
            'status' => 'error',
            'message' => 'Invalid session ID'
        ];
    }
    $sessionData = [
        'expires' => $parts[0],
        'username' => $parts[1],
        'email' => $parts[2]
    ];

    // Check expiration
    if (time() > $sessionData['expires']) {
        throw new Exception("Session expired and time() is ".time()." and sessionData['expires'] is ".$sessionData['expires']);
    }

    if ($sessionData['username'] !== $data['username']) {
        // return "Session-user mismatch";
        throw new Exception('Session-user mismatch');
    }
    
    // Renew session expiration and send new cookie
    $exp_time = time() + $_ENV['SESSION_EXPIRATION'];
    $sessionData['expires'] = $exp_time;
    $sessionId = openssl_encrypt(
        $exp_time . '|' . $sessionData['username'] . '|' . $sessionData['email'],
        'AES-256-CBC',
        $_ENV['SESSION_KEY'],
        0,
        $iv
    );

    setcookie(
        'session_id',
        $sessionId,
        [
            'expires' => $exp_time,
            'path' => '/',
            'domain' => 'localhost',
            'secure' => true,
            'httponly' => true,
        ]
    );    

    return [
        'status' => 'success',
        'message' => 'Session renewed',
        'session_id' => $sessionId,
        'expires_in' => $exp_time
    ];
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

    if (!empty($data['session_id'])) {
        $response = validateSessionLogin($data);
    } else {
        $response = validatePasswordLogin($data);
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(401);
    $message = $e->getMessage();
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();  // Stop execution here to check for errors
}



?>
