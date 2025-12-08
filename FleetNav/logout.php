<?php
session_start();

// Include database connection (connect.php) and configuration (config.php)
// We need connect.php for the $conn variable to insert the log
include("connect.php"); 
include("config.php"); 

// 1. Check if the user is currently logged in to get their ID
if (isset($_SESSION['accountID'])) {
    $accountID = $_SESSION['accountID'];
    
    // 2. Prepare the Log Entry
    $logType = 'LOGOUT';
    $logDetails = "User logged out successfully.";
    
    // 3. Insert into Action_Logs
    if ($conn) {
        $stmt = $conn->prepare("INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $accountID, $logType, $logDetails);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// 4. Destroy the session (Standard Logout)
session_unset();
session_destroy();

// 5. Redirect
header("Location: " . BASE_URL . "index.php");
exit();
?>