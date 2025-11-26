<?php

// Include database connection and configuration
include("connect.php");

// Handle session status check and clearance at the very top
$status_data = null;
if (isset($_SESSION['status'])) {
    $status_data = $_SESSION['status'];
    unset($_SESSION['status']); // Clear status immediately after reading
}

$message = ""; // Variable to store immediate login errors

// --- Function Definitions ---

/**
 * Handles the user login process.
 */
function loginUser($conn) {
    global $message;
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $selectedAccountType = $_POST['account_type']; // 'Admin' or 'Driver'

        if (empty($email) || empty($password)) {
            $message = "<div style='color:red; text-align:center;'>Please fill in all fields.</div>";
            return;
        }

        // FIX 1: Retrieve all necessary fields from the database, including lastName.
        $stmt = $conn->prepare("SELECT accountID, password, accountType, firstName, lastName FROM Accounts WHERE email = ?"); 
        if ($stmt === false) {
             $message = "<div style='color:red; text-align:center;'>Database error: " . $conn->error . "</div>";
             return;
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 1. Verify password
            if (password_verify($password, $user['password'])) {
                // 2. Verify account type matches the login form used
                if ($user['accountType'] === $selectedAccountType) {
                    
                    // FIX 2: Store all required fields in the session.
                    $_SESSION['accountID'] = $user['accountID'];
                    $_SESSION['accountType'] = $user['accountType'];
                    $_SESSION['firstName'] = $user['firstName'];
                    $_SESSION['lastName'] = $user['lastName']; // <-- THIS IS THE KEY FIX
                    
                    // 3. Redirect to the correct dashboard
                    if ($user['accountType'] === 'Admin') {
                        header("Location: " . BASE_URL . "AdminPage.php");
                        exit();
                    } elseif ($user['accountType'] === 'Driver') {
                        header("Location: " . BASE_URL . "DriverPage.php");
                        exit();
                    }
                } else {
                    $message = "<div style='color:red; text-align:center;'>Login error: Invalid role selection for this account.</div>";
                }
            } else {
                $message = "<div style='color:red; text-align:center;'>Login error: Incorrect password.</div>";
            }
        } else {
            $message = "<div style='color:red; text-align:center;'>Login error: No account found with that email.</div>";
        }
        $stmt->close();
    }
}
/**
 * Handles the user registration process.
 */
function registerUser($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_submit'])) {
        $firstName = trim($_POST['firstName']);
        $lastName = trim($_POST['lastName']);
        $email = trim($_POST['email']);
        $contactNo = trim($_POST['contactNo']);
        $address = trim($_POST['address']);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        // NEW: Retrieve the uploaded profile image path from the hidden input
        $profileImg = trim($_POST['uploadedProfileImagePath'] ?? '');
        // Set to NULL if the path is empty (user didn't upload a picture)
        $profileImg = empty($profileImg) ? null : $profileImg; 

        $accountType = isset($_POST['account_role_type']) ? trim($_POST['account_role_type']) : 'Driver';
        if ($accountType !== 'Admin' && $accountType !== 'Driver') {
             $accountType = 'Driver';
        }
        $redirectRole = $accountType;

        if (empty($firstName) || empty($lastName) || empty($email) || empty($password) || empty($confirmPassword)) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Please fill in all required fields.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }

        if ($password !== $confirmPassword) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Passwords do not match.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $check_stmt = $conn->prepare("SELECT accountID FROM Accounts WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Registration failed: An account with this email already exists.',
                'role' => $redirectRole
            ];
            $check_stmt->close();
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        $check_stmt->close();
        
        // MODIFIED SQL: Added profileImg column
        $sql = "INSERT INTO Accounts (firstName, lastName, email, contactNo, address, password, accountType, profileImg) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
             $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Database error during registration: ' . $conn->error,
                'role' => $redirectRole
            ];
             header("Location: " . BASE_URL . "index.php");
             exit();
        }

        // MODIFIED BIND_PARAM: Added one 's' for profileImg (total 8 strings)
        $stmt->bind_param("ssssssss", 
            $firstName, 
            $lastName, 
            $email, 
            $contactNo, 
            $address, 
            $hashed_password, 
            $accountType,
            $profileImg // NEW parameter
        );
        
        if ($stmt->execute()) {
            $_SESSION['status'] = [
                'type' => 'success',
                'message' => "Registration successful! You can now log in as a {$accountType}.",
                'role' => $redirectRole
            ];
        } else {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Registration failed: ' . $stmt->error,
                'role' => $redirectRole
            ];
        }
        $stmt->close();
        
        // Redirect to index.php to trigger the modal display on page load
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

/**
 * Handles the password reset request (sends a fictitious email).
 */
function requestPasswordReset($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request_submit'])) {
        $email = trim($_POST['email']);
        $redirectRole = 'Admin'; // Default to Admin Login after password reset

        if (empty($email)) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Please enter your email address.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        
        $stmt = $conn->prepare("SELECT accountID FROM Accounts WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $_SESSION['status'] = [
                'type' => 'success',
                'message' => 'If an account with that email exists, a password reset link has been sent.',
                'role' => $redirectRole
            ];
        } else {
            $_SESSION['status'] = [
                'type' => 'success',
                'message' => 'If an account with that email exists, a password reset link has been sent.',
                'role' => $redirectRole
            ];
        }
        $stmt->close();

        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

// Call the appropriate function based on the form submission
$intended_role = isset($_GET['role']) && ($_GET['role'] === 'Admin' || $_GET['role'] === 'Driver') ? $_GET['role'] : 'Driver';

if (isset($_POST['login_submit'])) {
    loginUser($conn);
} elseif (isset($_POST['register_submit'])) {
    registerUser($conn);
} elseif (isset($_POST['reset_request_submit'])) {
    requestPasswordReset($conn);
}

?>