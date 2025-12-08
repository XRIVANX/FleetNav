<?php
include("connect.php");

// Determine View (Must be defined before includes so they can check $view)
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$message = ''; 

// =========================================================
//           OPTIMIZED MODULE LOADING (Logic & Actions)
// =========================================================
// We use a tracking variable to ensure we don't include the same file twice
// if an action and a view require the same file.
$loadedModule = '';

// 1. Check for Form Submissions (POST Actions)
// We must include the relevant file to process the action, regardless of the current view.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_driver_submit']) || isset($_POST['edit_driver_submit']) || isset($_POST['delete_user_id'])) {
        include_once('AdminPageUser.php');
        $loadedModule = 'user';
    }
    elseif (isset($_POST['add_truck_submit']) || isset($_POST['edit_truck_submit']) || isset($_POST['delete_truck_id'])) {
        include_once('AdminPageTruck.php');
        $loadedModule = 'truck';
    }
    // Check for all delivery-related POST triggers
    elseif (isset($_POST['delivery_id']) || isset($_POST['add_delivery_submit']) || isset($_POST['edit_delivery_submit']) || isset($_POST['delete_delivery_id'])) {
        include_once('AdminPageDelivery.php');
        $loadedModule = 'delivery';
    }
    elseif (isset($_POST['update_gas_submit'])) {
        include_once('AdminPageHistory.php');
        $loadedModule = 'history';
    }
}

// 2. Load View Data (Only if not already loaded by an action)
// This prevents loading heavy dashboard queries when viewing the truck list, for example.
switch ($view) {
    case 'drivers':
    case 'accounts':
        if ($loadedModule !== 'user') include_once('AdminPageUser.php');
        break;
    case 'trucks':
        if ($loadedModule !== 'truck') include_once('AdminPageTruck.php');
        break;
    case 'deliveries':
        if ($loadedModule !== 'delivery') include_once('AdminPageDelivery.php');
        break;
    case 'history_reports':
        if ($loadedModule !== 'history') include_once('AdminPageHistory.php');
        break;
    case 'dashboard':
        // Only load dashboard metrics if we are actually on the dashboard
        include_once('AdminPageDashboard.php');
        break;
    // 'logs' view is handled internally in this file, so no include needed.
}


// Security Check: Only allow 'Admin' and 'Super Admin' users
if (!isset($_SESSION['accountID']) || ($_SESSION['accountType'] !== 'Admin' && $_SESSION['accountType'] !== 'Super Admin')) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

// --- USER VARIABLES ---
$firstName = $_SESSION['firstName'];
$lastName = $_SESSION['lastName'];
$fullName = $firstName . ' ' . $lastName;
$profileFilename = $_SESSION['profileImg'] ?? null;
$accountType = htmlspecialchars($_SESSION['accountType']);
$adminID = $_SESSION['accountID'];
$isSuperAdmin = ($accountType === 'Super Admin');

// Profile Image Logic
if (!empty($profileFilename)) {
    // Check if the filename already contains 'uploads/'
    if (strpos($profileFilename, 'uploads/') !== 0) {
        // If it doesn't start with 'uploads/', prepend the full path structure
        $profileImgSrc = BASE_URL . "uploads/" . $profileFilename;
    } else {
        // If it already starts with 'uploads/', just prepend BASE_URL
        // This handles cases where indexFunctions.php saved 'uploads/filename.png'
        $profileImgSrc = BASE_URL . $profileFilename;
    }
} else {
    $profileImgSrc = BASE_URL . "blank-profile-picture-973460_960_720-587709513.png";
}
// =========================================================
//                  LOGGER FUNCTION
// =========================================================
function logAction($conn, $actionType, $details) {
    if (isset($_SESSION['accountID'])) {
        $adminID = $_SESSION['accountID'];
        $stmt = $conn->prepare("INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $adminID, $actionType, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>adminpage.css">
    <!-- Optimized: Added defer to prevent render blocking -->
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous" defer></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Admin Dashboard</title>
</head>

<body>
    <div class="container">
        <div class="sidebar">
            <h1 class="logo">FleetNav</h1>
            <div class="nav">
                <button class="nav-btn <?php echo $view === 'dashboard' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=dashboard'"><i class="fas fa-tachometer-alt"></i>Dashboard</button>
                
                <?php if ($isSuperAdmin): ?>
                    <button class="nav-btn <?php echo $view === 'accounts' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=accounts'"><i class="fas fa-users"></i>Users</button>
                <?php else: ?>
                    <button class="nav-btn <?php echo $view === 'drivers' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=drivers'"><i class="fas fa-users"></i>Drivers</button>
                <?php endif; ?>
                
                <button class="nav-btn <?php echo $view === 'trucks' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=trucks'"><i class="fas fa-truck-moving"></i>Trucks</button>
                <button class="nav-btn <?php echo $view === 'deliveries' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=deliveries'"><i class="fas fa-clipboard-list"></i>Deliveries</button>
                          
                <button class="nav-btn <?php echo $view === 'history_reports' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=history_reports'"><i class="fas fa-history"></i>History Reports</button>
                <button class="nav-btn <?php echo $view === 'logs' ? 'active' : ''; ?>" onclick="window.location.href='AdminPage.php?view=logs'"><i class="fas fa-history"></i>Logs</button>
            </div>
        </div>

        <div class="content-wrapper">

            <header class="top-nav-bar">
                <h2 class="page-title">
                    <?php 
                        $pageTitle = strtoupper($view);
                        if ($view === 'drivers') $pageTitle = 'DRIVER MANAGEMENT';
                        if ($view === 'accounts' && $isSuperAdmin) $pageTitle = 'USER MANAGEMENT';
                        if ($view === 'accounts' && !$isSuperAdmin) $pageTitle = 'ACCOUNTS MANAGEMENT (ADMINS ONLY)';
                        echo $pageTitle; 
                    ?>
                </h2>
                <div class="user-profile">
                    <a href="EditProfile.php" title="Edit Profile">
                        <img src="<?php echo $profileImgSrc; ?>" alt="Profile" class="profile-icon">
                    </a>
                        <span class="user-name"><?php echo htmlspecialchars($fullName); ?> (<?php echo $accountType; ?>)</span>
                        <button class="logout-btn" onclick="window.location.href='logout.php';">Logout</button>
                </div>
            </header>

            <div class="main-content-area">
                <script>
                    (function() {
                        var msgHTML = <?php echo json_encode($message); ?>;
                        if (msgHTML && msgHTML.length) {
                            var type = msgHTML.indexOf('alert-success') !== -1 ? 'success' : (msgHTML.indexOf('alert-danger') !== -1 ? 'error' : 'info');
                            var text = msgHTML.replace(/<[^>]*>/g, '').replace(/\*\*/g, '');
                            var toast = document.createElement('div');
                            toast.style.position = 'fixed';
                            toast.style.right = '20px';
                            toast.style.top = '20px';
                            toast.style.zIndex = '9999';
                            toast.style.padding = '14px 16px';
                            toast.style.borderRadius = '6px';
                            toast.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
                            toast.style.color = '#fff';
                            toast.style.fontFamily = 'system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif';
                            toast.style.fontSize = '14px';
                            toast.style.maxWidth = '420px';
                            toast.style.whiteSpace = 'pre-wrap';
                            toast.style.background = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#007bff');
                            toast.textContent = text;
                            document.body.appendChild(toast);
                            setTimeout(function() {
                                toast.style.transition = 'opacity .4s';
                                toast.style.opacity = '0';
                                setTimeout(function() {
                                    if (toast.parentNode) toast.parentNode.removeChild(toast);
                                }, 400);
                            }, 3500);
                        }
                    })();
                </script>

                <?php if ($view === 'dashboard'): ?>
                   <section id="dashboard-charts">
                        <div class="chart-widget">
    <h3>Driver Assignment (Total Drivers: <?php echo $driversData['total_drivers']; ?>)</h3>
    <div class="chart-container"><canvas id="driverChart"></canvas></div>
    <div class="chart-legend">
        <span class="legend-assigned">Assigned: <?php echo $driversData['assigned']; ?> (<?php echo $driversData['assigned_percent']; ?>%)</span>
        
        <span class="legend-available">Available: <?php echo $driversData['available']; ?> (<?php echo $driversData['available_percent']; ?>%)</span>
        
        <span class="legend-unavailable">Unavailable: <?php echo $driversData['unavailable']; ?> (<?php echo $driversData['unavailable_percent']; ?>%)</span>
    </div>
</div>
                        <div class="chart-widget">
    <h3>Truck Status (Total Trucks: <?php echo $trucksData['total_trucks']; ?>)</h3>
    <div class="chart-container"><canvas id="truckChart"></canvas></div>
    <div class="chart-legend">
        <span class="legend-available">Available: <?php echo $trucksData['available']; ?> (<?php echo $trucksData['available_percent']; ?>%)</span>
        <span class="legend-grey">Unavailable: <?php echo $trucksData['unavailable']; ?> (<?php echo $trucksData['unavailable_percent']; ?>%)</span>
        <span class="legend-in-transit">In Transit: <?php echo $trucksData['in_transit']; ?> (<?php echo $trucksData['in_transit_percent']; ?>%)</span>
        <span class="legend-maintenance">Maintenance: <?php echo $trucksData['maintenance']; ?> (<?php echo $trucksData['maintenance_percent']; ?>%)</span>
    </div>
</div>
<div class="chart-widget">
    <h3>Delivery Status (Total Deliveries: <?php echo $deliveriesData['total_deliveries']; ?>)</h3>
    <div class="chart-container"><canvas id="deliveryChart"></canvas></div>
    <div class="chart-legend">
        <span class="legend-complete">Completed: <?php echo $deliveriesData['completed']; ?> (<?php echo $deliveriesData['completed_percent']; ?>%)</span>
        <span class="legend-cancelled">Cancelled: <?php echo $deliveriesData['cancelled']; ?> (<?php echo $deliveriesData['cancelled_percent']; ?>%)</span>
        <span class="legend-in-progress">In Progress: <?php echo $deliveriesData['in_progress']; ?> (<?php echo $deliveriesData['in_progress_percent']; ?>%)</span>
        <span class="legend-inactive">Inactive: <?php echo $deliveriesData['inactive']; ?> (<?php echo $deliveriesData['inactive_percent']; ?>%)</span>
    </div>
</div>
                   </section>

                <?php elseif ($view === 'drivers' || ($view === 'accounts' && $isSuperAdmin)): ?>
                    <section id="drivers-list">
                        <div class="section-header">
                            <h1>Fleet <?php echo ($view === 'accounts' && $isSuperAdmin) ? 'Users' : 'Drivers'; ?></h1>
                            <button class="action-btn" onclick="document.getElementById('addDriverModal').style.display='block';">➕ Add User</button>
                        </div>
                        <?php if (empty($usersList)): ?>
                            <div class="empty-state"><p>No users registered yet.</p></div>
                        <?php else: ?>
                            <div class="driver-grid">
                                <?php foreach ($usersList as $user): ?>
                                    <?php
                                    $isDriver = $user['accountType'] === 'Driver'; 
                                    $isAssigned = $isDriver && $user['truckID'] !== null; 
                                    $statusClass = $isAssigned ? 'status-unavailable' : 'status-available';
                                    $statusText = $isAssigned ? 'Assigned' : 'Available';
                                    $assignedTruck = $user['truckName'] ? htmlspecialchars($user['truckName']) . ' (ID: ' . $user['truckID'] . ')' : '<span class="text-unassigned">None</span>';
                                    
                                    // Override status for non-drivers
                                    if (!$isDriver) {
                                        $statusText = htmlspecialchars($user['accountType']);
                                        $statusClass = strtolower($user['accountType']) === 'super admin' ? 'status-super-admin' : 'status-admin';
                                    }

                                    // --- NEW PROFILE IMAGE LOGIC FOR DRIVER CARD ---
                                    $driverProfileFilename = $user['profileImg'] ?? null;
                                    if (!empty($driverProfileFilename)) {
                                        $driverProfileImgSrc = BASE_URL . "uploads/" .$driverProfileFilename;
                                        if (strpos($driverProfileFilename, 'uploads/') === 0) {
                                            $driverProfileImgSrc = BASE_URL . $driverProfileFilename;
                                        }                                                     
                                    } else {
                                        $driverProfileImgSrc = BASE_URL . "blank-profile-picture-973460_960_720-587709513.png"; // Use the same default as the admin
                                    }
                                    // -----------------------------------------------
                                    ?>
                                    <div class="driver-card"
                                        data-accountid="<?php echo htmlspecialchars($user['accountID']); ?>"
                                        data-firstname="<?php echo htmlspecialchars($user['firstName']); ?>"
                                        data-lastname="<?php echo htmlspecialchars($user['lastName']); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                        data-accounttype="<?php echo htmlspecialchars($user['accountType']); // [ADDED] Account Type ?>">
                                        <div class="driver-status-header">
                                            <h3 class="driver-name"><?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?></h3>
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </div>
                                        <div class="driver-details">
                                            
                                            <img src="<?php echo $driverProfileImgSrc; ?>" alt="Profile" class="driver-profile-icon">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($user['accountID']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                                            
                                            <?php if ($isDriver): // [MODIFIED] Only show truck for Drivers ?>
                                                <p><strong>Assigned Truck:</strong> <?php echo $assignedTruck; ?></p>
                                            <?php else: ?>
                                                <p><strong>Role:</strong> <?php echo htmlspecialchars($user['accountType']); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="driver-actions">
                                            <?php $isSelf = ($user['accountID'] == $adminID); ?>
                                            <?php $isTargetSuperAdmin = $user['accountType'] === 'Super Admin'; ?>
                                            
                                            <button class="icon-btn edit-btn" title="Edit User" onclick="openEditDriverModal(this)" <?php echo $isSelf ? '' : ''; // Allow self-edit for now ?>><i class="fas fa-edit"></i> <?php echo $isSuperAdmin ? 'Edit' : 'View'; ?></button>
                                            
                                            <button class="icon-btn delete-btn" title="Delete User" 
                                                onclick="confirmDeleteDriver(<?php echo htmlspecialchars($user['accountID']); ?>, '<?php echo htmlspecialchars($user['firstName'] . ' ' . $user['lastName']); ?>', <?php echo $isAssigned ? 'true' : 'false'; ?>, '<?php echo htmlspecialchars($user['accountType']); ?>')"
                                                <?php echo ($isAssigned || $isSelf || $isTargetSuperAdmin) ? 'disabled' : ''; ?>><i class="fas fa-trash"></i> Delete</button>
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
                            <div class="empty-state"><p>No trucks registered in the fleet yet.</p></div>
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
                                            <?php $imgPath = $truck['truckImg'] ? BASE_URL . "uploads/" . $truck['truckImg'] : BASE_URL . "assets/default-truck.png"; ?>
                                            <img src="<?php echo $imgPath; ?>" alt="Image of <?php echo htmlspecialchars($truck['truckName']); ?>">
                                            <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $truck['truckStatus'])); ?>"><?php echo htmlspecialchars($truck['truckStatus']); ?></span>
                                        </div>
                                        <div class="truck-details">
                                            <h3 class="truck-name"><?php echo htmlspecialchars($truck['truckName']); ?> (ID: <?php echo htmlspecialchars($truck['truckID']); ?>)</h3>
                                            <p><strong>Plate:</strong> <?php echo htmlspecialchars($truck['plateNumber']); ?></p>
                                            <p><strong>Mileage:</strong> <?php echo number_format($truck['odometerOrMileage']); ?> km</p>
                                            <p><strong>Driver:</strong> <?php echo $truck['assignedDriver'] ? htmlspecialchars($truck['firstName'] . ' ' . $truck['lastName']) : '<span class="text-unassigned">Unassigned</span>'; ?></p>
                                        </div>
                                        <div class="truck-actions">
                                            <button class="icon-btn edit-btn" title="Edit Truck" onclick="openEditTruckModal(this)"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="icon-btn delete-btn" title="Delete Truck" onclick="confirmDelete(<?php echo htmlspecialchars($truck['truckID']); ?>, '<?php echo htmlspecialchars($truck['truckName']); ?>')"><i class="fas fa-trash"></i> Delete</button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>

                <?php elseif ($view === 'deliveries'): ?>
                    <section id="deliveries-list">
                        <div class="section-header">
                            <h1>Delivery Management</h1>
                            
                            <!-- FILTER FORM START -->
                            <div style="display:flex; gap:10px; align-items:center;">
                                <form method="GET" action="AdminPage.php" style="margin:0;">
                                    <input type="hidden" name="view" value="deliveries">
                                    <select name="delivery_filter" onchange="this.form.submit()" style="padding: 10px; border-radius: 8px; border: 1px solid #ddd; font-size: 0.95rem; cursor:pointer;">
                                        <option value="">All</option>
                                        <option value="Inactive" <?php echo (isset($_GET['delivery_filter']) && $_GET['delivery_filter'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                        <option value="In Progress" <?php echo (isset($_GET['delivery_filter']) && $_GET['delivery_filter'] == 'In Progress') ? 'selected' : ''; ?>>In Progress</option>
                                    </select>
                                </form>
                                <button class="action-btn" onclick="document.getElementById('addDeliveryModal').style.display='block';">➕ Schedule Delivery</button>
                            </div>
                            <!-- FILTER FORM END -->

                        </div>
                        
                        <?php if (empty($deliveriesList)): ?>
                            <div class="empty-state"><p>No active deliveries matching your criteria.</p></div>
                        <?php else: ?>
                            <div class="card-list">
                                <?php 
                                    $index = 1; 
                                    foreach ($deliveriesList as $delivery): 
                                    $formattedDate = date('Y-m-d\TH:i', strtotime($delivery['estimatedTimeOfArrival']));
                                    $status = htmlspecialchars($delivery['deliveryStatus']);
                                    $editDisabled = ($status == 'Completed' || $status == 'Cancelled') ? 'disabled' : '';
                                    
                                    // ADDED data-allocatedgas
                                    $dataAttributes = "
                                        data-deliveryid='{$delivery['deliveryID']}'
                                        data-productname='" . htmlspecialchars($delivery['productName'] ?? '', ENT_QUOTES) . "'
                                        data-productdescription='" . htmlspecialchars($delivery['productDescription'] ?? '', ENT_QUOTES) . "'
                                        data-origin='" . htmlspecialchars($delivery['origin'] ?? '', ENT_QUOTES) . "'
                                        data-destination='" . htmlspecialchars($delivery['destination'] ?? '', ENT_QUOTES) . "'
                                        data-deliverydistance='" . htmlspecialchars($delivery['deliveryDistance'] ?? '0', ENT_QUOTES) . "' 
                                        data-allocatedgas='" . htmlspecialchars($delivery['allocatedGas'] ?? '0', ENT_QUOTES) . "' 
                                        data-assignedtruck='" . ($delivery['assignedTruck'] ?? '0') . "' 
                                        data-eta='" . $formattedDate . "'
                                        data-status='" . $status . "'";
                                    
                                    $deleteDisabled = ($status == 'Active' || $status == 'On Route' || $status == 'In Progress') ? 'disabled' : '';
                                ?>
                                <div class="delivery-card card-item" <?= $dataAttributes ?>>
                                    <div class="card-details">
                                        <h3>#<?= $index ?>: <?= htmlspecialchars($delivery['productName']) ?></h3>
                                        <p><strong>Status: </strong> <span class="badge status-<?= strtolower(str_replace(' ', '-', $status)) ?>"><?= $status ?></span></p>
                                        <p><strong>Route: </strong> <?= htmlspecialchars($delivery['origin']) ?> &rarr; <?= htmlspecialchars($delivery['destination']) ?></p>
                                        <div class="delivery-metrics">
                                            <p><strong>Distance: </strong><?= htmlspecialchars($delivery['deliveryDistance'])?></p>
                                            <p><strong>Allocated Gas: </strong><?= number_format($delivery['allocatedGas']) ?> L</p>
                                        </div>
                                        <p><strong>Truck: </strong> <?= $delivery['truckName'] ? htmlspecialchars($delivery['truckName']) . ' (' . $delivery['plateNumber'] . ')' : '<span class="text-unassigned">Unassigned</span>' ?></p>
                                        <p><strong>ETA: </strong> <?= date('M j, Y h:i A', strtotime($delivery['estimatedTimeOfArrival'])) ?></p>
                                    </div>
                                    
                                   <div class="card-actions">
                                        <?php if ($status == 'Inactive'): ?>
                                            <form method="POST" style="display:inline-block; flex-grow: 1;">
                                                <input type="hidden" name="delivery_id" value="<?= $delivery['deliveryID'] ?>">
                                                <button type="submit" name="start_delivery" class="icon-btn green-btn" title="Start Delivery">
                                                    <i class="fas fa-play"></i> Start Delivery
                                                </button>
                                            </form>
                                            <button class="icon-btn edit-btn" onclick="openEditDeliveryModal(this)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                            <button class="icon-btn delete-btn" 
                                                onclick="confirmDeleteDelivery(<?= $delivery['deliveryID'] ?>, '<?= htmlspecialchars($delivery['productName'], ENT_QUOTES) ?>', '<?= $status ?>')">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        <?php elseif ($status == 'In Progress'): ?>
                                            <form method="POST" style="display:inline-block; flex-grow: 1;">
                                                <input type="hidden" name="delivery_id" value="<?= $delivery['deliveryID'] ?>">
                                                <button type="submit" name="complete_delivery" class="icon-btn green-btn" title="Complete Delivery">
                                                    <i class="fas fa-check"></i> Complete Delivery
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline-block; flex-grow: 1;">
                                                <input type="hidden" name="delivery_id" value="<?= $delivery['deliveryID'] ?>">
                                                <button type="submit" name="cancel_delivery" class="icon-btn delete-btn" title="Cancel Delivery">
                                                    <i class="fas fa-times"></i> Cancel Delivery
                                                </button>
                                            </form>
                                        <?php else: // Completed or Cancelled ?>
                                            <span style="color:#555; font-style:italic;">Delivery Finalized</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php $index++; endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                    
                      
                    <?php elseif ($view === 'logs'): ?>
    <section id="logs-list">
        <div class="section-header">
            <h1>System Action Logs</h1>
        </div>

        <div style="background: white; padding: 15px 20px; border-bottom: 1px solid #eee; margin-bottom: 10px;">
            <form method="GET" action="AdminPage.php" class="data-form" style="padding:0;">
                <input type="hidden" name="view" value="logs">
                <div class="form-row" style="align-items: flex-end; gap: 15px;">
                    
                    <div class="form-group" style="margin-bottom:0;">
                        <label for="log_start" style="font-size:0.85rem;">From Date:</label>
                        <input type="date" id="log_start" name="log_start" 
                               value="<?php echo isset($_GET['log_start']) ? htmlspecialchars($_GET['log_start']) : ''; ?>">
                    </div>

                    <div class="form-group" style="margin-bottom:0;">
                        <label for="log_end" style="font-size:0.85rem;">To Date:</label>
                        <input type="date" id="log_end" name="log_end" 
                               value="<?php echo isset($_GET['log_end']) ? htmlspecialchars($_GET['log_end']) : ''; ?>">
                    </div>

                    <div class="form-group" style="margin-bottom:0; min-width: 150px;">
                        <label for="log_type" style="font-size:0.85rem;">Action Type:</label>
                        <select id="log_type" name="log_type">
    <option value="">All Actions</option>
    <option value="ADD" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'ADD') ? 'selected' : ''; ?>>Add (Create)</option>
    <option value="EDIT" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'EDIT') ? 'selected' : ''; ?>>Edit (Update)</option>
    <option value="DELETE" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'DELETE') ? 'selected' : ''; ?>>Delete (Remove)</option>
    <option value="LOGIN" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'LOGIN') ? 'selected' : ''; ?>>Login</option>
    
    <option value="LOGOUT" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'LOGOUT') ? 'selected' : ''; ?>>Logout</option>
    <option value="REPORT" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'REPORT') ? 'selected' : ''; ?>>Report Gas Updates</option>
    <option value="PROFILE" <?php echo (isset($_GET['log_type']) && $_GET['log_type'] == 'PROFILE') ? 'selected' : ''; ?>>Profile Updates</option>
</select>
                    </div>

                    <div class="form-group" style="margin-bottom:0; flex: 0 0 auto; display: flex; gap: 10px;">
                        <button type="submit" class="action-btn" style="padding: 10px 15px; font-size: 0.9rem;">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        <?php if(isset($_GET['log_start']) || isset($_GET['log_type'])): ?>
                            <a href="AdminPage.php?view=logs" class="crud-btn delete-btn" style="text-decoration:none; height: 38px;">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <?php 
        // --- BUILD LOG QUERY ---
        $logQuery = "
            SELECT L.log_id, L.action_type, L.action_details, L.TIMESTAMP, 
                   A.firstName, A.lastName, A.accountType
            FROM Action_Logs L
            LEFT JOIN Accounts A ON L.accountID = A.accountID
            WHERE 1=1 
        ";

        // 1. Check Account Permissions
        if ($_SESSION['accountType'] === 'Admin') {
            $adminID_safe = (int)$adminID;
            $logQuery .= " AND L.accountID = $adminID_safe ";
        }

        // 2. Filter by Start Date
        if (!empty($_GET['log_start'])) {
            $startDate = $conn->real_escape_string($_GET['log_start']);
            $logQuery .= " AND L.TIMESTAMP >= '$startDate 00:00:00' ";
        }

        // 3. Filter by End Date
        if (!empty($_GET['log_end'])) {
            $endDate = $conn->real_escape_string($_GET['log_end']);
            $logQuery .= " AND L.TIMESTAMP <= '$endDate 23:59:59' ";
        }

        // 4. Filter by Action Type
        if (!empty($_GET['log_type'])) {
            $type = $conn->real_escape_string($_GET['log_type']);
            // We use LIKE to catch specific variations (e.g., 'ADD_USER', 'ADD_TRUCK' will both match 'ADD%')
            $logQuery .= " AND L.action_type LIKE '$type%' ";
        }

        // 5. Final Ordering
        $logQuery .= " ORDER BY L.TIMESTAMP DESC";

        $logResult = $conn->query($logQuery); 
        ?> 

        <div class="data-table-container"> 
            <table class="data-table"> 
                <thead> 
                    <tr> 
                        <th style="width: 20%;">Date & Time</th> 
                        <th style="width: 20%;">User</th> 
                        <th style="width: 15%;">Action Type</th> 
                        <th style="width: 45%;">Details</th> 
                    </tr> 
                </thead> 
                <tbody> 
                    <?php if ($logResult && $logResult->num_rows > 0): ?> 
                        <?php while ($log = $logResult->fetch_assoc()): 
                            $dateDisplay = date('M j, Y h:i A', strtotime($log['TIMESTAMP'])); 
                            $fullName = ($log['firstName'] || $log['lastName']) ? htmlspecialchars($log['firstName'] . ' ' . $log['lastName']) : 'Unknown/Deleted'; 
                            $type = $log['accountType'] ? '(' . htmlspecialchars($log['accountType']) . ')' : ''; 
                            
                            // Visual Badge Coloring
                            $badgeColor = '#007bff'; 
if (stripos($log['action_type'], 'DELETE') !== false) $badgeColor = '#dc3545'; 
if (stripos($log['action_type'], 'ADD') !== false) $badgeColor = '#28a745'; 
if (stripos($log['action_type'], 'EDIT') !== false) $badgeColor = '#ffc107'; 
if (stripos($log['action_type'], 'LOGIN') !== false) $badgeColor = '#17a2b8';
if (stripos($log['action_type'], 'LOGOUT') !== false) $badgeColor = '#6c757d'; 
if (stripos($log['action_type'], 'GAS') !== false) $badgeColor = '#6610f2';
                        ?> 
                    <tr> 
                        <td style="color: #555; font-size: 0.9em;"> 
                            <i class="far fa-clock"></i> <?php echo $dateDisplay; ?> 
                        </td> 
                        <td> 
                            <strong><?php echo $fullName; ?></strong> <br><small style="color: #888;"><?php echo $type; ?></small> 
                        </td> 
                        <td> 
                            <span class="badge" style="background-color: <?php echo $badgeColor; ?>;"><?php echo htmlspecialchars(str_replace('_', ' ', $log['action_type'])); ?></span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($log['action_details']); ?>
                        </td>
                    </tr> 
                    <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="4" style="text-align:center; padding: 30px;">No logs found matching your criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
                
                    <?php elseif ($view === 'history_reports'): ?>
    <section id="history-reports-list">
        <div class="section-header">
            <h1>Delivery History Reports</h1>
        </div>
        <?php if (empty($reportsList)): ?>
            <div class="empty-state"><p>No delivery history reports available yet.</p></div>
        <?php else: ?>
            <div class="card-list">
                <?php foreach ($reportsList as $report): 
                    $gasUsed = (int)$report['gasUsed'];
                    $allocatedGas = (int)$report['allocatedGas'];
                    $isExceeded = $gasUsed > $allocatedGas;
                    
                ?>
                <div class="report-card card-item"
                    data-historyid="<?php echo htmlspecialchars($report['historyID']); ?>"
                    data-deliveryname="<?php echo htmlspecialchars($report['productName']); ?>"
                    data-allocatedgas="<?php echo htmlspecialchars($allocatedGas); ?>"
                    data-gasused="<?php echo htmlspecialchars($gasUsed); ?>">
                    
                    <div class="card-details"> 
                        <h3><?= htmlspecialchars($report['productName']) ?> (Completed)</h3>
                        <p><strong>Route: </strong> <?= htmlspecialchars($report['origin']) ?> &rarr; <?= htmlspecialchars($report['destination']) ?></p>
                        <p><strong>Date Completed: </strong> <?= date('M j, Y h:i A', strtotime($report['dateTimeCompleted'])) ?></p>
                        <div class="delivery-metrics"> 
                            <p><strong>Distance: </strong><?= htmlspecialchars($report['deliveryDistance'])?></p> 
                            <p><strong>Allocated Gas: </strong><?= number_format($allocatedGas) ?> L</p> 
                            <p><strong>Gas Used: </strong> <span class="text-<?= $isExceeded ? 'danger' : ($gasUsed > 0 ? 'success' : 'muted') ?>"> <?= $gasUsed > 0 ? number_format($gasUsed) . ' L' : 'Pending Input' ?> </span></p> 
                        </div> 
                        <p><strong>Truck: </strong> <?= htmlspecialchars($report['truckName']) . ' (' . $report['plateNumber'] . ')' ?></p> 
                        <p><strong>Driver: </strong> <?= htmlspecialchars($report['driverFirstName'] . ' ' . $report['driverLastName']) ?></p> 
                        <?= $report['messageAlert'] ?> 
                    </div> 
                    <div class="card-actions"> 
                        <button class="icon-btn edit-btn" onclick="openEditGasModal(this)"> 
                            <i class="fas fa-gas-pump"></i> Enter Gas Used 
                        </button> 
                    </div> 
                </div> 
                <?php endforeach; ?> 
            </div> 
        <?php endif; ?> 
    </section> 
<?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($view === 'drivers' || ($view === 'accounts' && $isSuperAdmin)): ?>
    <div id="addDriverModal" class="modal"> 
        <div class="modal-content"> 
            <span class="close-btn" onclick="document.getElementById('addDriverModal').style.display='none';">&times;</span> 
            <h2>Add New User</h2> 
            <form class="data-form" id="addDriverForm" method="POST"> 
                <div class="form-row"> 
                    <div class="form-group"><label for="firstName">First Name *</label><input type="text" id="firstName" name="firstName" required></div> 
                    <div class="form-group"><label for="lastName">Last Name *</label><input type="text" id="lastName" name="lastName" required></div> 
                </div> 
                <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" required></div>
                <div class="form-group"><label for="password">Password *</label><input type="password" id="password" name="password" required></div>
                
                <?php if ($isSuperAdmin): ?>
                    <div class="form-group">
                        <label for="addAccountType">Account Role:</label>
                        <select class="form-control" id="addAccountType" name="accountType" required onchange="toggleAddAdminPassField()">
                            <option value="Driver">Driver</option>
                            <option value="Admin">Admin</option>
                            <option value="Super Admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="addAdminRegPassContainer" style="display: none;">
                        <label for="addAdminRegPass">Admin Registration Password (Required for Admin/Super Admin):</label>
                        <input type="password" class="form-control" id="addAdminRegPass" name="adminRegPass" placeholder="Enter Admin Registration Password">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="accountType" value="Driver">
                <?php endif; ?>
                
                <button type="submit" name="add_driver_submit" class="submit-btn green-btn">ADD USER</button> 
            </form> 
        </div> 
    </div>
    <div id="editDriverModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('editDriverModal').style.display='none';">&times;</span>
            <h2 id="editDriverModalTitle">Edit User <span id="editUserIDDisplay"></span></h2>
            <form class="data-form" id="editDriverForm" method="POST">
                <input type="hidden" name="editAccountID" id="editAccountID">
                <input type="hidden" name="editCurrentAccountType" id="editCurrentAccountType"> <div class="form-row">
                    <div class="form-group"><label for="editFirstName">First Name *</label><input type="text" id="editFirstName" name="editFirstName" required></div>
                    <div class="form-group"><label for="editLastName">Last Name *</label><input type="text" id="editLastName" name="editLastName" required></div>
                </div>
                <div class="form-group"><label for="editEmail">Email *</label><input type="email" id="editEmail" name="editEmail" required></div>
                
                <?php if ($isSuperAdmin): ?>
                <div class="form-group">
                    <label for="editAccountType">Account Role:</label>
                    <select class="form-control" id="editAccountType" name="editAccountType" required>
                        <option value="Driver">Driver</option>
                        <option value="Admin">Admin</option>
                        <option value="Super Admin">Super Admin</option>
                    </select>
                </div>
                
                <div class="form-group" id="adminRegPassContainer" style="display: none;">
                    <label for="adminRegPass">Admin Registration Password (Required to Save Changes):</label>
                    <input type="password" class="form-control" id="adminRegPass" name="adminRegPass" placeholder="Enter Admin Registration Password">
                </div>
                <?php else: ?>
                    <input type="hidden" name="editAccountType" id="editAccountType" value="Driver">
                <?php endif; ?>

                <div class="form-group"><label for="editPassword">New Password (optional)</label><input type="password" id="editPassword" name="editPassword" placeholder="Leave blank to keep current password"></div>
                
                <button type="submit" name="edit_driver_submit" id="editDriverSubmitBtn" class="submit-btn green-btn">SAVE CHANGES</button>
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
                <div class="form-row"> 
                    <div class="form-group"><label for="truckName">Truck Name/Model *</label><input type="text" id="truckName" name="truckName" required></div> 
                    <div class="form-group"><label for="plateNumber">Plate Number *</label><input type="text" id="plateNumber" name="plateNumber" required></div> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"><label for="odometerOrMileage">Odometer/Mileage *</label><input type="number" id="odometerOrMileage" name="odometerOrMileage" min="0" required></div> 
                    <div class="form-group"><label for="registrationDate">Registration Date *</label><input type="date" id="registrationDate" name="registrationDate" required></div> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"> 
                        <label for="truckStatus">Truck Status *</label> 
                        <select id="truckStatus" name="truckStatus" required> 
                            <option value="Available">Available</option><option value="Unavailable">Unavailable</option><option value="Maintenance">Maintenance</option> 
                        </select> 
                    </div> 
                    <div class="form-group"> 
                        <label for="assignedDriver">Assigned Driver</label> 
                        <select id="assignedDriver" name="assignedDriver"> 
                            <option value="">--- Unassigned ---</option> 
                            <?php foreach ($availableDrivers as $driver): ?><option value="<?php echo htmlspecialchars($driver['accountID']); ?>"><?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?></option><?php endforeach; ?> 
                        </select> 
                    </div> 
                </div> 
                <input type="hidden" name="uploadedImagePath" id="uploadedImagePath" value="">
    <div class="form-group">
        <label for="truckImage">Truck Image</label>
        <div class="preview-container" id="truckImagePreviewContainer" style="display:none;">
            <img id="truckImagePreview" src="#" alt="Truck Image Preview">
            <button type="button" class="clear-image-btn red-btn" id="addClearImageBtn">Clear Image</button>
        </div>
        <div class="upload-container">
            <div id="dropArea" class="drop-area"><p>Drag & Drop or <span id="selectFileLink">select a file</span></p></div>
            <input type="file" id="truckImage" name="truckImage" accept="image/*" style="display:none;">
        </div>
    </div>
    <button type="submit" name="add_truck_submit" class="submit-btn green-btn">ADD TRUCK</button>
</form>
        </div> 
    </div> 

    <div id="editTruckModal" class="modal"> 
        <div class="modal-content"> 
            <span class="close-btn" onclick="document.getElementById('editTruckModal').style.display='none';">&times;</span> 
            <h2>Edit Truck <span id="editTruckIDDisplay"></span></h2> 
            <form class="data-form" id="editTruckForm" method="POST" enctype="multipart/form-data"> 
                <input type="hidden" name="editTruckID" id="editTruckID"> 
                <input type="hidden" name="editCurrentTruckImg" id="editCurrentTruckImg"> 
                <input type="hidden" name="editUploadedImagePath" id="editUploadedImagePath"> 
                <div class="form-row"> 
                    <div class="form-group"><label for="editTruckName">Truck Name/Model *</label><input type="text" id="editTruckName" name="editTruckName" required></div> 
                    <div class="form-group"><label for="editPlateNumber">Plate Number *</label><input type="text" id="editPlateNumber" name="editPlateNumber" required></div> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"><label for="editOdometerOrMileage">Odometer/Mileage *</label><input type="number" id="editOdometerOrMileage" name="editOdometerOrMileage" min="0" required></div> 
                    <div class="form-group"><label for="editRegistrationDate">Registration Date *</label><input type="date" id="editRegistrationDate" name="editRegistrationDate" required></div> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"> 
                        <label for="editTruckStatus">Truck Status *</label> 
                        <select id="editTruckStatus" name="editTruckStatus" required> 
                            <option value="Available">Available</option><option value="Unavailable">Unavailable</option><option value="Maintenance">Maintenance</option> 
                        </select> 
                    </div> 
                    <div class="form-group"> 
                        <label for="editAssignedDriver">Assigned Driver</label> 
                        <select id="editAssignedDriver" name="editAssignedDriver"> 
                            <option value="">--- Unassigned ---</option> 
                            <?php foreach ($allDrivers as $driver): ?><option value="<?php echo htmlspecialchars($driver['accountID']); ?>"><?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?></option><?php endforeach; ?> 
                        </select> 
                    </div> 
                </div> 
                <div class="form-group"> 
                    <label for="editTruckImage">Truck Image</label> 
                    <div class="upload-container"> 
                        <div id="editPreviewContainer" class="preview-container" style="justify-content: center; display: none;"> 
                            <img id="editPreviewImage" src="" alt="Truck Image Preview"> 
                            <span id="editCurrentPathName"></span> 
                            <button type="button" class="clear-image-btn" id="editClearImageBtn">Remove Image</button> 
                        </div> 
                        <div id="editDropArea" class="drop-area"> 
                            <p>Drag & Drop or <span id="editSelectFileLink">select a file</span></p> 
                        </div> 
                        <input type="file" id="editTruckImage" name="editTruckImage" accept="image/*" style="display:none;"> 
                    </div> 
                </div> 
                <button type="submit" name="edit_truck_submit" class="submit-btn green-btn">SAVE CHANGES</button> 
            </form> 
        </div> 
    </div> 
    <?php endif; ?>
    
    <?php if ($view === 'deliveries'): ?>
    <div id="addDeliveryModal" class="modal"> 
        <div class="modal-content"> 
            <span class="close-btn" onclick="document.getElementById('addDeliveryModal').style.display='none';">&times;</span> 
            <h2>Schedule New Delivery</h2> 
            <form class="data-form" id="addDeliveryForm" method="POST"> 
                <div class="form-row"> 
                    <div class="form-group"><label for="productName">Product Name</label><input type="text" id="productName" name="productName" required></div> 
                    <div class="form-group"><label for="estimatedTimeOfArrival">ETA</label><input type="datetime-local" id="estimatedTimeOfArrival" name="estimatedTimeOfArrival" required></div> 
                </div> 
                <div class="form-group"><label for="productDescription">Description</label><textarea id="productDescription" name="productDescription" rows="2"></textarea></div> 
                <div class="form-row"> 
                    <div class="form-group"> 
                        <label for="origin">Origin</label> 
                        <input type="text" id="origin" name="origin" required placeholder="Type or select from map..."> 
                    </div> 
                    <div class="form-group"> 
                        <label for="destination">Destination</label> 
                        <input type="text" id="destination" name="destination" required placeholder="Type or select from map..."> 
                    </div> 
                </div> 
                <div class="form-group" style="margin-bottom: 15px;"> 
                    <button type="button" class="action-btn" style="width:100%; justify-content:center; background-color: #17a2b8;" onclick="openGoogleMapModal('add')"> 
                        <i class="fas fa-map-marked-alt"></i> Select Route on Google Maps 
                    </button> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"><label for="deliveryDistance">Delivery Distance</label><input type="text" id="deliveryDistance" name="deliveryDistance" required></div> 
                    <div class="form-group"><label for="allocatedGas">Allocated Gas (Liters)</label><input type="number" id="allocatedGas" name="allocatedGas" min="0" value="0" required></div> 
                </div> 
                <div class="form-group"> 
                    <label for="assignedTruck">Assign Truck</label> 
                    <select id="assignedTruck" name="assignedTruck"> 
                        <option value="">--- Unassigned (Inactive) ---</option> 
                        <?php foreach ($availableTrucks as $truck): ?><option value="<?php echo htmlspecialchars($truck['truckID']); ?>"><?php echo htmlspecialchars($truck['truckName'] . ' (' . $truck['plateNumber'] . ')'); ?></option><?php endforeach; ?> 
                    </select> 
                </div> 
                <button type="submit" name="add_delivery_submit" class="submit-btn green-btn">SCHEDULE DELIVERY</button> 
            </form> 
        </div> 
    </div> 

    <div id="editDeliveryModal" class="modal"> 
        <div class="modal-content"> 
            <span class="close-btn" onclick="document.getElementById('editDeliveryModal').style.display='none';">&times;</span> 
            <h2>Edit Delivery <span id="editDeliveryIDDisplay"></span></h2> 
            <form class="data-form" id="editDeliveryForm" method="POST"> 
                <input type="hidden" name="editDeliveryID" id="editDeliveryID"> 
                <div class="form-row"> 
                    <div class="form-group"><label for="editProductName">Product Name</label><input type="text" id="editProductName" name="editProductName" required></div> 
                    <div class="form-group"><label for="editEstimatedTimeOfArrival">ETA</label><input type="datetime-local" id="editEstimatedTimeOfArrival" name="editEstimatedTimeOfArrival" required></div> 
                </div> 
                <div class="form-group"><label for="editProductDescription">Description</label><textarea id="editProductDescription" name="editProductDescription" rows="2"></textarea></div> 
                <div class="form-row"> 
                    <div class="form-group"> 
                        <label for="editOrigin">Origin</label> 
                        <input type="text" id="editOrigin" name="editOrigin" required placeholder="Type or select from map..."> 
                    </div> 
                    <div class="form-group"> 
                        <label for="editDestination">Destination</label> 
                        <input type="text" id="editDestination" name="editDestination" required placeholder="Type or select from map..."> 
                    </div> 
                </div> 
                <div class="form-group" style="margin-bottom: 15px;"> 
                    <button type="button" class="action-btn" style="width:100%; justify-content:center; background-color: #17a2b8;" onclick="openGoogleMapModal('edit')"> 
                        <i class="fas fa-map-marked-alt"></i> Select Route on Google Maps 
                    </button> 
                </div> 
                <div class="form-row"> 
                    <div class="form-group"><label for="editDeliveryDistance">Delivery Distance</label><input type="text" id="editDeliveryDistance" name="editDeliveryDistance" required></div> 
                    <div class="form-group"><label for="editAllocatedGas">Allocated Gas (Liters)</label><input type="number" id="editAllocatedGas" name="editAllocatedGas" min="0" value="0" required></div> 
                </div> 
                <div class="form-group"> 
                    <label for="editAssignedTruck">Assign Truck</label> 
                    <select id="editAssignedTruck" name="editAssignedTruck"> 
                        <option value="">--- Unassigned (Inactive) ---</option> 
                        <?php foreach ($allTrucks as $truck): ?><option value="<?php echo htmlspecialchars($truck['truckID']); ?>"><?php echo htmlspecialchars($truck['truckName'] . ' (' . $truck['plateNumber'] . ')'); ?></option><?php endforeach; ?> 
                    </select> 
                </div> 
                <button type="submit" name="edit_delivery_submit" class="submit-btn green-btn">SAVE CHANGES</button> 
            </form> 
        </div> 
    </div> 
    <?php endif; ?>

    <?php if ($view === 'history_reports'): ?>
    <div id="gasUsedModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('gasUsedModal').style.display='none';">&times;</span>
            <h2>Update Gas Usage <span id="gasReportIDDisplay"></span></h2>
            <form class="data-form" id="gasUsedForm" method="POST">
                <input type="hidden" name="historyID" id="gasReportID">
                <div class="form-group">
                    <label>Allocated Gas:</label>
                    <p id="allocatedGasDisplay" style="font-weight: bold;"></p>
                </div>
                <div class="form-group">
                    <label for="gasUsed">Gas Used (Liters) *</label>
                    <input type="number" id="gasUsed" name="gasUsed" min="0" required>
                </div>
                <button type="submit" name="update_gas_submit" class="submit-btn green-btn">SAVE GAS USAGE</button>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div id="googleMapModal" class="modal"> 
        <div class="modal-content map-modal-content"> 
            <span class="close-btn" onclick="document.getElementById('googleMapModal').style.display='none';">&times;</span> 
            <h2>Select Route</h2> 
            <div class="map-controls"> 
                <div class="form-group" style="flex: 1;"> 
                    <label>Map Origin</label> 
                    <input type="text" id="mapOriginInput" placeholder="Enter start location"> 
                </div> 
                <div class="form-group" style="flex: 1;"> 
                    <label>Map Destination</label> 
                    <input type="text" id="mapDestInput" placeholder="Enter destination"> 
                </div> 
                <div class="map-btn-group"> 
                    <button type="button" class="crud-btn edit-btn" onclick="calculateRoute()">Calculate</button> 
                    <button type="button" class="crud-btn green-btn" onclick="confirmMapSelection()">Use Route</button> 
                </div> 
            </div> 
            <div id="googleMapContainer"></div> 
            <div id="mapStats" style="margin-top:10px; font-weight:bold; color:#333;"> </div> 
        </div> 
    </div>
    
    <?php if ($view === 'dashboard'): ?> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script> 
    <script> 
        // Generalized function for multi-segment pie charts function createMultiSegmentPieChart(chartId, labels, data, colors...
        function createMultiSegmentPieChart(chartId, labels, data, colors) {
            new Chart(document.getElementById(chartId), {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: colors,
                        hoverOffset: 10,
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                        // Calculate percentage
                                        const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        const percentage = ((context.parsed / total) * 100).toFixed(1) + '%';
                                        label += ` (${percentage})`;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }

        // CHART COLORS 
        const CHART_COLORS = {
            red: 'rgb(220, 53, 69)', // danger
            blue: 'rgb(0, 123, 255)', // primary
            green: 'rgb(40, 167, 69)', // success
            yellow: 'rgb(255, 193, 7)', // warning
            grey: 'rgb(173, 181, 189)' // secondary/info
        };

        // DRIVER DATA (3 segments)
        const driverLabels = ['Assigned', 'Available', 'Unavailable'];
        const driverData = [<?php echo $driversData['assigned']; ?>, <?php echo $driversData['available']; ?>, <?php echo $driversData['unavailable']; ?>];
        const driverColors = [CHART_COLORS.red, CHART_COLORS.blue, CHART_COLORS.grey];

        // TRUCK DATA (4 segments)
        const truckLabels = ['Available', 'Unavailable', 'In Transit', 'Maintenance'];
        const truckData = [<?php echo $trucksData['available']; ?>, <?php echo $trucksData['unavailable']; ?>, <?php echo $trucksData['in_transit']; ?>, <?php echo $trucksData['maintenance']; ?>];
        const truckColors = [CHART_COLORS.blue, CHART_COLORS.grey, CHART_COLORS.yellow, CHART_COLORS.red];

        // DELIVERY DATA (4 segments)
        const deliveryLabels = ['Completed', 'Cancelled', 'In Progress', 'Inactive'];
        const deliveryData = [<?php echo $deliveriesData['completed']; ?>, <?php echo $deliveriesData['cancelled']; ?>, <?php echo $deliveriesData['in_progress']; ?>, <?php echo $deliveriesData['inactive']; ?>];
        const deliveryColors = [CHART_COLORS.green, CHART_COLORS.red, CHART_COLORS.yellow, CHART_COLORS.grey];

        // RENDER CHARTS
        createMultiSegmentPieChart('driverChart', driverLabels, driverData, driverColors);
        createMultiSegmentPieChart('truckChart', truckLabels, truckData, truckColors);
        createMultiSegmentPieChart('deliveryChart', deliveryLabels, deliveryData, deliveryColors);
    </script> 
    <?php endif; ?> 
    
    <script> 
        // Modal Handling Scripts 
        const modal = document.getElementById('addTruckModal'); 
        const editModal = document.getElementById('editTruckModal'); 
        const addDeliveryModal = document.getElementById('addDeliveryModal'); 
        const editDeliveryModal = document.getElementById('editDeliveryModal'); 
        
        if (modal) { 
            window.onclick = function(event) { 
                if (event.target === modal) modal.style.display = "none"; 
                if (editModal && event.target === editModal) editModal.style.display = "none"; 
                const addDriverModal = document.getElementById('addDriverModal'); 
                const editDriverModal = document.getElementById('editDriverModal'); 
                if (addDriverModal && event.target === addDriverModal) addDriverModal.style.display = "none"; 
                if (editDriverModal && event.target === editDriverModal) editDriverModal.style.display = "none"; 
                if (addDeliveryModal && event.target === addDeliveryModal) addDeliveryModal.style.display = "none";
                if (editDeliveryModal && event.target === editDeliveryModal) editDeliveryModal.style.display = "none";
            };
        }
        
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }
        
        // --- DRIVER JS (MODIFIED) ---
        
        function confirmDeleteDriver(id, name, isAssigned, accountType) {
            let message = `Are you sure you want to delete ${accountType}: "${name}" (ID: ${id})? This action cannot be undone.`;
            
            if (accountType === 'Super Admin') {
                alert(`Cannot delete Super Admin accounts for security purposes. Please demote or contact a database administrator.`);
                return;
            }
            if (isAssigned) {
                message += "\n\nWARNING: This user is currently assigned to a truck, which will be unassigned (set to NULL).";
            }
            
            if (confirm(message)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'AdminPage.php?view=accounts'; 
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete_user_id';
                input.value = id;
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // [NEW] Toggle function for Add User Modal
        function toggleAddAdminPassField() {
            const roleSelect = document.getElementById('addAccountType');
            const passContainer = document.getElementById('addAdminRegPassContainer');
            const passInput = document.getElementById('addAdminRegPass');
            
            if (roleSelect && passContainer && passInput) {
                if (roleSelect.value === 'Admin' || roleSelect.value === 'Super Admin') {
                    passContainer.style.display = 'block';
                    passInput.required = true;
                } else {
                    passContainer.style.display = 'none';
                    passInput.required = false;
                    passInput.value = '';
                }
            }
        }

        // [MODIFIED] openEditDriverModal to enforce Read-Only for Admins vs Edit for Super Admins
        function openEditDriverModal(buttonElement) { 
            const card = buttonElement.closest('.driver-card'); 
            const accountID = card.dataset.accountid; 
            const firstName = card.dataset.firstname; 
            const lastName = card.dataset.lastname; 
            const email = card.dataset.email; 
            const accountType = card.dataset.accounttype; 
            
            // Check current user role (injected via PHP)
            const isSuperAdmin = <?php echo $isSuperAdmin ? 'true' : 'false'; ?>;

            document.getElementById('editAccountID').value = accountID; 
            document.getElementById('editUserIDDisplay').textContent = accountID; 
            document.getElementById('editFirstName').value = firstName; 
            document.getElementById('editLastName').value = lastName; 
            document.getElementById('editEmail').value = email; 
            
            document.getElementById('editCurrentAccountType').value = accountType; 

            const editAccountTypeSelect = document.getElementById('editAccountType'); 
            if (editAccountTypeSelect) { 
                editAccountTypeSelect.value = accountType; 
            }
            
            const adminPassContainer = document.getElementById('adminRegPassContainer');
            const submitBtn = document.getElementById('editDriverSubmitBtn');
            const modalTitle = document.getElementById('editDriverModalTitle');
            const formInputs = document.querySelectorAll('#editDriverForm input, #editDriverForm select');

            if (isSuperAdmin) {
                // SUPER ADMIN MODE: ENABLE EDITING
                modalTitle.innerHTML = `Edit User <span id="editUserIDDisplay">${accountID}</span>`;
                
                // Enable inputs
                formInputs.forEach(input => input.disabled = false);
                
                // Show Admin Password Field (Now ALWAYS required for changes)
                if (adminPassContainer) {
                    adminPassContainer.style.display = 'block';
                    document.getElementById('adminRegPass').required = true;
                }
                
                // Show Submit Button
                if (submitBtn) submitBtn.style.display = 'block';

            } else {
                // REGULAR ADMIN MODE: VIEW ONLY
                modalTitle.innerHTML = `View User Details <span id="editUserIDDisplay">${accountID}</span>`;
                
                // Disable inputs
                formInputs.forEach(input => input.disabled = true);
                
                // Hide Admin Password Field
                if (adminPassContainer) {
                    adminPassContainer.style.display = 'none';
                    document.getElementById('adminRegPass').required = false;
                }
                
                // Hide Submit Button
                if (submitBtn) submitBtn.style.display = 'none';
            }
            
            document.getElementById('editDriverModal').style.display = 'block'; 
        }

        // --- TRUCK JS (FIXED) ---
        // Image preview logic for ADD truck modal
        const dropArea = document.getElementById('dropArea'); 
        const fileInput = document.getElementById('truckImage'); 
        const selectFileLink = document.getElementById('selectFileLink'); 
        const previewContainer = document.getElementById('truckImagePreviewContainer');
        const previewImage = document.getElementById('truckImagePreview');
        const clearImageBtn = document.getElementById('addClearImageBtn');

        function showPreview(file) { 
            if(!file) return;
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function() {
                previewImage.src = reader.result;
                previewContainer.style.display = 'flex';
                dropArea.style.display = 'none';
            };
        } 
        
        function clearSelection() { 
            fileInput.value = '';
            previewImage.src = '#';
            previewContainer.style.display = 'none';
            dropArea.style.display = 'block';
            // Clear hidden input used for edit tracking logic (reused concept)
            document.getElementById('uploadedImagePath').value = '';
        } 

        if (fileInput) fileInput.addEventListener('change', (e) => { showPreview(e.target.files[0]); }); 
        if (selectFileLink) selectFileLink.addEventListener('click', () => { fileInput.click(); }); 
        if (clearImageBtn) clearImageBtn.addEventListener('click', clearSelection);

        if (dropArea) { 
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { dropArea.addEventListener(eventName, preventDefaults, false); }); 
            ['dragenter', 'dragover'].forEach(eventName => { dropArea.addEventListener(eventName, () => { dropArea.classList.add('dragover'); }, false); }); 
            ['dragleave', 'drop'].forEach(eventName => { dropArea.addEventListener(eventName, () => { dropArea.classList.remove('dragover'); }, false); }); 
            dropArea.addEventListener('drop', (e) => { 
                let dt = e.dataTransfer; 
                let files = dt.files; 
                if (files.length) { 
                    fileInput.files = files; 
                    showPreview(files[0]); 
                } 
            }, false); 
        } 
        
        function confirmDelete(id, name) { 
            if (confirm(`Delete truck "${name}" (ID: ${id})? Action cannot be undone.`)) { 
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
        
        // --- EDIT TRUCK JS (FIXED) --- 
        const editFileInput = document.getElementById('editTruckImage'); 
        const editSelectFileLink = document.getElementById('editSelectFileLink'); 
        const editDropArea = document.getElementById('editDropArea');
        const editPreviewContainer = document.getElementById('editPreviewContainer'); 
        const editPreviewImage = document.getElementById('editPreviewImage'); 
        const editClearImageBtn = document.getElementById('editClearImageBtn'); 
        const editCurrentPathName = document.getElementById('editCurrentPathName'); 
        const editUploadedImagePathInput = document.getElementById('editUploadedImagePath'); // Used to flag deletion

        function openEditTruckModal(buttonElement) { 
            const card = buttonElement.closest('.truck-card'); 
            const baseURL = "<?php echo BASE_URL; ?>"; 
            
            document.getElementById('editTruckID').value = card.dataset.truckid; 
            document.getElementById('editTruckIDDisplay').textContent = card.dataset.truckid; 
            document.getElementById('editTruckName').value = card.dataset.truckname; 
            document.getElementById('editPlateNumber').value = card.dataset.plate; 
            document.getElementById('editTruckStatus').value = card.dataset.status; 
            document.getElementById('editOdometerOrMileage').value = card.dataset.odometer; 
            document.getElementById('editRegistrationDate').value = card.dataset.regdate; 
            document.getElementById('editAssignedDriver').value = card.dataset.driverid; 
            
            const currentImg = card.dataset.img; 
            document.getElementById('editCurrentTruckImg').value = currentImg; 
            
            // Reset the "Remove flag"
            editUploadedImagePathInput.value = ''; 
            
            if (currentImg && currentImg !== 'null') { 
                // Handle path logic: check if it already has 'uploads/'
                let path = currentImg;
                if (!currentImg.startsWith('uploads/')) {
                     path = "uploads/" + currentImg;
                }
                editPreviewImage.src = baseURL + path; 
                editCurrentPathName.textContent = currentImg; 
                editPreviewContainer.style.display = 'flex'; 
                editDropArea.style.display = 'none'; 
            } else { 
                editPreviewContainer.style.display = 'none'; 
                editDropArea.style.display = 'flex'; 
                editPreviewImage.src = '';
            } 
            
            document.getElementById('editTruckModal').style.display = 'block'; 
        } 
        
        function editShowPreview(file) { 
             if(!file) return;
            const reader = new FileReader();
            reader.readAsDataURL(file);
            reader.onload = function() {
                editPreviewImage.src = reader.result;
                editPreviewContainer.style.display = 'flex';
                editCurrentPathName.textContent = "New Image Selected";
                editDropArea.style.display = 'none';
                
                // Reset the "Remove flag" because we just added a new one
                editUploadedImagePathInput.value = '';
            };
        } 
        
        function editClearSelection() { 
            // 1. Clear input
            editFileInput.value = '';
            // 2. Hide preview
            editPreviewContainer.style.display = 'none'; 
            editDropArea.style.display = 'block';
            editPreviewImage.src = '';
            // 3. Mark for deletion in backend
            editUploadedImagePathInput.value = 'null';
        }
        
        if (editSelectFileLink) editSelectFileLink.addEventListener('click', () => { editFileInput.click(); }); 
        if (editFileInput) editFileInput.addEventListener('change', (e) => { editShowPreview(e.target.files[0]); }); 
        if (editClearImageBtn) editClearImageBtn.addEventListener('click', editClearSelection); 
        
        if (editDropArea) { 
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { editDropArea.addEventListener(eventName, preventDefaults, false); }); 
            ['dragenter', 'dragover'].forEach(eventName => { editDropArea.addEventListener(eventName, () => { editDropArea.classList.add('dragover'); }, false); }); 
            ['dragleave', 'drop'].forEach(eventName => { editDropArea.addEventListener(eventName, () => { editDropArea.classList.remove('dragover'); }, false); }); 
            editDropArea.addEventListener('drop', (e) => { 
                let dt = e.dataTransfer; 
                let files = dt.files; 
                if (files.length) { 
                    editFileInput.files = files; 
                    editShowPreview(files[0]); 
                } 
            }, false); 
        }

        // --- DELIVERY JS (unchanged) --- 
        function openEditDeliveryModal(buttonElement) { 
            const card = buttonElement.closest('.delivery-card'); 
            document.getElementById('editDeliveryID').value = card.dataset.deliveryid; 
            document.getElementById('editDeliveryIDDisplay').textContent = card.dataset.deliveryid; 
            document.getElementById('editProductName').value = card.dataset.productname; 
            document.getElementById('editProductDescription').value = card.dataset.productdescription; 
            document.getElementById('editAssignedTruck').value = card.dataset.assignedtruck; 
            document.getElementById('editOrigin').value = card.dataset.origin; 
            document.getElementById('editDestination').value = card.dataset.destination; 
            document.getElementById('editDeliveryDistance').value = card.dataset.deliverydistance; 
            document.getElementById('editAllocatedGas').value = card.dataset.allocatedgas; 
            document.getElementById('editEstimatedTimeOfArrival').value = card.dataset.eta; 
            document.getElementById('editDeliveryModal').style.display = 'block'; 
        } 
        
        function confirmDeleteDelivery(id, name, status) { 
            if (status === 'In Progress' || status === 'Active' || status === 'On Route') { 
                alert('Cannot delete an active delivery. Please cancel it first.'); 
                return; 
            } 
            if (confirm(`Delete delivery "${name}" (ID: ${id})? This action cannot be undone.`)) { 
                const form = document.createElement('form'); 
                form.method = 'POST'; 
                form.action = 'AdminPage.php?view=deliveries'; 
                const input = document.createElement('input'); 
                input.type = 'hidden'; 
                input.name = 'delete_delivery_id'; 
                input.value = id; 
                form.appendChild(input); 
                document.body.appendChild(form); 
                form.submit(); 
            } 
        } 

        // --- HISTORY REPORTS JS (unchanged) --- 
        function openEditGasModal(buttonElement) { 
            const card = buttonElement.closest('.report-card'); 
            const reportID = card.dataset.historyid; 
            const allocatedGas = card.dataset.allocatedgas; 
            const gasUsed = card.dataset.gasused; 
            document.getElementById('gasReportID').value = reportID; 
            document.getElementById('gasReportIDDisplay').textContent = reportID; 
            document.getElementById('allocatedGasDisplay').textContent = allocatedGas + ' L'; 
            document.getElementById('gasUsed').value = gasUsed; // Pre-fill if already set 
            document.getElementById('gasUsedModal').style.display = 'block'; 
        } 

        // --- GOOGLE MAPS JS (unchanged) ---
        let map, directionsService, directionsRenderer; 
        let currentMapMode = 'add'; 

        function initMap() { /* ... (function body omitted for brevity) ... */ } 
        function openGoogleMapModal(mode) { /* ... (function body omitted for brevity) ... */ } 
        function calculateRoute() { /* ... (function body omitted for brevity) ... */ } 
        function confirmMapSelection() { /* ... (function body omitted for brevity) ... */ }
    </script> 
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCe0QPh_Jshd8UqAUsqYSrNmg7itHuzv0w&libraries=places&callback=initMap" async defer></script> 
    <script> 
        function initMap() { 
            directionsService = new google.maps.DirectionsService();
            directionsRenderer = new google.maps.DirectionsRenderer({ suppressMarkers: false });
            map = new google.maps.Map(document.getElementById("googleMapContainer"), {
                zoom: 7,
                center: { lat: 12.8797, lng: 121.7740 } // Center of the Philippines
            });
            directionsRenderer.setMap(map);
            
            // Autocomplete setup for inputs
            const originInput = document.getElementById("mapOriginInput");
            const destInput = document.getElementById("mapDestInput");
            const originAutocomplete = new google.maps.places.Autocomplete(originInput);
            const destAutocomplete = new google.maps.places.Autocomplete(destInput);
        }

        function openGoogleMapModal(mode) {
            currentMapMode = mode;
            // Clear previous inputs/data
            document.getElementById("mapOriginInput").value = "";
            document.getElementById("mapDestInput").value = "";
            document.getElementById("mapStats").innerHTML = "";
            if (directionsRenderer) directionsRenderer.set('directions', null);
            
            // Pre-fill fields from the current modal if in edit mode
            if (mode === 'edit') {
                document.getElementById("mapOriginInput").value = document.getElementById("editOrigin").value;
                document.getElementById("mapDestInput").value = document.getElementById("editDestination").value;
                // Temporarily hide the original modal
                document.getElementById('editDeliveryModal').style.display = 'none';
            } else {
                document.getElementById("mapOriginInput").value = document.getElementById("origin").value;
                document.getElementById("mapDestInput").value = document.getElementById("destination").value;
                // Temporarily hide the original modal
                document.getElementById('addDeliveryModal').style.display = 'none';
            }

            document.getElementById('googleMapModal').style.display = 'block';
            setTimeout(function() {
                google.maps.event.trigger(map, 'resize');
                // Attempt to re-calculate route if fields were pre-filled
                if (document.getElementById("mapOriginInput").value && document.getElementById("mapDestInput").value) {
                    calculateRoute();
                }
            }, 300);
        }
        
        function calculateRoute() {
            const origin = document.getElementById("mapOriginInput").value;
            const destination = document.getElementById("mapDestInput").value;

            if (!origin || !destination) {
                document.getElementById("mapStats").innerHTML = "<span style='color:red;'>Please enter both origin and destination.</span>";
                return;
            }

            directionsService.route(
                {
                    origin: origin,
                    destination: destination,
                    travelMode: google.maps.TravelMode.DRIVING,
                },
                (response, status) => {
                    if (status === "OK") {
                        directionsRenderer.setDirections(response);
                        const route = response.routes[0].legs[0];
                        const distance = route.distance.text;
                        const duration = route.duration.text;
                        const arrivalTime = new Date(new Date().getTime() + route.duration.value * 1000);
                        
                        document.getElementById("mapStats").innerHTML = `Distance: <strong>${distance}</strong> | Travel Time: <strong>${duration}</strong> | Estimated Arrival: <strong>${arrivalTime.toLocaleTimeString()}</strong>`;
                    } else {
                        document.getElementById("mapStats").innerHTML = "<span style='color:red;'>Could not calculate route. Please try more specific locations.</span>";
                        directionsRenderer.set('directions', null);
                    }
                }
            );
        }
        
        function confirmMapSelection() {
            const directions = directionsRenderer.getDirections();
            if (!directions) {
                alert("Please calculate a route first.");
                return;
            }
            
            const route = directions.routes[0].legs[0];
            const originVal = route.start_address;
            const destVal = route.end_address;
            const calculatedDistance = route.distance.text;
            const durationInSeconds = route.duration.value;
            
            // Calculate ETA
            const arrivalTime = new Date(new Date().getTime() + durationInSeconds * 1000);
            const year = arrivalTime.getFullYear();
            const month = String(arrivalTime.getMonth() + 1).padStart(2, '0');
            const day = String(arrivalTime.getDate()).padStart(2, '0');
            const hours = String(arrivalTime.getHours()).padStart(2, '0');
            const minutes = String(arrivalTime.getMinutes()).padStart(2, '0');
            const formattedETA = `${year}-${month}-${day}T${hours}:${minutes}`;

            // CHECK MODE AND POPULATE CORRECT FIELDS
            if (currentMapMode === 'edit') {
                // Populate EDIT Modal Fields
                document.getElementById("editOrigin").value = originVal;
                document.getElementById("editDestination").value = destVal;
                document.getElementById("editDeliveryDistance").value = calculatedDistance;
                document.getElementById("editEstimatedTimeOfArrival").value = formattedETA;
                
                // Ensure Edit modal stays visible
                document.getElementById('editDeliveryModal').style.display = 'block';
            } else {
                // Populate ADD Modal Fields
                document.getElementById("origin").value = originVal;
                document.getElementById("destination").value = destVal;
                document.getElementById("deliveryDistance").value = calculatedDistance;
                document.getElementById("estimatedTimeOfArrival").value = formattedETA;
                
                // Ensure Add modal stays visible
                document.getElementById('addDeliveryModal').style.display = 'block';
            }

            // Close Map Modal
            document.getElementById('googleMapModal').style.display = 'none';
            
            // Clear map inputs for next time (optional)
            document.getElementById("mapOriginInput").value = "";
            document.getElementById("mapDestInput").value = "";
            document.getElementById("mapStats").innerHTML = "";
            directionsRenderer.set('directions', null);
        }
    </script>
</body>
</html>