<?php

// Include database connection and configuration
include("connect.php");

// Handle session status check and clearance at the very top
$status_data = null;
if (isset($_SESSION['status'])) {
    $status_data = $_SESSION['status'];
    unset($_SESSION['status']); // Clear status immediately after reading
}

// Note: Removed global $message variable as we are now using the Modal for everything.

// --- Function Definitions ---

/**
 * Handles the user login process.
 */
function loginUser($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $selectedAccountType = $_POST['account_type']; // 'Admin' or 'Driver'

        // 1. Check for Empty Fields
        if (empty($email) || empty($password)) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Please fill in all fields.',
                'role' => $selectedAccountType
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }

        // FIX 1: Retrieve all necessary fields (Added profileImg)
        $stmt = $conn->prepare("SELECT accountID, password, accountType, firstName, lastName, profileImg FROM Accounts WHERE email = ?"); 
        if ($stmt === false) {
             $_SESSION['status'] = [
                'type' => 'error',
                'message' => "Database error: " . $conn->error,
                'role' => $selectedAccountType
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }

        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // 2. Verify password
            if (password_verify($password, $user['password'])) {
                // 3. Verify account type matches the login form used
                if ($user['accountType'] === $selectedAccountType) {
                    
                    // FIX 2: Store all required fields in the session (Added profileImg)
                    $_SESSION['accountID'] = $user['accountID'];
                    $_SESSION['accountType'] = $user['accountType'];
                    $_SESSION['firstName'] = $user['firstName'];
                    $_SESSION['lastName'] = $user['lastName'];
                    $_SESSION['profileImg'] = $user['profileImg']; // <--- NEW LINE
                    
                    // 4. Redirect to the correct dashboard
                    if ($user['accountType'] === 'Admin') {
                        header("Location: " . BASE_URL . "AdminPage.php");
                        exit();
                    } elseif ($user['accountType'] === 'Driver') {
                        header("Location: " . BASE_URL . "DriverPage.php");
                        exit();
                    }
                } else {
                    // Role mismatch error
                    $_SESSION['status'] = [
                        'type' => 'error',
                        'message' => 'Login error: Invalid role selection for this account.',
                        'role' => $selectedAccountType
                    ];
                }
            } else {
                // Password incorrect error
                $_SESSION['status'] = [
                    'type' => 'error',
                    'message' => 'Login error: Incorrect password.',
                    'role' => $selectedAccountType
                ];
            }
        } else {
            // Email not found error
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Login error: No account found with that email.',
                'role' => $selectedAccountType
            ];
        }
        $stmt->close();

        // If we reached here, an error occurred inside the logic blocks above.
        // Redirect back to index to show the modal.
        header("Location: " . BASE_URL . "index.php");
        exit();
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
        
        // Retrieve the uploaded profile image path
        $profileImg = trim($_POST['uploadedProfileImagePath'] ?? '');
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

        $stmt->bind_param("ssssssss", 
            $firstName, 
            $lastName, 
            $email, 
            $contactNo, 
            $address, 
            $hashed_password, 
            $accountType,
            $profileImg
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
        
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

/**
 * Handles the password reset request.
 */
function requestPasswordReset($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['reset_request_submit'])) {
        $email = trim($_POST['email']);
        $redirectRole = 'Admin'; // Default view

        if (empty($email)) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Please enter your email address.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        
        // Logic essentially the same, utilizing session for the message
        $_SESSION['status'] = [
            'type' => 'success',
            'message' => 'If an account with that email exists, a password reset link has been sent.',
            'role' => $redirectRole
        ];

        header("Location: " . BASE_URL . "index.php");
        exit();
    }
}

// Call the appropriate function based on the form submission
if (isset($_POST['login_submit'])) {
    loginUser($conn);
} elseif (isset($_POST['register_submit'])) {
    registerUser($conn);
} elseif (isset($_POST['reset_request_submit'])) {
    requestPasswordReset($conn);
}

?>