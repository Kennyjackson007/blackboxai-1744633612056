<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'photo_gallery';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create users table if it doesn't exist
$create_users = "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

// Create images table if it doesn't exist
$create_images = "CREATE TABLE IF NOT EXISTS images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    description TEXT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
)";

$conn->query($create_users);
$conn->query($create_images);

// Directory where images will be stored
$upload_dir = 'uploads/';

// Create upload directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Process file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file-upload'])) {
    $file = $_FILES['file-upload'];
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['error' => 'File upload error']);
        exit;
    }

    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowed_types)) {
        http_response_code(400);
        echo json_encode(['error' => 'Only JPG, PNG, and GIF files are allowed']);
        exit;
    }

    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $destination = $upload_dir . $filename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        // Insert into database
        session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Not authenticated']);
            exit;
        }
        
        $stmt = $conn->prepare("INSERT INTO images (user_id, filename, description) VALUES (?, ?, ?)");
        $user_id = $_SESSION['user_id'];
        $stmt->bind_param("iss", $user_id, $filename, $description);
        $description = $_POST['image-caption'] ?? 'No caption'; // Get caption from the form
        $stmt->bind_param("ss", $filename, $description);
        
        if ($stmt->execute()) {
            // Return success response with image info
            echo json_encode([
                'success' => true,
                'filename' => $filename,
                'url' => $destination,
                'description' => $description
            ]);
        } else {
            // Database error - delete the uploaded file
            unlink($destination);
            http_response_code(500);
            echo json_encode(['error' => 'Database error']);
        }
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to move uploaded file']);
    }
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}

$conn->close();
?>
