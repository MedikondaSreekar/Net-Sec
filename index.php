<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Define routes
$routes = [
    '/api/uniqueusername' => 'app/UniqueUsername.php',
    '/api/register' => 'app/Register.php',
    '/api/login'    => 'app/Login.php',
    '/api/logout'   => 'app/Logout.php',
    '/api/moneytransfer'   => 'app/MoneyTransfer.php',
    '/api/profile'   => 'app/Profile.php',
    '/api/otherprofile'   => 'app/OtherProfile.php',
    '/api/getbalance'   => 'app/GetBalance.php',
    '/api/editprofile'   => 'app/EditProfile.php',
    '/api/search'   => 'app/UserSearch.php',
    '/api/transaction'   => 'app/Transactions.php',
];

// Get the request path
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Route the request
if (array_key_exists($requestPath, $routes)) {
    require __DIR__ . '/' . $routes[$requestPath];
} else {
    http_response_code(404);
    echo '[STATUS 404] NOT FOUND';
    echo json_encode(['status' => 'error', 'message' => 'Not Found']);
}



?>





