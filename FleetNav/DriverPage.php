<?php
include("connect.php");

// Security Check: Only allow 'Driver' users
if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Driver') {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// --- USER VARIABLES ---
$firstName = $_SESSION['firstName'];
$lastName = $_SESSION['lastName'];
$fullName = $firstName . ' ' . $lastName;
$profileFilename = $_SESSION['profileImg'] ?? null;
$accountType = htmlspecialchars($_SESSION['accountType']);
$driverID = $_SESSION['accountID'];

// Profile Image Logic
$profileImgSrc = ""; // Initialize variable

if (!empty($profileFilename)) {
    // CHECK: Does the database value already include "uploads/"?
    if (strpos($profileFilename, 'uploads/') === 0) {
        // Yes: The DB has "uploads/image.png", so we just add BASE_URL
        $profileImgSrc = BASE_URL . $profileFilename;
    } else {
        // No: The DB has "image.png", so we must add "uploads/" manually
        $profileImgSrc = BASE_URL . "uploads/" . $profileFilename;
    }
} else {
    // Fallback if no image is set
    $profileImgSrc = BASE_URL . "blank-profile-picture-973460_960_720-587709513.png";
}
// Determine View
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$message = ''; 

// =========================================================
//           GLOBAL DATA FETCH (Used in both views)
// =========================================================
// 1. Get Assigned Truck
$assignedTruck = null;
$truckQuery = $conn->prepare("SELECT * FROM Trucks WHERE assignedDriver = ?");
$truckQuery->bind_param("i", $driverID);
$truckQuery->execute();
$truckResult = $truckQuery->get_result();
if ($row = $truckResult->fetch_assoc()) {
    $assignedTruck = $row;
}
$truckQuery->close();

// 2. Get Active Delivery for that Truck
$activeDelivery = null;
if ($assignedTruck) {
    $truckID = $assignedTruck['truckID'];
    // Fetch delivery that is NOT completed or cancelled
    $delQuery = $conn->prepare("
        SELECT * FROM Deliveries 
        WHERE assignedTruck = ? 
        AND deliveryStatus IN ('Inactive', 'In Progress', 'On Route')
        LIMIT 1
    ");
    $delQuery->bind_param("i", $truckID);
    $delQuery->execute();
    $delResult = $delQuery->get_result();
    if ($row = $delResult->fetch_assoc()) {
        $activeDelivery = $row;
    }
    $delQuery->close();
}

// Handle POST requests from included files (like Start Delivery)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
    if ($_POST['action_type'] === 'start_delivery' && $activeDelivery) {
        $delID = $activeDelivery['deliveryID'];
        $newStatus = 'In Progress'; // Or 'On Route' depending on your preference
        
        $updateStmt = $conn->prepare("UPDATE Deliveries SET deliveryStatus = ? WHERE deliveryID = ?");
        $updateStmt->bind_param("si", $newStatus, $delID);
        if ($updateStmt->execute()) {
            // Update Truck Status to 'In Transit'
            $truckUpdate = $conn->prepare("UPDATE Trucks SET truckStatus = 'In Transit' WHERE truckID = ?");
            $truckUpdate->bind_param("i", $truckID);
            $truckUpdate->execute();
            
            // Log it
            $logStmt = $conn->prepare("INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, 'START_DELIVERY', ?)");
            $logDetails = "Driver started delivery ID: $delID";
            $logStmt->bind_param("is", $driverID, $logDetails);
            $logStmt->execute();

            // Refresh Page to show new status
            header("Location: DriverPage.php?view=delivery");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>driverpage.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Driver Dashboard</title>
</head>
<body>
    <div class="menu-overlay" id="menuOverlay"></div>

    <div class="container">
        <div class="sidebar" id="sidebarMenu">
            <div class="sidebar-header">
                <h1 class="logo">FleetNav</h1>
                <button class="close-menu-btn" id="closeMenuBtn"><i class="fas fa-times"></i></button>
            </div>
            
            <div class="nav">
                <button class="nav-btn <?php echo $view === 'dashboard' ? 'active' : ''; ?>" onclick="window.location.href='DriverPage.php?view=dashboard'">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </button>
                <button class="nav-btn <?php echo $view === 'delivery' ? 'active' : ''; ?>" onclick="window.location.href='DriverPage.php?view=delivery'">
                    <i class="fas fa-truck"></i> My Delivery
                </button>
            </div>
        </div>

        <div class="content-wrapper">
            <header class="top-nav-bar">
                <div class="header-left">
                    <button class="menu-toggle-btn" id="menuToggleBtn">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h2 class="page-title"><?php echo strtoupper($view === 'delivery' ? 'My Delivery' : 'Dashboard'); ?></h2>
                </div>
                
                <div class="user-profile-section">
                    <div class="profile-info">
                        <span class="user-name"><?php echo htmlspecialchars($fullName); ?></span>
                        <span class="user-role">Driver Account</span>
                    </div>
                    <a href="EditProfile.php" class="profile-link">
                        <img src="<?php echo $profileImgSrc; ?>" alt="Profile" class="profile-icon">
                    </a>
                    <div class="logout-wrapper">
                         <button class="logout-btn-icon" onclick="window.location.href='logout.php';" title="Logout">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </div>
                </div>
            </header>

            <div class="main-content-area">
                <?php 
                if ($view === 'dashboard') {
                    include 'DriverPageDashboard.php';
                } elseif ($view === 'delivery') {
                    include 'DriverPageDelivery.php';
                }
                ?>
            </div>
        </div>
    </div>
    <script>
        const menuToggleBtn = document.getElementById('menuToggleBtn');
        const closeMenuBtn = document.getElementById('closeMenuBtn');
        const sidebarMenu = document.getElementById('sidebarMenu');
        const menuOverlay = document.getElementById('menuOverlay');

        function openMenu() {
            sidebarMenu.classList.add('active');
            menuOverlay.classList.add('active');
        }

        function closeMenu() {
            sidebarMenu.classList.remove('active');
            menuOverlay.classList.remove('active');
        }

        if(menuToggleBtn) menuToggleBtn.addEventListener('click', openMenu);
        if(closeMenuBtn) closeMenuBtn.addEventListener('click', closeMenu);
        if(menuOverlay) menuOverlay.addEventListener('click', closeMenu);
    </script>
</body>
</html>