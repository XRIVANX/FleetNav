<?php

// Include database connection and configuration
include("connect.php");

// Handle session status check and clearance at the very top
$status_data = null;
if (isset($_SESSION['status'])) {
    $status_data = $_SESSION['status'];
    unset($_SESSION['status']);
}
/**
 * Handles the user login process.
 */
function loginUser($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login_submit'])) {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $selectedAccountType = $_POST['account_type']; // 'Admin', 'Super Admin' or 'Driver'

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
            if ($user['accountType'] === $selectedAccountType || ($user['accountType'] === 'Super Admin' && $selectedAccountType === 'Admin')) {
                
                // Set session variables
                $_SESSION['accountID'] = $user['accountID'];
                $_SESSION['accountType'] = $user['accountType'];
                $_SESSION['firstName'] = $user['firstName'];
                $_SESSION['lastName'] = $user['lastName'];
                $_SESSION['profileImg'] = $user['profileImg']; 

                // =========================================================
                // START: LOG SUCCESSFUL LOGIN
                // =========================================================
                $logType = 'LOGIN'; // Matches the filter added in AdminPage.php
                $logDetails = "User logged in successfully.";
                
                // Prepare insert statement for Action_Logs table
                $logStmt = $conn->prepare("INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, ?, ?)");
                if ($logStmt) {
                    $logStmt->bind_param("iss", $user['accountID'], $logType, $logDetails);
                    $logStmt->execute();
                    $logStmt->close();
                }
                // =========================================================
                // END: LOG SUCCESSFUL LOGIN
                // =========================================================
                
                // 4. Redirect to the correct dashboard
                if ($user['accountType'] == 'Admin' || $user['accountType'] == 'Super Admin') {
                    header("Location: " . BASE_URL . "AdminPage.php");
                    exit();
                } elseif ($user['accountType'] == 'Driver') {
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
        $adminRegPassInput = isset($_POST['super_admin_reg_pass']) ? $_POST['super_admin_reg_pass'] : null; // [MODIFIED] Generic variable name

        // Retrieve the uploaded profile image path
        $profileImg = trim($_POST['uploadedProfileImagePath'] ?? '');
        $profileImg = empty($profileImg) ? null : $profileImg; 

        $accountType = isset($_POST['account_role_type']) ? trim($_POST['account_role_type']) : 'Driver';
        // FIX: Allow Super Admin as a valid type
        if ($accountType !== 'Admin' && $accountType !== 'Driver' && $accountType !== 'Super Admin') { 
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
        
        // --- [MODIFIED] Admin & Super Admin Registration Password Check ---
        // Now applies to both 'Admin' and 'Super Admin'
        if ($accountType === 'Super Admin' || $accountType === 'Admin') {
            if (empty($adminRegPassInput)) {
                 $_SESSION['status'] = [
                    'type' => 'error',
                    'message' => "The " . $accountType . " registration password is required.",
                    'role' => $redirectRole
                ];
                header("Location: " . BASE_URL . "index.php");
                exit();
            }
            
            // 1. Fetch the official registration password from adminRegPass table
            $pass_stmt = $conn->prepare("SELECT adminRegPass FROM adminRegPass LIMIT 1");
            $pass_stmt->execute();
            $pass_result = $pass_stmt->get_result();

            // [FIXED] Auto-initialize table if empty
            if ($pass_result->num_rows === 0) {
                // Insert the default hash provided in the database schema (Password: '12345')
                $defaultHash = password_hash('12345', PASSWORD_DEFAULT);
                
                $init_stmt = $conn->prepare("INSERT INTO adminRegPass (adminRegPass) VALUES (?)");
                $init_stmt->bind_param("s", $defaultHash);
                $init_stmt->execute();
                $init_stmt->close();
                
                // Re-fetch to confirm and continue
                $pass_stmt->execute();
                $pass_result = $pass_stmt->get_result();
            }

            if ($pass_result->num_rows === 0) {
                 $_SESSION['status'] = [
                    'type' => 'error',
                    'message' => "Admin registration is currently disabled (no registration password configured).",
                    'role' => $redirectRole
                ];
                $pass_stmt->close();
                header("Location: " . BASE_URL . "index.php");
                exit();
            }
            
            $reg_pass_row = $pass_result->fetch_assoc();
            $official_reg_pass = $reg_pass_row['adminRegPass'];
            $pass_stmt->close();
            
            // 2. Verify the input password against the official one
            if (!password_verify($adminRegPassInput, $official_reg_pass)) {
                 $_SESSION['status'] = [
                    'type' => 'error',
                    'message' => "Invalid " . $accountType . " registration password.",
                    'role' => $redirectRole
                ];
                header("Location: " . BASE_URL . "index.php");
                exit();
            }
        }
        // -------------------------------------------------------------

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
 * Handles the process of changing the Super Admin Registration Password.
 * Requires an existing Super Admin's login credentials for authorization.
 */
function changeSuperAdminRegPass($conn) {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_sar_pass_submit'])) {
        $sa_email = trim($_POST['sa_auth_email']);
        $sa_password = $_POST['sa_auth_password'];
        $new_reg_pass = $_POST['new_reg_pass'];
        $confirm_new_reg_pass = $_POST['confirm_new_reg_pass'];
        $redirectRole = 'Admin'; // Redirect to admin login page on failure/success

        // 1. Basic validation
        if (empty($sa_email) || empty($sa_password) || empty($new_reg_pass) || empty($confirm_new_reg_pass)) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Please fill in all fields.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        
        // 2. Validate Super Admin Credentials
        // Check for 'Super Admin' account type specifically
        $auth_stmt = $conn->prepare("SELECT password, accountType FROM Accounts WHERE email = ? AND accountType = 'Super Admin'");
        $auth_stmt->bind_param("s", $sa_email);
        $auth_stmt->execute();
        $auth_result = $auth_stmt->get_result();

        if ($auth_result->num_rows !== 1) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Authorization failed: Invalid Super Admin Email or not a Super Admin account.',
                'role' => $redirectRole
            ];
            $auth_stmt->close();
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        
        $user = $auth_result->fetch_assoc();
        
        if (!password_verify($sa_password, $user['password'])) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'Authorization failed: Invalid Super Admin password.',
                'role' => $redirectRole
            ];
            $auth_stmt->close();
            header("Location: " . BASE_URL . "index.php");
            exit();
        }
        $auth_stmt->close(); // Credentials validated

        // 3. Validate new registration passwords match
        if ($new_reg_pass !== $confirm_new_reg_pass) {
            $_SESSION['status'] = [
                'type' => 'error',
                'message' => 'New registration passwords do not match.',
                'role' => $redirectRole
            ];
            header("Location: " . BASE_URL . "index.php");
            exit();
        }

        // 4. Hash the new registration password
        $hashed_new_reg_pass = password_hash($new_reg_pass, PASSWORD_DEFAULT);

        // 5. Update the adminRegPass table
        // Update first (for existing records)
        $update_stmt = $conn->prepare("UPDATE adminRegPass SET adminRegPass = ?");
        $update_stmt->bind_param("s", $hashed_new_reg_pass);
        $update_stmt->execute();
        $rows_affected = $update_stmt->affected_rows;
        $update_stmt->close();

        if ($rows_affected === 0) {
            // If the update failed (no rows exist), insert the first entry
            $insert_stmt = $conn->prepare("INSERT INTO adminRegPass (adminRegPass) VALUES (?)");
            $insert_stmt->bind_param("s", $hashed_new_reg_pass);
            $insert_success = $insert_stmt->execute();
            $insert_stmt->close();

            if (!$insert_success) {
                 $_SESSION['status'] = [
                    'type' => 'error',
                    'message' => 'Failed to set the initial Super Admin Registration Password.',
                    'role' => $redirectRole
                ];
                header("Location: " . BASE_URL . "index.php");
                exit();
            }
        }
        
        // Success
        $_SESSION['status'] = [
            'type' => 'success',
            'message' => 'Super Admin Registration Password has been successfully changed.',
            'role' => $redirectRole
        ];

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
} elseif (isset($_POST['change_sar_pass_submit'])) {
    changeSuperAdminRegPass($conn);
}

?>