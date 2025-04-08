<?php

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__.'/..');
$dotenv->load();

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        // validate username to only have alpabets, numbers and underscores
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            throw new Exception('Invalid username format');
        }

        // Sanitize inputs
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
        if (pg_connection_status($conn) === PGSQL_CONNECTION_BAD) {
            throw new Exception("PostgreSQL connection is in a bad state: " . pg_last_error($conn));
        }
        pg_query($conn, "BEGIN");
        if (!$conn) {
            echo json_encode([
                'status' => 'failed',
                'message' => 'Hulabaloo'
            ]);
            throw new Exception('Transaction failed');
        }

        $username = strtolower(pg_escape_string($conn, $data['username']));

        // Check unique username
        $result = pg_query_params($conn,
            'SELECT * FROM users WHERE username = $1',
            [$username]
        );
        if (!$result) {
            echo json_encode([
                'status' => 'failed',
                'message' => 'DB connection failed'
            ]);
            throw new Exception('Username uniqueness check failed: ' . pg_last_error($conn));
        }
        if (pg_num_rows($result) > 0) {
            echo json_encode([
                'status' => 'failed',
                'message' => 'Username already exists'
            ]);
            throw new Exception('Username already exists');
        }


        
        // Start transaction
        
        try {
            // Insert user
            $result = pg_query_params($conn,
                'INSERT INTO users (username, email, password) VALUES ($1, $2, $3)',
                [$username, $email, $hashedPassword]
            );
            if (!$result) {
                throw new Exception('User insertion failed: ' . pg_last_error($conn));
            }

            // Credit $100 to the user
            $result = pg_query_params($conn,
                'INSERT INTO bankbalance (username, balance) VALUES ($1, $2)',
                [$username, 100]
            );
            if (!$result) {
                throw new Exception('Credit insertion failed: ' . pg_last_error($conn));
            }

            // add user to the profile with an empty bio and an empty image(TEXT)
            $result = pg_query_params($conn,
                'INSERT INTO profiles (username, biography, image) VALUES ($1, $2, $3)',
                [$username, '', '']
            );
            if (!$result) {
                throw new Exception('Profile insertion failed: ' . pg_last_error($conn));
            }

            // Commit transaction
            pg_query($conn, "COMMIT");

            // Send success response
            echo json_encode([
                'status' => 'success',
                'message' => 'User registered and credited successfully'
            ]);

        } catch (Exception $e) {
            // Rollback transaction on error
            pg_query($conn, "ROLLBACK");

            // Send error response
            http_response_code(400);
            echo json_encode([
                'status' => 'caught error',
                'message' => $e->getMessage()
            ]);
        }

    } catch (Exception $e) {
        // Send error response
        http_response_code(400);
    } 
} else {
    // Send error response for invalid method
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method Not Allowed'
    ]);
}




?>



