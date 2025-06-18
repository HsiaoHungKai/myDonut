<?php
// Load environment variables from .env file
function loadEnv($path) {
    if (!file_exists($path)) {
        throw new Exception(".env file not found at: $path");
    }
    
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos(trim($line), '//') === 0) {
            continue; // Skip comments
        }
        
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
    }
}

// Load the .env file
loadEnv('/Users/Repositories/myDonut/.env');

// Get database configuration from environment variables
$serverName = $_ENV['DB_HOST'] ?? "localhost";
$database = $_ENV['DB_NAME'] ?? "Donut";
$username = $_ENV['DB_USER'] ?? "sa";
$password = $_ENV['DB_PASSWORD'] ?? "Till3005";
$trustServerCertificate = filter_var($_ENV['DB_TRUST_SERVER_CERTIFICATE'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Connection options with timeout and encryption settings
$connectionOptions = array(
    "Database" => $database,
    "LoginTimeout" => 30, // Increased timeout
    "Encrypt" => true,
    "TrustServerCertificate" => $trustServerCertificate
);

try {
    // Use the sqlsrv PDO driver with proper options for Azure
    $conn = new PDO("sqlsrv:Server=$serverName;Database=$database", $username, $password, $connectionOptions);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Success message - only shown during development
    // comment this out in production
    // echo "Connected successfully";
} catch (PDOException $e) {
    // Log error to file instead of displaying it to users
    error_log("Database connection error: " . $e->getMessage(), 3, "/Users/Repositories/myDonut/logs/error.log");
    
    // For production, show a generic message
    // echo "A database error occurred. Please try again later.";
}
?>