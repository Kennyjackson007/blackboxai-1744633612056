<?php
session_start();
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'photo_gallery';

$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle registration
    if (isset($_GET['action']) && $_GET['action'] === 'register') {
        $data = json_decode(file_get_contents('php://input'), true);
        $username = $conn->real_escape_string($data['username']);
        $password = password_hash($data['password'], PASSWORD_DEFAULT);
        
        try {
            $stmt = $conn->prepare("INSERT INTO users (username, password) VALUES (?, ?)");
            $stmt->bind_param("ss", $username, $password);
            $stmt->execute();
            echo json_encode(['success' => true]);
        } catch (mysqli_sql_exception $e) {
            if ($e->getCode() === 1062) {
                echo json_encode(['error' => 'Username already exists']);
            } else {
                echo json_encode(['error' => 'Registration failed']);
            }
        }
        exit;
    }
    // Handle login
    elseif (isset($_POST['username']) && isset($_POST['password'])) {
        $username = $conn->real_escape_string($_POST['username']);
        $password = $_POST['password'];
        
        $stmt = $conn->prepare("SELECT id, password FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                echo json_encode(['success' => true]);
                exit;
            }
        }
        http_response_code(401);
        echo json_encode(['error' => 'Invalid credentials']);
    }
}
?>
