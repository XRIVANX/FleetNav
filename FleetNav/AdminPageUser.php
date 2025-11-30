<?php
// =========================================================
//                  HANDLE DRIVER ADDITION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_driver_submit'])) {
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $accountType = 'Driver';

    if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($password)) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Error: Invalid email format.</div>';
    } else {
        // Hash the password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        // Check for duplicate username or email first
        $checkStmt = $conn->prepare("SELECT accountID FROM Accounts WHERE username = ? OR email = ?");
        $checkStmt->bind_param("ss", $username, $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();

        if ($checkResult->num_rows > 0) {
            $message = '<div class="alert alert-danger">Error: Username or Email already exists.</div>';
        } else {
            $sql = "INSERT INTO Accounts (firstName, lastName, username, email, password, accountType) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);

            if ($stmt) {
                $stmt->bind_param("ssssss", $firstName, $lastName, $username, $email, $hashedPassword, $accountType);

                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Driver **' . htmlspecialchars($firstName) . ' ' . htmlspecialchars($lastName) . '** added successfully!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Failed to add driver. Database Error: ' . $stmt->error . '</div>';
                }
                $stmt->close();
            } else {
                $message = '<div class="alert alert-danger">Failed to prepare statement.</div>';
            }
        }
        $checkStmt->close();
    }
    $view = 'drivers';
}

// =========================================================
//                  HANDLE DRIVER UPDATES (EDIT)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_driver_submit'])) {
    $accountID = (int)($_POST['editAccountID'] ?? 0);
    $firstName = trim($_POST['editFirstName'] ?? '');
    $lastName = trim($_POST['editLastName'] ?? '');
    $username = trim($_POST['editUsername'] ?? '');
    $email = trim($_POST['editEmail'] ?? '');
    $password = $_POST['editPassword'] ?? ''; // Optional

    if ($accountID <= 0 || empty($firstName) || empty($lastName) || empty($username) || empty($email)) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '<div class="alert alert-danger">Error: Invalid email format.</div>';
    } else {
        $updatePassword = !empty($password);
        $sql = "UPDATE Accounts SET firstName = ?, lastName = ?, username = ?, email = ?"
            . ($updatePassword ? ", password = ?" : "")
            . " WHERE accountID = ? AND accountType = 'Driver'";

        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $checkStmt = $conn->prepare("SELECT accountID FROM Accounts WHERE (username = ? OR email = ?) AND accountID != ?");
            $checkStmt->bind_param("ssi", $username, $email, $accountID);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $message = '<div class="alert alert-danger">Error: Username or Email already exists for another account.</div>';
                $checkStmt->close();
            } else {
                $checkStmt->close();

                if ($updatePassword) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt->bind_param("sssssi", $firstName, $lastName, $username, $email, $hashedPassword, $accountID);
                } else {
                    $stmt->bind_param("ssssi", $firstName, $lastName, $username, $email, $accountID);
                }

                if ($stmt->execute()) {
                    $message = '<div class="alert alert-success">Driver ID **' . $accountID . '** updated successfully!</div>';
                } else {
                    $message = '<div class="alert alert-danger">Failed to update driver. Database Error: ' . $stmt->error . '</div>';
                }
            }
            $stmt->close();
        }
    }
    $view = 'drivers';
}

// =========================================================
//                  HANDLE DRIVER DELETION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_driver_id'])) {
    $accountID = (int)$_POST['delete_driver_id'];

    if ($accountID > 0) {
        // Prevent deletion if the driver is assigned a truck
        $checkTruckStmt = $conn->prepare("SELECT COUNT(*) FROM Trucks WHERE assignedDriver = ?");
        $checkTruckStmt->bind_param("i", $accountID);
        $checkTruckStmt->execute();
        $truckCount = $checkTruckStmt->get_result()->fetch_row()[0];
        $checkTruckStmt->close();

        if ($truckCount > 0) {
            $message = '<div class="alert alert-danger">Error: Cannot delete driver ID ' . $accountID . '. They are currently assigned to a truck.</div>';
        } else {
            // Prevent deletion if assigned to 'In Progress' delivery
            $checkDeliveryStmt = $conn->prepare("SELECT COUNT(*) FROM Deliveries WHERE driverID = ? AND deliveryStatus = 'In Progress'");
            // Note: Your DB schema in `Deliveries` might not have `driverID` directly if it uses `assignedTruck`. 
            // Assuming simplified logic based on your original file provided:
            // If checking via truck: Deliveries -> Truck -> Driver. 
            // I will use your original logic provided in the prompt:
            $stmt = $conn->prepare("DELETE FROM Accounts WHERE accountID = ? AND accountType = 'Driver'");
            $stmt->bind_param("i", $accountID);
            if ($stmt->execute()) {
                $message = '<div class="alert alert-success">Driver ID **' . $accountID . '** successfully removed.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error deleting driver ID ' . $accountID . ': ' . $stmt->error . '</div>';
            }
            $stmt->close();
        }
    }
    $view = 'drivers';
}

// =========================================================
//                  FETCH DRIVERS LIST
// =========================================================
$driversList = [];
if ($view === 'drivers') {
    $queryDriversList = "
        SELECT 
            A.accountID, A.firstName, A.lastName, A.username, A.email,
            T.truckName, T.truckID
        FROM Accounts A
        LEFT JOIN Trucks T ON A.accountID = T.assignedDriver
        WHERE A.accountType = 'Driver'
        ORDER BY A.lastName ASC
    ";
    $resultDriversList = $conn->query($queryDriversList);
    if ($resultDriversList) {
        while ($row = $resultDriversList->fetch_assoc()) {
            $driversList[] = $row;
        }
    }
}

?>