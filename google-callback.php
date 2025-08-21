<?php
session_start();
require_once 'config.php';
require_once 'vendor/autoload.php';

// Initialize Google Client
$client = new Google_Client();
$client->setClientId('YOUR_GOOGLE_CLIENT_ID');
$client->setClientSecret('YOUR_GOOGLE_CLIENT_SECRET');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Google Sign-In
    $content = file_get_contents('php://input');
    $data = json_decode($content, true);
    
    try {
        $payload = $client->verifyIdToken($data['credential']);
        
        if ($payload) {
            $google_id = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            
            // Check if user exists
            $sql = "SELECT * FROM users WHERE email = ? OR google_id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ss", $email, $google_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing user
                $user = $result->fetch_assoc();
                $update_sql = "UPDATE users SET google_id = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("si", $google_id, $user['id']);
                $update_stmt->execute();
                
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
            } else {
                // Create new user
                $sql = "INSERT INTO users (name, email, google_id, password) VALUES (?, ?, ?, '')";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sss", $name, $email, $google_id);
                
                if ($stmt->execute()) {
                    $_SESSION['user_id'] = $stmt->insert_id;
                    $_SESSION['email'] = $email;
                }
            }
            
            echo json_encode(['success' => true]);
            exit();
        }
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
}

echo json_encode(['success' => false]);
