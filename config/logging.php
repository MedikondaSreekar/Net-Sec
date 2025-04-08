<?php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Create a log channel
$log = new Logger('app');

// Log to a file
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/app.log', Logger::INFO));

// Log to the console (for development)
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

return $log;
?>
