<?php
header('Content-Type: application/json');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Define the path to match
$requestPath = '/db';

// Check if the request path and method match
if ($_SERVER['REQUEST_URI'] === $requestPath && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
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
        $required = ['username', 'email', 'password'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // Sanitize inputs
        $username = pg_escape_string($data['username']);
        $email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
        $password = $data['password'];

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        if ($hashedPassword === false) {
            throw new Exception('Password hashing failed');
        }

        // Database connection
        $conn = pg_connect("host=localhost port=5432 dbname=netsecdb user=postgres password=postgres");
        if (!$conn) {
            throw new Exception('Database connection failed');
        }

        // Insert using prepared statement
        $result = pg_query_params($conn,
            'INSERT INTO users (username, email, password) VALUES ($1, $2, $3)',
            [$username, $email, $hashedPassword]
        );

        if (!$result) {
            throw new Exception('Database insertion failed: ' . pg_last_error($conn));
        }

        // Success response
        echo json_encode([
            'status' => 'success',
            'message' => 'User created successfully'
        ]);

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    } finally {
        if (isset($conn)) {
            pg_close($conn);
        }
    }
} else {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Not Found'
    ]);
}
?>
