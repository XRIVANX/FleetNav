<?php
include("connect.php");

// Security Check: Only allow 'Admin' users
if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Admin') {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$firstName = $_SESSION['firstName']; 
$accountType = htmlspecialchars($_SESSION['accountType']);
$adminID = $_SESSION['accountID']; // Current Admin ID for logging - KEPT for future use, but not used in Trucks insertion

// Determine which view to load (Default to 'dashboard')
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$message = ''; // For operation success/failure messages

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
    $password = $_POST['editPassword'] ?? ''; // Optional: Only if changed
    
    if ($accountID <= 0 || empty($firstName) || empty($lastName) || empty($username) || empty($email)) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields for editing correctly.</div>';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
         $message = '<div class="alert alert-danger">Error: Invalid email format.</div>';
    } else {
        $updatePassword = !empty($password);
        $sql = "UPDATE Accounts SET firstName = ?, lastName = ?, username = ?, email = ?" 
               . ($updatePassword ? ", password = ?" : "") 
               . " WHERE accountID = ? AND accountType = 'Driver'";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Check for duplicate username/email (excluding current accountID)
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
                    $message = '<div class="alert alert-success">Driver ID **' . $accountID . '** updated successfully!' . ($updatePassword ? ' (Password changed)' : '') . '</div>';
                } else {
                    $message = '<div class="alert alert-danger">Failed to update driver. Database Error: ' . $stmt->error . '</div>';
                }
            }
            $stmt->close();
        } else {
             $message = '<div class="alert alert-danger">Failed to prepare update statement.</div>';
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
            $message = '<div class="alert alert-danger">Error: Cannot delete driver ID ' . $accountID . '. They are currently assigned to a truck. Please unassign the truck first.</div>';
        } else {
             // Prevent deletion if the driver is assigned to an 'In Progress' delivery
             $checkDeliveryStmt = $conn->prepare("SELECT COUNT(*) FROM Deliveries WHERE driverID = ? AND deliveryStatus = 'In Progress'");
             $checkDeliveryStmt->bind_param("i", $accountID);
             $checkDeliveryStmt->execute();
             $deliveryCount = $checkDeliveryStmt->get_result()->fetch_row()[0];
             $checkDeliveryStmt->close();
             
             if ($deliveryCount > 0) {
                $message = '<div class="alert alert-danger">Error: Cannot delete driver ID ' . $accountID . '. They are currently assigned to an **In Progress** delivery.</div>';
             } else {
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
    } else {
        $message = '<div class="alert alert-danger">Invalid driver ID provided for deletion.</div>';
    }
    $view = 'drivers';
}

// =========================================================
//                  HANDLE TRUCK ADDITION (FIXED BINDING)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_truck_submit'])) {
    
    // FIX: Using null coalescing (??) to prevent "Undefined array key" warnings 
    $truckName = trim($_POST['truckName'] ?? '');
    $plateNumber = trim($_POST['plateNumber'] ?? ''); 
    $truckStatus = $_POST['truckStatus'] ?? 'Available'; 
    
    // Ensure odometer is treated as an integer, defaulting to 0 if missing
    $odometer = (int)($_POST['odometerOrMileage'] ?? 0); 
    $registrationDate = $_POST['registrationDate'] ?? null; 
    
    // Retrieve the path from the hidden field populated by the AJAX upload script.
    $truckImgPath = isset($_POST['uploadedImagePath']) && !empty($_POST['uploadedImagePath']) ? trim($_POST['uploadedImagePath']) : null;
    
    // Set to NULL if no driver is selected (the form value is typically an empty string '0' or '')
    $assignedDriverID = empty($_POST['assignedDriver']) || $_POST['assignedDriver'] === '0' ? null : (int)$_POST['assignedDriver'];
    
    // Input Validation 
    if (empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields (Truck Name, Plate Number, Status, Odometer, Registration Date) correctly.</div>';
    } else {
        // --- Database Insertion Logic (Uses defensive variables above) ---
        $stmt = null;
        
        if ($assignedDriverID === null) {
            // Case 1: No assigned driver
            $sql = "INSERT INTO Trucks (truckName, plateNumber, truckStatus, odometerOrMileage, registrationDate, truckImg) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // CORRECTED BIND_PARAM: s (name), s (plate), s (status), i (odometer), s (date), s (img)
                $stmt->bind_param("sssiss", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $truckImgPath);
            }
        } else {
            // Case 2: An assigned driver is selected
            $sql = "INSERT INTO Trucks (truckName, plateNumber, truckStatus, odometerOrMileage, registrationDate, assignedDriver, truckImg) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql);
            
            if ($stmt) {
                // CORRECTED BIND_PARAM: s (name), s (plate), s (status), i (odometer), s (date), i (driverID), s (img)
                $stmt->bind_param("sssisis", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $assignedDriverID, $truckImgPath);
            }
        }

        if ($stmt && $stmt->execute()) {
             $message = '<div class="alert alert-success">Truck **' . htmlspecialchars($truckName) . '** added successfully!</div>';
        } else {
             $error_msg = $stmt ? $stmt->error : $conn->error;
             
             if ($conn->errno == 1452) {
                 $message = "<div class='alert alert-danger'>Failed to add truck. Database Error: Assigned driver ID does not exist.</div>";
             } else {
                 $message = '<div class="alert alert-danger">Failed to add truck. Database Error: ' . $error_msg . '</div>';
             }
        }
        
        if ($stmt) {
            $stmt->close();
        }
    }
    
    $view = 'trucks'; 
}

// =========================================================
//                  HANDLE TRUCK UPDATES (EDIT) - NEW LOGIC
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_truck_submit'])) {
    $truckID = (int)($_POST['editTruckID'] ?? 0);
    $truckName = trim($_POST['editTruckName'] ?? '');
    $plateNumber = trim($_POST['editPlateNumber'] ?? ''); 
    $truckStatus = $_POST['editTruckStatus'] ?? 'Available'; 
    $odometer = (int)($_POST['editOdometerOrMileage'] ?? 0); 
    $registrationDate = $_POST['editRegistrationDate'] ?? null; 
    
    // Image handling: Use the newly uploaded path, or keep the current one, or set to null
    $newTruckImgPath = isset($_POST['editUploadedImagePath']) && !empty($_POST['editUploadedImagePath']) ? trim($_POST['editUploadedImagePath']) : null;
    $currentTruckImg = trim($_POST['editCurrentTruckImg'] ?? '');
    
    // If a new path exists, use it. If the image was intentionally cleared ('null'), use null. Otherwise, keep the current path.
    $finalTruckImgPath = ($newTruckImgPath === 'null' ? null : $newTruckImgPath) ?? ($currentTruckImg !== 'null' ? $currentTruckImg : null);
    
    // Driver ID: Set to null if '0' or empty string is submitted
    $assignedDriverID = empty($_POST['editAssignedDriver']) || $_POST['editAssignedDriver'] === '0' ? null : (int)$_POST['editAssignedDriver'];

    if ($truckID <= 0 || empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields for editing correctly.</div>';
    } else {
        $stmt = null;
        
        if ($assignedDriverID === null) {
            // Case 1: Driver is unassigned (assignedDriver = NULL)
            $sql = "UPDATE Trucks SET 
                        truckName = ?, plateNumber = ?, truckStatus = ?, odometerOrMileage = ?, registrationDate = ?, assignedDriver = NULL, truckImg = ?
                    WHERE truckID = ?";
            $stmt = $conn->prepare($sql);
            // Binding: s (name), s (plate), s (status), i (odometer), s (date), s (img), i (truckID)
            $stmt->bind_param("sssissi", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $finalTruckImgPath, $truckID);
        } else {
            // Case 2: Driver is assigned
            $sql = "UPDATE Trucks SET 
                        truckName = ?, plateNumber = ?, truckStatus = ?, odometerOrMileage = ?, registrationDate = ?, assignedDriver = ?, truckImg = ?
                    WHERE truckID = ?";
            $stmt = $conn->prepare($sql);
            // Binding: s, s, s, i, s, i, s, i
            $stmt->bind_param("sssisisi", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $assignedDriverID, $finalTruckImgPath, $truckID);
        }

        if ($stmt && $stmt->execute()) {
             $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** updated successfully!</div>';
        } else {
             $error_msg = $stmt ? $stmt->error : $conn->error;
             
             if ($conn->errno == 1452) {
                 $message = "<div class='alert alert-danger'>Failed to update truck. Database Error: Assigned driver ID does not exist.</div>";
             } else {
                 $message = '<div class="alert alert-danger">Failed to update truck. Database Error: ' . $error_msg . '</div>';
             }
        }
        
        if ($stmt) {
            $stmt->close();
        }
    }
    
    $view = 'trucks'; 
}

// =========================================================
//                  HANDLE TRUCK DELETION - NEW LOGIC
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_truck_id'])) {
    $truckID = (int)$_POST['delete_truck_id'];

    if ($truckID > 0) {
        $stmt = $conn->prepare("DELETE FROM Trucks WHERE truckID = ?");
        $stmt->bind_param("i", $truckID);

        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** successfully removed.</div>';
        } else {
            // Handle foreign key constraint error if truck is linked to a delivery
            if ($conn->errno == 1451) {
                $message = '<div class="alert alert-danger">Error: Cannot delete truck ID ' . $truckID . '. It is currently assigned to one or more active deliveries.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error deleting truck ID ' . $truckID . ': ' . $stmt->error . '</div>';
            }
        }
        $stmt->close();
    } else {
        $message = '<div class="alert alert-danger">Invalid truck ID provided for deletion.</div>';
    }
    $view = 'trucks';
}


// Check for success message from redirect
if (isset($_GET['message']) && $_GET['message'] === 'success') {
    $message = "<div class='alert alert-success'>Truck successfully added/updated!</div>";
}

// =========================================================
//                  PHP METRICS FETCHING (Dashboard View)
// =========================================================
// ... (Existing dashboard fetching logic remains here) ...
if ($view === 'dashboard') {
    // --- 1. Drivers: Assigned (Unavailable) vs. Unassigned (Available) ---
    $queryTotalDrivers = "SELECT COUNT(accountID) AS total FROM Accounts WHERE accountType = 'Driver'";
    $resultTotalDrivers = $conn->query($queryTotalDrivers);
    $totalDrivers = $resultTotalDrivers->fetch_assoc()['total'];
    $queryAssignedDrivers = "SELECT COUNT(DISTINCT assignedDriver) AS assigned FROM Trucks WHERE assignedDriver IS NOT NULL";
    $resultAssignedDrivers = $conn->query($queryAssignedDrivers);
    $assignedDrivers = $resultAssignedDrivers->fetch_assoc()['assigned'];
    $driversData = [
        'available_drivers' => $totalDrivers - $assignedDrivers,
        'unavailable_drivers' => $assignedDrivers
    ];

    // --- 2. Trucks: Available vs. Unavailable (Based on truckStatus) ---
    $queryTrucksMetrics = "
        SELECT
            SUM(CASE WHEN truckStatus = 'Available' THEN 1 ELSE 0 END) AS available_trucks,
            SUM(CASE WHEN truckStatus != 'Available' THEN 1 ELSE 0 END) AS unavailable_trucks
        FROM Trucks;
    ";
    $resultTrucksMetrics = $conn->query($queryTrucksMetrics);
    $trucksData = $resultTrucksMetrics->fetch_assoc();
    $totalTrucks = $trucksData['available_trucks'] + $trucksData['unavailable_trucks'];

    // --- 3. Deliveries: In Progress vs. Not In Progress (Based on deliveryStatus) ---
    $queryDeliveries = "
        SELECT
            SUM(CASE WHEN deliveryStatus = 'In Progress' THEN 1 ELSE 0 END) AS deliveries_in_progress,
            SUM(CASE WHEN deliveryStatus != 'In Progress' THEN 1 ELSE 0 END) AS deliveries_not_in_progress
        FROM Deliveries;
    ";
    $resultDeliveries = $conn->query($queryDeliveries);
    $deliveriesData = $resultDeliveries->fetch_assoc();
    $totalDeliveries = $deliveriesData['deliveries_in_progress'] + $deliveriesData['deliveries_not_in_progress'];


    // --- Calculate Percentages (Prevent division by zero) ---
    $drivers_avail_percent = $totalDrivers > 0 ? round(($driversData['available_drivers'] / $totalDrivers) * 100) : 0;
    $drivers_unavail_percent = 100 - $drivers_avail_percent;

    $trucks_avail_percent = $totalTrucks > 0 ? round(($trucksData['available_trucks'] / $totalTrucks) * 100) : 0;
    $trucks_unavail_percent = 100 - $trucks_avail_percent;

    $deliveries_in_percent = $totalDeliveries > 0 ? round(($deliveriesData['deliveries_in_progress'] / $totalDeliveries) * 100) : 0;
    $deliveries_not_in_percent = 100 - $deliveries_in_percent;
}

// =========================================================
//                  DRIVERS LIST FETCHING (Drivers View)
// =========================================================
$driversList = [];
if ($view === 'drivers') {
    // 1. Fetch All Drivers with their assigned truck info (if any)
    $queryDriversList = "
        SELECT 
            A.accountID, 
            A.firstName, 
            A.lastName, 
            A.username, 
            A.email,
            T.truckName,
            T.truckID
        FROM Accounts A
        LEFT JOIN Trucks T ON A.accountID = T.assignedDriver
        WHERE A.accountType = 'Driver'
        ORDER BY A.lastName ASC
    ";
    $resultDriversList = $conn->query($queryDriversList);
    if ($resultDriversList) {
        while($row = $resultDriversList->fetch_assoc()) {
            $driversList[] = $row;
        }
    }
}


// =========================================================
//                  TRUCKS LIST & DRIVER LIST FETCHING (Trucks View)
// =========================================================
$trucksList = [];
$availableDrivers = [];
$allDrivers = []; // Need all drivers for the edit modal
if ($view === 'trucks') {
    // 1. Fetch Trucks List
    $queryTrucksList = "
        SELECT 
            T.*, 
            A.firstName, 
            A.lastName
        FROM Trucks T
        LEFT JOIN Accounts A ON T.assignedDriver = A.accountID
        ORDER BY T.truckStatus DESC, T.truckID ASC
    ";
    $resultTrucksList = $conn->query($queryTrucksList);
    if ($resultTrucksList) {
        while($row = $resultTrucksList->fetch_assoc()) {
            $trucksList[] = $row;
        }
    }
    
    // 2. Fetch Available Drivers (not currently assigned a truck)
    $queryDrivers = "
        SELECT 
            A.accountID, 
            A.firstName, 
            A.lastName
        FROM Accounts A
        LEFT JOIN Trucks T ON A.accountID = T.assignedDriver
        WHERE A.accountType = 'Driver' AND T.assignedDriver IS NULL
        ORDER BY A.firstName ASC
    ";
    $resultDrivers = $conn->query($queryDrivers);
    if ($resultDrivers) {
        while($row = $resultDrivers->fetch_assoc()) {
            $availableDrivers[] = $row;
        }
    }
    
    // 3. Fetch All Drivers (for edit modal driver dropdown)
    $queryAllDrivers = "
        SELECT 
            A.accountID, 
            A.firstName, 
            A.lastName
        FROM Accounts A
        WHERE A.accountType = 'Driver'
        ORDER BY A.firstName ASC
    ";
    $resultAllDrivers = $conn->query($queryAllDrivers);
    if ($resultAllDrivers) {
        while($row = $resultAllDrivers->fetch_assoc()) {
            $allDrivers[] = $row;
        }
    }
}

// =========================================================
//                  HTML & VIEW RENDERING
// =========================================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>adminpage.css"> 
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <title>Admin Dashboard</title>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h1 class="logo">FleetNav</h1>
            <div class="nav">
                <button class="nav-btn <?php echo $view === 'dashboard' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=dashboard'">Dashboard</button>
                <button class="nav-btn <?php echo $view === 'drivers' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=drivers'">Drivers</button>
                <button class="nav-btn <?php echo $view === 'trucks' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=trucks'">Trucks</button>
                <button class="nav-btn <?php echo $view === 'deliveries' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=deliveries'">Deliveries</button>
                <button class="nav-btn <?php echo $view === 'accounts' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=accounts'">Accounts</button>
                <button class="nav-btn <?php echo $view === 'history' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=history'">History Reports</button>
                <button class="nav-btn <?php echo $view === 'logs' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=logs'">Logs</button>
            </div>
        </div>

        <div class="content-wrapper">
            
            <header class="top-nav-bar">
                <h2 class="page-title"><?php echo strtoupper($view); ?> MANAGEMENT</h2>
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($firstName); ?> (<?php echo $accountType; ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php';">Logout</button>
                </div>
            </header>

            <div class="main-content-area">
                
                <?php echo $message; // Display success/error messages ?>

                <?php if ($view === 'dashboard'): ?>
                    <section id="dashboard-charts">
                        
                        <div class="chart-widget">
                            <h3>Driver Availability (Total: <?php echo $totalDrivers; ?>)</h3>
                            <div class="chart-container">
                                <canvas id="driverChart"></canvas>
                            </div>
                            <div class="chart-legend">
                                <span>Available: **<?php echo $driversData['available_drivers']; ?>** (<?php echo $drivers_avail_percent; ?>%)</span>
                                <span>Unavailable: **<?php echo $driversData['unavailable_drivers']; ?>** (<?php echo $drivers_unavail_percent; ?>%)</span>
                            </div>
                        </div>

                        <div class="chart-widget">
                            <h3>Truck Availability (Total: <?php echo $totalTrucks; ?>)</h3>
                            <div class="chart-container">
                                <canvas id="truckChart"></canvas>
                            </div>
                            <div class="chart-legend">
                                <span>Available: **<?php echo $trucksData['available_trucks']; ?>** (<?php echo $trucks_avail_percent; ?>%)</span>
                                <span>Unavailable: **<?php echo $trucksData['unavailable_trucks']; ?>** (<?php echo $trucks_unavail_percent; ?>%)</span>
                            </div>
                        </div>

                        <div class="chart-widget">
                            <h3>Delivery Status (Total: <?php echo $totalDeliveries; ?>)</h3>
                            <div class="chart-container">
                                <canvas id="deliveryChart"></canvas>
                            </div>
                            <div class="chart-legend">
                                <span>In Progress: **<?php echo $deliveriesData['deliveries_in_progress']; ?>** (<?php echo $deliveries_in_percent; ?>%)</span>
                                <span>Not In Progress: **<?php echo $deliveriesData['deliveries_not_in_progress']; ?>** (<?php echo $deliveries_not_in_percent; ?>%)</span>
                            </div>
                        </div>
                    </section>
                
                <?php elseif ($view === 'drivers'): ?>
                    <section id="drivers-list">
                        <div class="section-header">
                            <h1>Fleet Drivers</h1>
                            <button class="action-btn" onclick="document.getElementById('addDriverModal').style.display='block';">➕ Add Driver</button>
                        </div>
                        
                        <?php if (empty($driversList)): ?>
                            <div class="empty-state">
                                <p>No drivers registered in the fleet yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="driver-grid">
                                <?php foreach ($driversList as $driver): ?>
                                    <?php
                                        $isAssigned = $driver['truckID'] !== null;
                                        $statusClass = $isAssigned ? 'status-unavailable' : 'status-available';
                                        $statusText = $isAssigned ? 'Assigned' : 'Available';
                                        $assignedTruck = $driver['truckName'] ? htmlspecialchars($driver['truckName']) . ' (ID: ' . $driver['truckID'] . ')' : '<span class="text-unassigned">None</span>';
                                    ?>
                                    <div class="driver-card" 
                                         data-accountid="<?php echo htmlspecialchars($driver['accountID']); ?>" 
                                         data-firstname="<?php echo htmlspecialchars($driver['firstName']); ?>"
                                         data-lastname="<?php echo htmlspecialchars($driver['lastName']); ?>"
                                         data-username="<?php echo htmlspecialchars($driver['username']); ?>"
                                         data-email="<?php echo htmlspecialchars($driver['email']); ?>">
                                        
                                        <div class="driver-status-header">
                                            <h3 class="driver-name"><?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?></h3>
                                            <span class="status-badge <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                        </div>

                                        <div class="driver-details">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($driver['accountID']); ?></p>
                                            <p><strong>Username:</strong> <?php echo htmlspecialchars($driver['username']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($driver['email']); ?></p>
                                            <p><strong>Assigned Truck:</strong> <?php echo $assignedTruck; ?></p>
                                        </div>

                                        <div class="driver-actions">
                                            <button class="icon-btn edit-btn" title="Edit Driver" 
                                                    onclick="openEditDriverModal(this)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="icon-btn delete-btn" title="Delete Driver"
                                                    onclick="confirmDeleteDriver(<?php echo htmlspecialchars($driver['accountID']); ?>, '<?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?>', <?php echo $isAssigned ? 'true' : 'false'; ?>)">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                
                <?php elseif ($view === 'trucks'): ?>
                    <section id="trucks-list">
                        <div class="section-header">
                            <h1>Fleet Trucks</h1>
                            <button class="action-btn" onclick="document.getElementById('addTruckModal').style.display='block';">➕ Add Truck</button>
                        </div>
                        
                        <?php if (empty($trucksList)): ?>
                            <div class="empty-state">
                                <p>No trucks registered in the fleet yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="truck-grid">
                                <?php foreach ($trucksList as $truck): ?>
                                    <div class="truck-card" data-truckid="<?php echo htmlspecialchars($truck['truckID']); ?>" 
                                         data-truckname="<?php echo htmlspecialchars($truck['truckName']); ?>"
                                         data-plate="<?php echo htmlspecialchars($truck['plateNumber']); ?>"
                                         data-status="<?php echo htmlspecialchars($truck['truckStatus']); ?>"
                                         data-odometer="<?php echo htmlspecialchars($truck['odometerOrMileage']); ?>"
                                         data-regdate="<?php echo htmlspecialchars($truck['registrationDate']); ?>"
                                         data-driverid="<?php echo htmlspecialchars($truck['assignedDriver'] ?? 0); ?>"
                                         data-drivername="<?php echo htmlspecialchars($truck['firstName'] ? $truck['firstName'] . ' ' . $truck['lastName'] : 'Unassigned'); ?>"
                                         data-img="<?php echo htmlspecialchars($truck['truckImg'] ?? 'null'); ?>">
                                        
                                        <div class="truck-image-container">
                                            <?php 
                                                // Assuming BASE_URL is defined and the assets folder contains a default image
                                                $imgPath = $truck['truckImg'] ? BASE_URL . "uploads/" . $truck['truckImg'] : BASE_URL . "assets/default-truck.png";
                                            ?>
                                            <img src="<?php echo $imgPath; ?>" alt="Image of <?php echo htmlspecialchars($truck['truckName']); ?>">
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $truck['truckStatus'])); ?>">
                                                <?php echo htmlspecialchars($truck['truckStatus']); ?>
                                            </span>
                                        </div>

                                        <div class="truck-details">
                                            <h3 class="truck-name"><?php echo htmlspecialchars($truck['truckName']); ?> (ID: <?php echo htmlspecialchars($truck['truckID']); ?>)</h3>
                                            <p><strong>Plate:</strong> <?php echo htmlspecialchars($truck['plateNumber']); ?></p>
                                            <p><strong>Mileage:</strong> <?php echo number_format($truck['odometerOrMileage']); ?> mi</p>
                                            <p><strong>Driver:</strong> 
                                                <?php 
                                                    if ($truck['assignedDriver']) {
                                                        echo htmlspecialchars($truck['firstName'] . ' ' . $truck['lastName']);
                                                    } else {
                                                        echo '<span class="text-unassigned">Unassigned</span>';
                                                    }
                                                ?>
                                            </p>
                                        </div>

                                        <div class="truck-actions">
                                            <button class="icon-btn edit-btn" title="Edit Truck" 
                                                    onclick="openEditTruckModal(this)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="icon-btn delete-btn" title="Delete Truck"
                                                    onclick="confirmDelete(<?php echo htmlspecialchars($truck['truckID']); ?>, '<?php echo htmlspecialchars($truck['truckName']); ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                
                <?php else: ?>
                    <section id="placeholder-content">
                        <h1><?php echo strtoupper($view); ?> MANAGEMENT</h1>
                        <p>This section is currently under development.</p>
                    </section>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    
    <?php if ($view === 'drivers'): ?>
        <div id="addDriverModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="document.getElementById('addDriverModal').style.display='none';">&times;</span>
                <h2>Add New Driver</h2>
                
                <form class="data-form" id="addDriverForm" method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="firstName">First Name *</label>
                            <input type="text" id="firstName" name="firstName" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name *</label>
                            <input type="text" id="lastName" name="lastName" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" id="username" name="username" required>
                    </div>

                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <input type="password" id="password" name="password" required>
                        <small class="form-hint">A strong password is required for security.</small>
                    </div>
                    
                    <button type="submit" name="add_driver_submit" class="submit-btn">ADD DRIVER</button>
                </form>
            </div>
        </div>

        <div id="editDriverModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="document.getElementById('editDriverModal').style.display='none';">&times;</span>
                <h2>Edit Driver Details (ID: <span id="editDriverIDDisplay"></span>)</h2>
                
                <form class="data-form" id="editDriverForm" method="POST">
                    
                    <input type="hidden" name="editAccountID" id="editAccountID" value="">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editFirstName">First Name *</label>
                            <input type="text" id="editFirstName" name="editFirstName" required>
                        </div>
                        <div class="form-group">
                            <label for="editLastName">Last Name *</label>
                            <input type="text" id="editLastName" name="editLastName" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editUsername">Username *</label>
                        <input type="text" id="editUsername" name="editUsername" required>
                    </div>

                    <div class="form-group">
                        <label for="editEmail">Email *</label>
                        <input type="email" id="editEmail" name="editEmail" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="editPassword">New Password (Leave blank to keep current)</label>
                        <input type="password" id="editPassword" name="editPassword">
                        <small class="form-hint">Only fill this if you need to change the password.</small>
                    </div>
                    
                    <button type="submit" name="edit_driver_submit" class="submit-btn green-btn">SAVE CHANGES</button>
                </form>
            </div>
        </div>
    <?php endif; ?>


    <?php if ($view === 'trucks'): ?>
        <div id="addTruckModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="document.getElementById('addTruckModal').style.display='none';">&times;</span>
                <h2>Add New Truck</h2>
                
                <form class="data-form" id="addTruckForm" method="POST" enctype="multipart/form-data">
                    
                    <input type="hidden" name="uploadedImagePath" id="uploadedImagePath" value="">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="truckName">Truck Name/Model *</label>
                            <input type="text" id="truckName" name="truckName" required>
                        </div>
                        <div class="form-group">
                            <label for="plateNumber">Plate Number *</label>
                            <input type="text" id="plateNumber" name="plateNumber" required>
                        </div>
                    </div>

                    <div class="form-row">
                         <div class="form-group">
                            <label for="odometerOrMileage">Odometer/Mileage *</label>
                            <input type="number" id="odometerOrMileage" name="odometerOrMileage" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="registrationDate">Registration Date *</label>
                            <input type="date" id="registrationDate" name="registrationDate" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="truckStatus">Truck Status *</label>
                            <select id="truckStatus" name="truckStatus" required>
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                                <option value="In Transit">In Transit</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="assignedDriver">Assigned Driver</label>
                            <select id="assignedDriver" name="assignedDriver">
                                <option value="">--- Unassigned ---</option>
                                <?php foreach ($availableDrivers as $driver): ?>
                                    <option value="<?php echo htmlspecialchars($driver['accountID']); ?>">
                                        <?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Only unassigned drivers are shown.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="truckImage">Truck Image</label>

                        <div class="upload-container">
                            <div id="dropArea" class="drop-area">
                                <p>Drag & Drop your image here or <span id="selectFileLink">select a file</span></p>
                            </div>
    
                            <input type="file" id="truckImage" name="truckImage" accept="image/*" style="display: none;">

                                <div id="previewContainer" class="preview-container" style="display: none;">
                                    <img id="previewImage" src="" alt="Image Preview">
                                <button type="button" id="clearImageBtn" class="clear-image-btn">Clear Image</button>
                                </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="add_truck_submit" class="submit-btn">ADD TRUCK</button>
                </form>
            </div>
        </div>
        
        <div id="editTruckModal" class="modal">
            <div class="modal-content">
                <span class="close-btn" onclick="document.getElementById('editTruckModal').style.display='none';">&times;</span>
                <h2>Edit Truck Details (ID: <span id="editTruckIDDisplay"></span>)</h2>
                
                <form class="data-form" id="editTruckForm" method="POST" enctype="multipart/form-data">
                    
                    <input type="hidden" name="editTruckID" id="editTruckID" value="">
                    <input type="hidden" name="editCurrentTruckImg" id="editCurrentTruckImg" value="">
                    <input type="hidden" name="editUploadedImagePath" id="editUploadedImagePath" value="">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="editTruckName">Truck Name/Model *</label>
                            <input type="text" id="editTruckName" name="editTruckName" required>
                        </div>
                        <div class="form-group">
                            <label for="editPlateNumber">Plate Number *</label>
                            <input type="text" id="editPlateNumber" name="editPlateNumber" required>
                        </div>
                    </div>

                    <div class="form-row">
                         <div class="form-group">
                            <label for="editOdometerOrMileage">Odometer/Mileage *</label>
                            <input type="number" id="editOdometerOrMileage" name="editOdometerOrMileage" min="0" required>
                        </div>
                        <div class="form-group">
                            <label for="editRegistrationDate">Registration Date *</label>
                            <input type="date" id="editRegistrationDate" name="editRegistrationDate" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="editTruckStatus">Truck Status *</label>
                            <select id="editTruckStatus" name="editTruckStatus" required>
                                <option value="Available">Available</option>
                                <option value="Unavailable">Unavailable</option>
                                <option value="In Transit">In Transit</option>
                                <option value="Maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="editAssignedDriver">Assigned Driver</label>
                            <select id="editAssignedDriver" name="editAssignedDriver">
                                <option value="0">--- Unassigned ---</option>
                                <?php foreach ($allDrivers as $driver): ?>
                                    <option value="<?php echo htmlspecialchars($driver['accountID']); ?>">
                                        <?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="form-hint">Select a driver or choose 'Unassigned'.</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="editTruckImage">Truck Image (Optional)</label>

                        <div class="upload-container">
                            <div id="editDropArea" class="drop-area">
                                <p>Drag & Drop a new image or <span id="editSelectFileLink">select a file</span></p>
                            </div>
    
                            <input type="file" id="editTruckImage" name="editTruckImage" accept="image/*" style="display: none;">

                                <div id="editPreviewContainer" class="preview-container" style="display: none;">
                                    <img id="editPreviewImage" src="" alt="Image Preview">
                                    <p id="editCurrentPathText">Current Image: <span id="editCurrentPathName"></span></p>
                                    <button type="button" id="editClearImageBtn" class="clear-image-btn">Remove/Change Image</button>
                                </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="edit_truck_submit" class="submit-btn green-btn">SAVE CHANGES</button>
                </form>
            </div>
        </div>
    <?php endif; ?>


    <?php if ($view === 'dashboard'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    function createPieChart(chartId, label1, data1, label2, data2, color1, color2) {
        // Only show a chart if there is data
        const totalData = data1 + data2;
        if (totalData === 0) {
            const container = document.getElementById(chartId).parentNode;
            container.innerHTML = '<p style="text-align:center; padding-top: 50px; color:#555;">No data available.</p>';
            return;
        }

        const ctx = document.getElementById(chartId).getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: [label1, label2],
                datasets: [{
                    data: [data1, data2],
                    backgroundColor: [color1, color2],
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed + '%'; 
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // PHP Data translated to JavaScript (using percentages)
    const driverData = { available: <?php echo $drivers_avail_percent; ?>, unavailable: <?php echo $drivers_unavail_percent; ?> };
    const truckData = { available: <?php echo $trucks_avail_percent; ?>, unavailable: <?php echo $trucks_unavail_percent; ?> };
    const deliveryData = { inProgress: <?php echo $deliveries_in_percent; ?>, notInProgress: <?php echo $deliveries_not_in_percent; ?> };

    const PRIMARY_BLUE = '#007bff';
    const LIGHT_GREY = '#ced4da';

    // Initialize Charts
    createPieChart('driverChart', 'Available', driverData.available, 'Unavailable', driverData.unavailable, PRIMARY_BLUE, LIGHT_GREY);
    createPieChart('truckChart', 'Available', truckData.available, 'Unavailable', truckData.unavailable, PRIMARY_BLUE, LIGHT_GREY);
    createPieChart('deliveryChart', 'In Progress', deliveryData.inProgress, 'Not In Progress', deliveryData.notInProgress, PRIMARY_BLUE, LIGHT_GREY);

    </script>
    <?php endif; ?>
    
    <script>
        const modal = document.getElementById('addTruckModal');
        const editModal = document.getElementById('editTruckModal');
        
        if (modal) {
            window.onclick = function(event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
                if (editModal && event.target === editModal) {
                    editModal.style.display = "none";
                }
                // NEW: Driver Modals
                const addDriverModal = document.getElementById('addDriverModal');
                const editDriverModal = document.getElementById('editDriverModal');
                if (addDriverModal && event.target === addDriverModal) {
                    addDriverModal.style.display = "none";
                }
                if (editDriverModal && event.target === editDriverModal) {
                    editDriverModal.style.display = "none";
                }
            }
        }
    </script>

    <script>
        // Use BASE_URL from connect.php (assumed to be included)
        const BASE_URL = '<?php echo BASE_URL; ?>';
        const UPLOADS_PATH = BASE_URL + 'uploads/';
        // NOTE: You must create an 'assets' folder and place a default-truck.png image in it for this path to work
        const DEFAULT_IMG_PATH = BASE_URL + 'assets/default-truck.png'; 

        // --- Shared Helper Functions ---
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        // --- ADD TRUCK MODAL (Existing Logic Refined) ---
        const dropArea = document.getElementById('dropArea');
        const selectFileLink = document.getElementById('selectFileLink');
        const fileInput = document.getElementById('truckImage');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const clearImageBtn = document.getElementById('clearImageBtn');
        const uploadedImagePathInput = document.getElementById('uploadedImagePath'); 

        function showPreview(file) {
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                    dropArea.style.display = 'none';
                    previewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
                uploadFile(file);
            }
        }

        function clearSelection() {
            fileInput.value = ''; 
            previewImage.src = '';
            previewContainer.style.display = 'none';
            dropArea.style.display = 'block';
            uploadedImagePathInput.value = ''; 
        }

        function uploadFile(file) {
            const formData = new FormData();
            formData.append('image', file);
            uploadedImagePathInput.value = 'UPLOADING...';
            
            fetch('upload.php', { method: 'POST', body: formData })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    uploadedImagePathInput.value = data.file; 
                } else {
                    alert('Image upload failed: ' + data.message);
                    clearSelection(); 
                }
            })
            .catch(error => {
                console.error('Error during upload:', error);
                alert('An error occurred during file upload. Check console for details.');
                clearSelection();
            });
        }

        if (selectFileLink) selectFileLink.addEventListener('click', () => { fileInput.click(); });
        if (fileInput) fileInput.addEventListener('change', (e) => { const file = e.target.files[0]; showPreview(file); });
        if (clearImageBtn) clearImageBtn.addEventListener('click', clearSelection);
        
        if (dropArea) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, preventDefaults, false);
            });
            ['dragenter', 'dragover'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => { dropArea.classList.add('dragover'); }, false);
            });
            ['dragleave', 'drop'].forEach(eventName => {
                dropArea.addEventListener(eventName, () => { dropArea.classList.remove('dragover'); }, false);
            });
            dropArea.addEventListener('drop', (e) => {
                let dt = e.dataTransfer;
                let files = dt.files;
                if (files.length) {
                    fileInput.files = files;
                    showPreview(files[0]);
                }
            }, false);
        }

        // --- TRUCK DELETION FUNCTION (New) ---
        function confirmDelete(id, name) {
            if (confirm(`Are you sure you want to delete the truck "${name}" (ID: ${id})? This action cannot be undone and may fail if the truck is linked to active deliveries.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'AdminPage.php?view=trucks';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_truck_id';
                input.value = id;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // --- EDIT TRUCK MODAL (New) ---
        
        const editTruckIDInput = document.getElementById('editTruckID');
        const editTruckIDDisplay = document.getElementById('editTruckIDDisplay');
        const editCurrentTruckImgInput = document.getElementById('editCurrentTruckImg');
        const editUploadedImagePathInput = document.getElementById('editUploadedImagePath');
        
        const editDropArea = document.getElementById('editDropArea');
        const editSelectFileLink = document.getElementById('editSelectFileLink');
        const editFileInput = document.getElementById('editTruckImage');
        const editPreviewContainer = document.getElementById('editPreviewContainer');
        const editPreviewImage = document.getElementById('editPreviewImage');
        const editClearImageBtn = document.getElementById('editClearImageBtn');
        const editCurrentPathName = document.getElementById('editCurrentPathName');


        function openEditTruckModal(buttonElement) {
            const card = buttonElement.closest('.truck-card');
            
            // Populate form fields
            editTruckIDInput.value = card.dataset.truckid;
            editTruckIDDisplay.textContent = card.dataset.truckid;
            document.getElementById('editTruckName').value = card.dataset.truckname;
            document.getElementById('editPlateNumber').value = card.dataset.plate;
            document.getElementById('editTruckStatus').value = card.dataset.status;
            document.getElementById('editOdometerOrMileage').value = card.dataset.odometer;
            document.getElementById('editRegistrationDate').value = card.dataset.regdate;
            document.getElementById('editAssignedDriver').value = card.dataset.driverid === '0' ? '0' : card.dataset.driverid;
            
            // Image handling setup
            const currentImgPath = card.dataset.img;
            editUploadedImagePathInput.value = ''; // Clear new upload path
            editFileInput.value = ''; // Clear file input
            editCurrentTruckImgInput.value = currentImgPath; // Set current path

            if (currentImgPath && currentImgPath !== 'null') {
                editPreviewImage.src = UPLOADS_PATH + currentImgPath;
                editCurrentPathName.textContent = currentImgPath;
                editDropArea.style.display = 'none';
            } else {
                editPreviewImage.src = DEFAULT_IMG_PATH; 
                editCurrentPathName.textContent = 'None';
                editDropArea.style.display = 'block'; // Show drop area if no image
            }
            
            editPreviewContainer.style.display = 'block';
            document.getElementById('editTruckModal').style.display = 'block';
        }
        
        function editShowPreview(file) {
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    editPreviewImage.src = e.target.result;
                    editCurrentPathName.textContent = 'New File Selected (Uploading...)';
                    editDropArea.style.display = 'none';
                    editPreviewContainer.style.display = 'block';
                };
                reader.readAsDataURL(file);
                editUploadFile(file);
            }
        }
        
        function editClearSelection() {
            editFileInput.value = ''; 
            editPreviewImage.src = DEFAULT_IMG_PATH;
            editCurrentPathName.textContent = 'None';
            editUploadedImagePathInput.value = 'null'; // Signal to PHP to remove the image
            editCurrentTruckImgInput.value = 'null'; 
            editDropArea.style.display = 'block';
        }
        
        function editUploadFile(file) {
            const formData = new FormData();
            formData.append('image', file);
            editUploadedImagePathInput.value = 'UPLOADING...';
            
            fetch('upload.php', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    editUploadedImagePathInput.value = data.file; 
                    editCurrentPathName.textContent = `New File: ${data.file}`;
                } else {
                    alert('Image upload failed: ' + data.message);
                    editClearSelection(); 
                }
            })
            .catch(error => {
                console.error('Error during upload:', error);
                alert('An error occurred during file upload. Check console for details.');
                editClearSelection();
            });
        }
        
        if (editModal) {
            if(editSelectFileLink) editSelectFileLink.addEventListener('click', () => { editFileInput.click(); });
            if(editFileInput) editFileInput.addEventListener('change', (e) => { const file = e.target.files[0]; editShowPreview(file); });
            if(editClearImageBtn) editClearImageBtn.addEventListener('click', editClearSelection);

            if (editDropArea) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    editDropArea.addEventListener(eventName, preventDefaults, false);
                });
                ['dragenter', 'dragover'].forEach(eventName => {
                    editDropArea.addEventListener(eventName, () => { editDropArea.classList.add('dragover'); }, false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    editDropArea.addEventListener(eventName, () => { editDropArea.classList.remove('dragover'); }, false);
                });
                editDropArea.addEventListener('drop', (e) => {
                    let dt = e.dataTransfer;
                    let files = dt.files;
                    if (files.length) {
                        editFileInput.files = files;
                        editShowPreview(files[0]);
                    }
                }, false);
            }
        }

        // --- DRIVER MODAL & CRUD JS (NEW) ---

        // --- DRIVER DELETION FUNCTION (New) ---
        function confirmDeleteDriver(id, name, isAssigned) {
            // Reusing the check logic from PHP to provide client-side feedback
            if (isAssigned) {
                 alert(`Cannot delete ${name} (ID: ${id}). The driver is currently assigned to a truck or an In Progress delivery. Please resolve the assignments first.`);
                 return;
            }
            if (confirm(`Are you sure you want to delete the driver "${name}" (ID: ${id})? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'AdminPage.php?view=drivers';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_driver_id';
                input.value = id;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // --- EDIT DRIVER MODAL (New) ---
        function openEditDriverModal(buttonElement) {
            const card = buttonElement.closest('.driver-card');
            
            // Populate form fields
            document.getElementById('editAccountID').value = card.dataset.accountid;
            document.getElementById('editDriverIDDisplay').textContent = card.dataset.accountid;
            document.getElementById('editFirstName').value = card.dataset.firstname;
            document.getElementById('editLastName').value = card.dataset.lastname;
            document.getElementById('editUsername').value = card.dataset.username;
            document.getElementById('editEmail').value = card.dataset.email;
            document.getElementById('editPassword').value = ''; // Always clear password field on open (for security)
            
            document.getElementById('editDriverModal').style.display = 'block';
        }

    </script>
</body>
</html>