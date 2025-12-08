<?php
// [FIXED] Define $adminID at the top, as it's required for logging and list exclusion later in this file.
$adminID = $_SESSION['accountID'] ?? 0;

// =========================================================
//                  LOGGER FUNCTION 
// =========================================================
// Helper function to insert logs into the Action_Logs table
function log_action($actor_accountID, $action_type, $action_details) {
    global $conn;
    // Check if $conn is available and the ID is valid
    if ($conn && $actor_accountID > 0) {
        $sql = "INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("iss", $actor_accountID, $action_type, $action_details);
            $stmt->execute();
            $stmt->close();
        }
    }
}


// =========================================================
//                  HANDLE USER ADDITION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_driver_submit'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // [MODIFIED] Get accountType from POST if present (Super Admin), default to 'Driver'
    $accountType = $_POST['accountType'] ?? 'Driver';
    $submittedAdminPass = $_POST['adminRegPass'] ?? ''; // [ADDED] Get admin pass from form
    
    $validationError = false;

    // Ensure accountType is valid
    if (!in_array($accountType, ['Driver', 'Admin', 'Super Admin'])) {
        $accountType = 'Driver';
    }
    
    // [SECURITY CHECK 1] Only Super Admin can add Admin/Super Admin
    if ($accountType !== 'Driver' && $_SESSION['accountType'] !== 'Super Admin') {
        $message = '<div class="alert alert-danger">Error: Access Denied. Only Super Admins can create Admin or Super Admin accounts.</div>';
        $validationError = true;
    }

    // [SECURITY CHECK 2] Require AdminRegPass for creating Admin/Super Admin
    if (!$validationError && ($accountType === 'Admin' || $accountType === 'Super Admin')) {
        // Fetch the stored hash
        $passStmt = $conn->prepare("SELECT adminRegPass FROM adminRegPass LIMIT 1");
        $storedHash = '';
        if ($passStmt) {
            $passStmt->execute();
            $res = $passStmt->get_result();
            if ($row = $res->fetch_assoc()) {
                $storedHash = $row['adminRegPass'];
            }
            $passStmt->close();
        }

        if (empty($submittedAdminPass)) {
            $message = '<div class="alert alert-danger">Error: Admin Registration Password is required to create an ' . htmlspecialchars($accountType) . ' account.</div>';
            $validationError = true;
        } elseif (!password_verify($submittedAdminPass, $storedHash)) {
            $message = '<div class="alert alert-danger">Error: Incorrect Admin Registration Password. User not created.</div>';
            $validationError = true;
        }
    }

    if (!$validationError) {
        if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
            $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Error: Invalid email format.</div>';
        } else {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            // Check for duplicate EMAIL only
            $checkStmt = $conn->prepare("SELECT accountID FROM Accounts WHERE email = ?");
            $checkStmt->bind_param("s", $email);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $message = '<div class="alert alert-danger">Error: Email already exists.</div>';
            } else {
                // Removed username from INSERT
                $sql = "INSERT INTO Accounts (firstName, lastName, email, password, accountType) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    // Bind params: sssss (First, Last, Email, Pass, Type)
                    $stmt->bind_param("sssss", $firstName, $lastName, $email, $hashedPassword, $accountType);

                    if ($stmt->execute()) {
                        $newUserID = $conn->insert_id; // Get the ID of the newly created user
                        $message = '<div class="alert alert-success">' . htmlspecialchars($accountType) . ' **' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . '** added successfully!</div>';
                        
                        // [ADDED LOG]
                        $details = "Created new user (ID: $newUserID, Role: $accountType): $firstName $lastName ($email)";
                        log_action($adminID, 'USER_CREATE', $details);

                    } else {
                        $message = '<div class="alert alert-danger">Failed to add user. Database Error: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                } else {
                    $message = '<div class="alert alert-danger">Failed to prepare statement.</div>';
                }
            }
            $checkStmt->close();
        }
    }
    // [MODIFIED] Adjust view based on current user role
    $view = ($_SESSION['accountType'] === 'Super Admin') ? 'accounts' : 'drivers';
}

// =========================================================
//                  HANDLE USER UPDATES (EDIT)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_driver_submit'])) {
    $accountID = (int)($_POST['editAccountID'] ?? 0);
    $firstName = trim($_POST['editFirstName'] ?? '');
    $lastName = trim($_POST['editLastName'] ?? '');
    $email = trim($_POST['editEmail'] ?? '');
    $password = $_POST['editPassword'] ?? ''; // Optional
    
    $currentAccountType = $_POST['editCurrentAccountType'] ?? 'Driver'; // Original role from hidden field
    $newAccountType = $_POST['editAccountType'] ?? $currentAccountType;
    $submittedAdminPass = $_POST['adminRegPass'] ?? ''; // [ADDED] Get submitted admin password

    $validationError = false; // Flag to stop execution

    // --- [CRITICAL SECURITY CHECK 1: ONLY SUPER ADMIN CAN EDIT] ---
    if ($_SESSION['accountType'] !== 'Super Admin') {
        $message = '<div class="alert alert-danger">Error: Access Denied. Only Super Admins are authorized to change user details.</div>';
        $validationError = true;
    }

    // Prevent changing the role of the current user's *own* Super Admin status
    if (!$validationError && $accountID == $_SESSION['accountID'] && $_SESSION['accountType'] === 'Super Admin' && $newAccountType !== 'Super Admin') {
        $newAccountType = 'Super Admin'; // Force keep as Super Admin
    }

    if (!$validationError) {
        if ($accountID <= 0 || empty($firstName) || empty($lastName) || empty($email)) {
            $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
            $validationError = true;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $message = '<div class="alert alert-danger">Error: Invalid email format.</div>';
            $validationError = true;
        }
    }

    // --- [CRITICAL SECURITY CHECK 2: REQUIRE ADMIN REG PASS FOR *ANY* EDIT] ---
    if (!$validationError && $_SESSION['accountType'] === 'Super Admin') {
        
        // 1. Fetch the stored admin registration password hash
        $passStmt = $conn->prepare("SELECT adminRegPass FROM adminRegPass LIMIT 1");
        $storedHash = '';
        if ($passStmt) {
            $passStmt->execute();
            $passResult = $passStmt->get_result();
            $passRow = $passResult->fetch_assoc();
            $passStmt->close();
            $storedHash = $passRow['adminRegPass'] ?? '';
        }

        // 2. Verify the submitted password against the hash
        if (empty($submittedAdminPass)) {
            $message = '<div class="alert alert-danger">Error: Admin Registration Password is required to save changes.</div>';
            $validationError = true;
        } elseif (!password_verify($submittedAdminPass, $storedHash)) {
            $message = '<div class="alert alert-danger">Error: Incorrect Admin Registration Password. Changes not saved.</div>';
            $validationError = true;
        }
    }
    // -----------------------------------------------------------

    if (!$validationError) {
        
        // Fetch old data for logging comparison
        $oldDataStmt = $conn->prepare("SELECT firstName, lastName, email, accountType FROM Accounts WHERE accountID = ?");
        $oldDataStmt->bind_param("i", $accountID);
        $oldDataStmt->execute();
        $oldUser = $oldDataStmt->get_result()->fetch_assoc();
        $oldDataStmt->close();
        
        $changes = [];
            
        // Check for changes
        if ($oldUser['firstName'] !== $firstName) { $changes[] = "First Name ('{$oldUser['firstName']}' -> '{$firstName}')"; }
        if ($oldUser['lastName'] !== $lastName) { $changes[] = "Last Name ('{$oldUser['lastName']}' -> '{$lastName}')"; }
        if ($oldUser['email'] !== $email) { $changes[] = "Email ('{$oldUser['email']}' -> '{$email}')"; }
        if (!empty($password)) { $changes[] = "Password (reset)"; }
        
        // Check role change
        if ($oldUser['accountType'] !== $newAccountType) { 
            $changes[] = "Role ('{$oldUser['accountType']}' -> '{$newAccountType}')"; 
        }

        if (empty($changes)) {
            $message = '<div class="alert alert-info">No changes were made to User ID **' . $accountID . '**.</div>';
        } else {
            // Proceed with update
            $updatePassword = !empty($password);
            
            // Removed username from UPDATE SQL
            $sql = "UPDATE Accounts SET firstName = ?, lastName = ?, email = ?, accountType = ?";
            
            if ($updatePassword) { $sql .= ", password = ?"; }
            $sql .= " WHERE accountID = ?";

            $stmt = $conn->prepare($sql);

            if ($stmt) {
                // Check duplicate email on other accounts
                $checkStmt = $conn->prepare("SELECT accountID FROM Accounts WHERE email = ? AND accountID != ?");
                $checkStmt->bind_param("si", $email, $accountID);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();

                if ($checkResult->num_rows > 0) {
                    $message = '<div class="alert alert-danger">Error: Email already exists for another account.</div>';
                    $checkStmt->close();
                } else {
                    $checkStmt->close();
                    
                    // Bind logic
                    $paramTypes = "ssss"; // First, Last, Email, Type
                    $params = [&$firstName, &$lastName, &$email, &$newAccountType];
                    
                    if ($updatePassword) {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $paramTypes .= "s";
                        $params[] = &$hashedPassword;
                    }
                    
                    $paramTypes .= "i";
                    $params[] = &$accountID;
                    
                    $stmt->bind_param($paramTypes, ...$params);

                    if ($stmt->execute()) {
                        $message = '<div class="alert alert-success">User ID **' . $accountID . '** updated successfully!</div>';
                        
                        // Log changes
                        $logDetails = "Updated user (ID: $accountID). Changes: " . implode(", ", $changes);
                        log_action($adminID, 'USER_UPDATE', $logDetails);
                        
                        // If the current user updated their own role
                        if ($accountID == $_SESSION['accountID']) {
                            $_SESSION['accountType'] = $newAccountType;
                        }

                    } else {
                        $message = '<div class="alert alert-danger">Failed to update user. Database Error: ' . $stmt->error . '</div>';
                    }
                }
                $stmt->close();
            }
        }
    }
    // [MODIFIED] Adjust view based on current user role
    $view = ($_SESSION['accountType'] === 'Super Admin') ? 'accounts' : 'drivers';
}

// =========================================================
//                  HANDLE USER DELETION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_user_id'])) {
    // Sanitize and cast to integer for security
    $accountID_to_delete = (int)$_POST['delete_user_id'];
    $account_name = '';
    $account_type = '';

    // 1. Fetch user details for confirmation and logging
    $stmt = $conn->prepare("SELECT firstName, lastName, accountType FROM Accounts WHERE accountID = ?");
    if ($stmt) {
        $stmt->bind_param("i", $accountID_to_delete);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $account_name = $row['firstName'] . ' ' . $row['lastName'];
            $account_type = $row['accountType'];
        }
        $stmt->close();
    }

    if ($account_name) {
        // Start transaction for atomic deletion
        $conn->begin_transaction();
        try {
            // A. Clear Trucks Dependency: Set assignedDriver to NULL for any trucks assigned to this user
            $sql_trucks = "UPDATE Trucks SET assignedDriver = NULL WHERE assignedDriver = ?";
            $stmt_trucks = $conn->prepare($sql_trucks);
            $stmt_trucks->bind_param("i", $accountID_to_delete);
            $stmt_trucks->execute();
            $stmt_trucks->close();

            // B. Delete History_Reports: (Must be deleted first due to FK constraint)
            $sql_history = "DELETE FROM History_Reports WHERE driverID = ?";
            $stmt_history = $conn->prepare($sql_history);
            $stmt_history->bind_param("i", $accountID_to_delete);
            $stmt_history->execute();
            $stmt_history->close();

            // C. Delete Action_Logs: (Must be deleted first due to FK constraint)
            $sql_logs = "DELETE FROM Action_Logs WHERE accountID = ?";
            $stmt_logs = $conn->prepare($sql_logs);
            $stmt_logs->bind_param("i", $accountID_to_delete);
            $stmt_logs->execute();
            $stmt_logs->close();
            
            // D. Delete Account
            $sql_account = "DELETE FROM Accounts WHERE accountID = ?";
            $stmt_account = $conn->prepare($sql_account);
            $stmt_account->bind_param("i", $accountID_to_delete);

            if ($stmt_account->execute()) {
                $conn->commit();
                $message = '<div class="alert alert-success">Successfully deleted ' . htmlspecialchars($account_type) . ': ' . htmlspecialchars($account_name) . '.</div>';
                
                // Log the successful action
                $details = "Deleted " . $account_type . " account: " . $account_name . " (ID: " . $accountID_to_delete . ")";
                log_action($adminID, 'USER_DELETE', $details);
            } else {
                throw new Exception("Failed to delete account from Accounts table.");
            }

        } catch (Exception $e) {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Database Error: Failed to delete user ' . htmlspecialchars($account_name) . '. Please try again.</div>';
            
            // Log the failure
            log_action($adminID, 'USER_DELETE_FAIL', "Attempt to delete user ID " . $accountID_to_delete . " failed.");
        }
    } else {
        $message = '<div class="alert alert-danger">Error: User not found or ID is invalid.</div>';
    }
}

// =========================================================
//                  FETCH USERS/DRIVERS LIST
// =========================================================
$usersList = [];

// [MODIFIED] Use a single list name $usersList for the combined view
if ($view === 'drivers' || ($view === 'accounts' && $_SESSION['accountType'] === 'Super Admin')) {
    
    $whereClauses = [];
    
    // 1. Filter by accountType (if not Super Admin)
    if ($_SESSION['accountType'] !== 'Super Admin') {
        $whereClauses[] = "A.accountType = 'Driver'";
    }
    
    // 2. [FIXED & MODIFIED] Exclude the current user's account ID from the list
    $adminID_safe = (int)$adminID;
    $whereClauses[] = "A.accountID != " . $adminID_safe;
    
    $driverCondition = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";
    
    // Removed A.username from SELECT
    $queryUsersList = "
        SELECT 
            A.accountID, A.firstName, A.lastName, A.email, A.profileImg, A.accountType,
            T.truckName, T.truckID
        FROM Accounts A
        LEFT JOIN Trucks T ON A.accountID = T.assignedDriver
        $driverCondition
        ORDER BY A.accountType DESC, A.lastName ASC
    ";
    
    $resultUsersList = $conn->query($queryUsersList);
    if ($resultUsersList) {
        while ($row = $resultUsersList->fetch_assoc()) {
            $usersList[] = $row;
        }
    }
    
    // [MODIFIED] Rename the list variable for consistency
    $driversList = $usersList; 
}
?>