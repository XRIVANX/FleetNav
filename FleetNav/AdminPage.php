<?php
include("connect.php");

// Determine View (Must be defined before includes so they can check $view)
$view = isset($_GET['view']) ? $_GET['view'] : 'dashboard';
$message = ''; 

// 1. User/Driver Logic
include('AdminPageUser.php');

// 2. Truck Logic
include('AdminPageTruck.php');

// 3. Delivery Logic
include('AdminPageDelivery.php');

// 4. Dashboard Logic (New Include)
include('AdminPageDashboard.php');

// Security Check: Only allow 'Admin' users
if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Admin') {
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

// Profile Image Logic
if (!empty($profileFilename)) {
    $profileImgSrc = BASE_URL . "uploads/" . $profileFilename;
} else {
    $profileImgSrc = BASE_URL . "assets/default-user.png";
}
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
                    <img src="<?php echo $profileImgSrc; ?>" alt="Profile" class="profile-icon">
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
                            <h3>Driver Availability (Total: <?php echo $totalDrivers; ?>)</h3>
                            <div class="chart-container"><canvas id="driverChart"></canvas></div>
                            <div class="chart-legend">
                                <span>Available: <?php echo $driversData['available_drivers']; ?> (<?php echo $drivers_avail_percent; ?>%)</span>
                                <span>Unavailable: <?php echo $driversData['unavailable_drivers']; ?> (<?php echo $drivers_unavail_percent; ?>%)</span>
                            </div>
                        </div>
                        <div class="chart-widget">
                            <h3>Truck Availability (Total: <?php echo $totalTrucks; ?>)</h3>
                            <div class="chart-container"><canvas id="truckChart"></canvas></div>
                            <div class="chart-legend">
                                <span>Available: <?php echo $trucksData['available_trucks']; ?> (<?php echo $trucks_avail_percent; ?>%)</span>
                                <span>Unavailable: <?php echo $trucksData['unavailable_trucks']; ?> (<?php echo $trucks_unavail_percent; ?>%)</span>
                            </div>
                        </div>
                        <div class="chart-widget">
                            <h3>Delivery Status (Total: <?php echo $totalDeliveries; ?>)</h3>
                            <div class="chart-container"><canvas id="deliveryChart"></canvas></div>
                            <div class="chart-legend">
                                <span>In Progress: <?php echo $deliveriesData['deliveries_in_progress']; ?> (<?php echo $deliveries_in_percent; ?>%)</span>
                                <span>Not In Progress: <?php echo $deliveriesData['deliveries_not_in_progress']; ?> (<?php echo $deliveries_not_in_percent; ?>%)</span>
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
                            <div class="empty-state"><p>No drivers registered in the fleet yet.</p></div>
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
                                            <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                        </div>
                                        <div class="driver-details">
                                            <p><strong>ID:</strong> <?php echo htmlspecialchars($driver['accountID']); ?></p>
                                            <p><strong>Username:</strong> <?php echo htmlspecialchars($driver['username']); ?></p>
                                            <p><strong>Email:</strong> <?php echo htmlspecialchars($driver['email']); ?></p>
                                            <p><strong>Assigned Truck:</strong> <?php echo $assignedTruck; ?></p>
                                        </div>
                                        <div class="driver-actions">
                                            <button class="icon-btn edit-btn" title="Edit Driver" onclick="openEditDriverModal(this)"><i class="fas fa-edit"></i> Edit</button>
                                            <button class="icon-btn delete-btn" title="Delete Driver" onclick="confirmDeleteDriver(<?php echo htmlspecialchars($driver['accountID']); ?>, '<?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?>', <?php echo $isAssigned ? 'true' : 'false'; ?>)"><i class="fas fa-trash"></i> Delete</button>
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
                                            <button class="icon-btn edit-btn" title="Edit Truck" onclick="openEditTruckModal(this)"><i class="fa sfa-edit"></i> Edit</button>
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
            <button class="action-btn" onclick="document.getElementById('addDeliveryModal').style.display='block';">➕ Schedule Delivery</button>
        </div>
        
        <?php if (empty($deliveriesList)): ?>
            <div class="empty-state"><p>No deliveries scheduled.</p></div>
        <?php else: ?>
                            <div class="card-list">
                                <?php 
                                    $index = 1; 
                                    foreach ($deliveriesList as $delivery): 
                                    // FORMATTING: Convert DB datetime to HTML5 input format (YYYY-MM-DDTHH:MM)
                                    $formattedDate = date('Y-m-d\TH:i', strtotime($delivery['estimatedTimeOfArrival']));
                                    $status = htmlspecialchars($delivery['deliveryStatus']);

                                    // Determine if editing should be disabled
                                    $editDisabled = ($status == 'Completed') ? 'disabled' : '';
                                    
                                    // Prepare data attributes for the Edit Modal
                                    $dataAttributes = "
    data-deliveryid='{$delivery['deliveryID']}'
    data-productname='" . htmlspecialchars($delivery['productName'] ?? '', ENT_QUOTES) . "'
    data-productdescription='" . htmlspecialchars($delivery['productDescription'] ?? '', ENT_QUOTES) . "'
    data-origin='" . htmlspecialchars($delivery['origin'] ?? '', ENT_QUOTES) . "'
    data-destination='" . htmlspecialchars($delivery['destination'] ?? '', ENT_QUOTES) . "'
    data-deliverydistance='" . htmlspecialchars($delivery['deliveryDistance'] ?? '0', ENT_QUOTES) . "' 👈 Make sure this line is there
    data-assignedtruck='" . ($delivery['assignedTruck'] ?? '0') . "' 
    data-eta='" . $formattedDate . "'
    data-status='" . $status . "'
";
                                    
                                    $deleteDisabled = ($status == 'Active' || $status == 'On Route') ? 'disabled' : '';
                                ?>
                                <div class="delivery-card card-item" <?= $dataAttributes ?>>
                                    <div class="card-details">
                                        <h3>#<?= $index ?>: <?= htmlspecialchars($delivery['productName']) ?></h3>
                                        <p><strong>Status: </strong> <span class="badge status-<?= strtolower(str_replace(' ', '-', $status)) ?>"><?= $status ?></span></p>
                                        <p><strong>Route: </strong> <?= htmlspecialchars($delivery['origin']) ?> &rarr; <?= htmlspecialchars($delivery['destination']) ?></p>
                                        <p><strong>Distance: </strong><?= htmlspecialchars(string: $delivery['deliveryDistance'])?></p>
                                        <p><strong>Truck: </strong> <?= $delivery['truckName'] ? htmlspecialchars($delivery['truckName']) . ' (' . $delivery['plateNumber'] . ')' : '<span class="text-unassigned">Unassigned</span>' ?></p>
                                        <p><strong>ETA: </strong> <?= date('M j, Y h:i A', strtotime($delivery['estimatedTimeOfArrival'])) ?></p>
                                    </div>
                                    
                                   <div class="card-actions">
    <button class="icon-btn edit-btn" <?= $editDisabled ?> onclick="openEditDeliveryModal(this)">
        <i class="fas fa-edit"></i> Edit
    </button>
    <button class="icon-btn delete-btn" <?= $deleteDisabled ?> 
        onclick="confirmDeleteDelivery(<?= $delivery['deliveryID'] ?>, '<?= htmlspecialchars($delivery['productName'], ENT_QUOTES) ?>', '<?= $status ?>')">
        <i class="fas fa-trash"></i> Delete
    </button>
</div>
                                </div>
                                <?php 
                                    $index++; 
                                    endforeach; 
                                ?>
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
                        <div class="form-group"><label for="firstName">First Name *</label><input type="text" id="firstName" name="firstName" required></div>
                        <div class="form-group"><label for="lastName">Last Name *</label><input type="text" id="lastName" name="lastName" required></div>
                    </div>
                    <div class="form-group"><label for="username">Username *</label><input type="text" id="username" name="username" required></div>
                    <div class="form-group"><label for="email">Email *</label><input type="email" id="email" name="email" required></div>
                    <div class="form-group"><label for="password">Password *</label><input type="password" id="password" name="password" required><small class="form-hint">A strong password is required for security.</small></div>
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
                        <div class="form-group"><label for="editFirstName">First Name *</label><input type="text" id="editFirstName" name="editFirstName" required></div>
                        <div class="form-group"><label for="editLastName">Last Name *</label><input type="text" id="editLastName" name="editLastName" required></div>
                    </div>
                    <div class="form-group"><label for="editUsername">Username *</label><input type="text" id="editUsername" name="editUsername" required></div>
                    <div class="form-group"><label for="editEmail">Email *</label><input type="email" id="editEmail" name="editEmail" required></div>
                    <div class="form-group"><label for="editPassword">New Password</label><input type="password" id="editPassword" name="editPassword"><small class="form-hint">Only fill if changing.</small></div>
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
                                <option value="Available">Available</option><option value="Unavailable">Unavailable</option><option value="In Transit">In Transit</option><option value="Maintenance">Maintenance</option>
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
                    <div class="form-group">
                        <label for="truckImage">Truck Image</label>
                        <div class="upload-container">
                            <div id="dropArea" class="drop-area"><p>Drag & Drop or <span id="selectFileLink">select a file</span></p></div>
                            <input type="file" id="truckImage" name="truckImage" accept="image/*" style="display: none;">
                            <div id="previewContainer" class="preview-container" style="display: none;"><img id="previewImage" src=""><button type="button" id="clearImageBtn" class="clear-image-btn">Clear Image</button></div>
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
                            <select id="editTruckStatus" name="editTruckStatus" required><option value="Available">Available</option><option value="Unavailable">Unavailable</option><option value="In Transit">In Transit</option><option value="Maintenance">Maintenance</option></select>
                        </div>
                        <div class="form-group">
                            <label for="editAssignedDriver">Assigned Driver</label>
                            <select id="editAssignedDriver" name="editAssignedDriver">
                                <option value="0">--- Unassigned ---</option>
                                <?php foreach ($allDrivers as $driver): ?><option value="<?php echo htmlspecialchars($driver['accountID']); ?>"><?php echo htmlspecialchars($driver['firstName'] . ' ' . $driver['lastName']); ?></option><?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="editTruckImage">Truck Image</label>
                        <div class="upload-container">
                            <div id="editDropArea" class="drop-area"><p>Drag & Drop or <span id="editSelectFileLink">select a file</span></p></div>
                            <input type="file" id="editTruckImage" name="editTruckImage" accept="image/*" style="display: none;">
                            <div id="editPreviewContainer" class="preview-container" style="display: none;"><img id="editPreviewImage" src=""><p id="editCurrentPathText">Current Image: <span id="editCurrentPathName"></span></p><button type="button" id="editClearImageBtn" class="clear-image-btn">Remove/Change Image</button></div>
                        </div>
                    </div>
                    <button type="submit" name="edit_truck_submit" class="submit-btn green-btn">SAVE CHANGES</button>
                </form>
            </div>
        </div>
    <?php endif; ?>

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
                    <div class="form-group"><label for="origin">Origin</label><input type="text" id="origin" name="origin" required></div>                   
                    <div class="form-group"><label for="destination">Destination</label><input type="text" id="destination" name="destination" required></div>  
                </div>
                <div class="form-group">
                    <label for="deliveryDistance">Delivery Distance</label><input type="text" id="deliveryDistance" name="deliveryDistance" required>
                    <label for="assignedTruck">Assign Truck (Available Only)</label>
                    <select id="assignedTruck" name="assignedTruck">
                        <option value="0">--- Unassigned ---</option>
                        <?php foreach ($availableTrucks as $truck): ?>
                            <option value="<?= $truck['truckID'] ?>">
                                <?= htmlspecialchars($truck['truckName'] . ' (' . $truck['plateNumber'] . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" name="add_delivery_submit" class="submit-btn">SCHEDULE DELIVERY</button>
            </form>
        </div>
    </div>

    <div id="editDeliveryModal" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="document.getElementById('editDeliveryModal').style.display='none';">&times;</span>
            <h2>Edit Delivery (ID: <span id="editDeliveryIDDisplay"></span>)</h2>
            <form class="data-form" id="editDeliveryForm" method="POST">
                <input type="hidden" id="editDeliveryID" name="editDeliveryID">
                
                <div class="form-row">
                    <div class="form-group"><label for="editProductName">Product Name *</label><input type="text" id="editProductName" name="editProductName" required></div>
                    <div class="form-group"><label for="editEstimatedTimeOfArrival">ETA *</label><input type="datetime-local" id="editEstimatedTimeOfArrival" name="editEstimatedTimeOfArrival" required></div>
                </div>
                
                <div class="form-group"><label for="editProductDescription">Description</label><textarea id="editProductDescription" name="editProductDescription" rows="2"></textarea></div>
                
                <div class="form-row">
                    <div class="form-group"><label for="editOrigin">Origin *</label><input type="text" id="editOrigin" name="editOrigin" required></div>
                    <div class="form-group"><label for="editDestination">Destination *</label><input type="text" id="editDestination" name="editDestination" required></div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="editDeliveryDistance">Delivery Distance *</label><input type="text" id="editDeliveryDistance" name="editDeliveryDistance" required> 
                        <label for="editAssignedTruck">Assigned Truck</label>
                        <select id="editAssignedTruck" name="editAssignedTruck">
                            <option value="0">--- Unassigned ---</option>
                            <?php foreach ($allTrucks as $truck): 
                                // Show truck status in dropdown so admin knows if they are assigning a busy truck
                                $statusLabel = ($truck['truckStatus'] !== 'Available') ? " [{$truck['truckStatus']}]" : "";
                            ?>
                                <option value="<?= $truck['truckID'] ?>">
                                    <?= htmlspecialchars($truck['truckName'] . ' (' . $truck['plateNumber'] . ')' . $statusLabel) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="editDeliveryStatus">Status *</label>
                        <select id="editDeliveryStatus" name="editDeliveryStatus" required>
                            <option value="Inactive">Inactive</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="edit_delivery_submit" class="submit-btn green-btn">SAVE CHANGES</button>
            </form> </div>
    </div>

    <?php if ($view === 'dashboard'): ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script>
            function createPieChart(chartId, label1, data1, label2, data2, color1, color2) {
                const totalData = data1 + data2;
                if (totalData === 0) {
                    const container = document.getElementById(chartId).parentNode;
                    container.innerHTML = '<p style="text-align:center; padding-top: 50px; color:#555;">No data available.</p>';
                    return;
                }
                const ctx = document.getElementById(chartId).getContext('2d');
                new Chart(ctx, { type: 'pie', data: { labels: [label1, label2], datasets: [{ data: [data1, data2], backgroundColor: [color1, color2], hoverOffset: 4 }] }, options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { callbacks: { label: function(context) { let label = context.label || ''; if (label) { label += ': '; } if (context.parsed !== null) { label += context.parsed + '%'; } return label; } } } } } });
            }
            const driverData = { available: <?php echo $drivers_avail_percent; ?>, unavailable: <?php echo $drivers_unavail_percent; ?> };
            const truckData = { available: <?php echo $trucks_avail_percent; ?>, unavailable: <?php echo $trucks_unavail_percent; ?> };
            const deliveryData = { inProgress: <?php echo $deliveries_in_percent; ?>, notInProgress: <?php echo $deliveries_not_in_percent; ?> };
            const PRIMARY_BLUE = '#007bff'; const LIGHT_GREY = '#ced4da';
            createPieChart('driverChart', 'Available', driverData.available, 'Unavailable', driverData.unavailable, PRIMARY_BLUE, LIGHT_GREY);
            createPieChart('truckChart', 'Available', truckData.available, 'Unavailable', truckData.unavailable, PRIMARY_BLUE, LIGHT_GREY);
            createPieChart('deliveryChart', 'In Progress', deliveryData.inProgress, 'Not In Progress', deliveryData.notInProgress, PRIMARY_BLUE, LIGHT_GREY);
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
            }
        }

        const BASE_URL = '<?php echo BASE_URL; ?>';
        const UPLOADS_PATH = BASE_URL + 'uploads/';
        const DEFAULT_IMG_PATH = BASE_URL + 'assets/default-truck.png';
        function preventDefaults(e) { e.preventDefault(); e.stopPropagation(); }

        // --- TRUCK UPLOAD JS ---
        const dropArea = document.getElementById('dropArea');
        const selectFileLink = document.getElementById('selectFileLink');
        const fileInput = document.getElementById('truckImage');
        const previewContainer = document.getElementById('previewContainer');
        const previewImage = document.getElementById('previewImage');
        const clearImageBtn = document.getElementById('clearImageBtn');
        const uploadedImagePathInput = document.getElementById('uploadedImagePath');
        function showPreview(file) { if (file && file.type.startsWith('image/')) { const reader = new FileReader(); reader.onload = function(e) { previewImage.src = e.target.result; dropArea.style.display = 'none'; previewContainer.style.display = 'block'; }; reader.readAsDataURL(file); uploadFile(file); } }
        function clearSelection() { fileInput.value = ''; previewImage.src = ''; previewContainer.style.display = 'none'; dropArea.style.display = 'block'; uploadedImagePathInput.value = ''; }
        function uploadFile(file) { const formData = new FormData(); formData.append('image', file); uploadedImagePathInput.value = 'UPLOADING...'; fetch('upload.php', { method: 'POST', body: formData }).then(response => { if (!response.ok) throw new Error('Network response'); return response.json(); }).then(data => { if (data.status === 'success') { uploadedImagePathInput.value = data.file; } else { alert('Upload failed: ' + data.message); clearSelection(); } }).catch(error => { console.error('Error:', error); alert('Error uploading.'); clearSelection(); }); }
        if (selectFileLink) selectFileLink.addEventListener('click', () => { fileInput.click(); });
        if (fileInput) fileInput.addEventListener('change', (e) => { showPreview(e.target.files[0]); });
        if (clearImageBtn) clearImageBtn.addEventListener('click', clearSelection);
        if (dropArea) { ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { dropArea.addEventListener(eventName, preventDefaults, false); }); ['dragenter', 'dragover'].forEach(eventName => { dropArea.addEventListener(eventName, () => { dropArea.classList.add('dragover'); }, false); }); ['dragleave', 'drop'].forEach(eventName => { dropArea.addEventListener(eventName, () => { dropArea.classList.remove('dragover'); }, false); }); dropArea.addEventListener('drop', (e) => { let dt = e.dataTransfer; let files = dt.files; if (files.length) { fileInput.files = files; showPreview(files[0]); } }, false); }

        function confirmDelete(id, name) {
            if (confirm(`Delete truck "${name}" (ID: ${id})? Action cannot be undone.`)) {
                const form = document.createElement('form'); form.method = 'POST'; form.action = 'AdminPage.php?view=trucks';
                const input = document.createElement('input'); input.type = 'hidden'; input.name = 'delete_truck_id'; input.value = id;
                form.appendChild(input); document.body.appendChild(form); form.submit();
            }
        }
        
        // --- EDIT TRUCK JS ---
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
            editTruckIDInput.value = card.dataset.truckid;
            editTruckIDDisplay.textContent = card.dataset.truckid;
            document.getElementById('editTruckName').value = card.dataset.truckname;
            document.getElementById('editPlateNumber').value = card.dataset.plate;
            document.getElementById('editTruckStatus').value = card.dataset.status;
            document.getElementById('editOdometerOrMileage').value = card.dataset.odometer;
            document.getElementById('editRegistrationDate').value = card.dataset.regdate;
            document.getElementById('editAssignedDriver').value = card.dataset.driverid === '0' ? '0' : card.dataset.driverid;
            const currentImgPath = card.dataset.img;
            editUploadedImagePathInput.value = ''; editFileInput.value = ''; editCurrentTruckImgInput.value = currentImgPath;
            if (currentImgPath && currentImgPath !== 'null') { editPreviewImage.src = UPLOADS_PATH + currentImgPath; editCurrentPathName.textContent = currentImgPath; editDropArea.style.display = 'none'; } else { editPreviewImage.src = DEFAULT_IMG_PATH; editCurrentPathName.textContent = 'None'; editDropArea.style.display = 'block'; }
            editPreviewContainer.style.display = 'block'; document.getElementById('editTruckModal').style.display = 'block';
        }
        function editShowPreview(file) { if (file && file.type.startsWith('image/')) { const reader = new FileReader(); reader.onload = function(e) { editPreviewImage.src = e.target.result; editCurrentPathName.textContent = 'New File Selected (Uploading...)'; editDropArea.style.display = 'none'; editPreviewContainer.style.display = 'block'; }; reader.readAsDataURL(file); editUploadFile(file); } }
        function editClearSelection() { editFileInput.value = ''; editPreviewImage.src = DEFAULT_IMG_PATH; editCurrentPathName.textContent = 'None'; editUploadedImagePathInput.value = 'null'; editCurrentTruckImgInput.value = 'null'; editDropArea.style.display = 'block'; }
        function editUploadFile(file) { const formData = new FormData(); formData.append('image', file); editUploadedImagePathInput.value = 'UPLOADING...'; fetch('upload.php', { method: 'POST', body: formData }).then(response => response.json()).then(data => { if (data.status === 'success') { editUploadedImagePathInput.value = data.file; editCurrentPathName.textContent = `New File: ${data.file}`; } else { alert('Upload failed: ' + data.message); editClearSelection(); } }).catch(error => { console.error('Error:', error); alert('Error uploading.'); editClearSelection(); }); }
        if (editModal) { if (editSelectFileLink) editSelectFileLink.addEventListener('click', () => { editFileInput.click(); }); if (editFileInput) editFileInput.addEventListener('change', (e) => { editShowPreview(e.target.files[0]); }); if (editClearImageBtn) editClearImageBtn.addEventListener('click', editClearSelection); if (editDropArea) { ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => { editDropArea.addEventListener(eventName, preventDefaults, false); }); ['dragenter', 'dragover'].forEach(eventName => { editDropArea.addEventListener(eventName, () => { editDropArea.classList.add('dragover'); }, false); }); ['dragleave', 'drop'].forEach(eventName => { editDropArea.addEventListener(eventName, () => { editDropArea.classList.remove('dragover'); }, false); }); editDropArea.addEventListener('drop', (e) => { let dt = e.dataTransfer; let files = dt.files; if (files.length) { editFileInput.files = files; editShowPreview(files[0]); } }, false); } }

        // --- DELIVERY CRUD JS ---
function openEditDeliveryModal(buttonElement) {
    // 1. Find the closest parent element with the class 'delivery-card'
    const card = buttonElement.closest('.delivery-card'); 
    document.getElementById('editDeliveryID').value = card.dataset.deliveryid;
    document.getElementById('editDeliveryIDDisplay').textContent = card.dataset.deliveryid;
    document.getElementById('editProductName').value = card.dataset.productname;
    document.getElementById('editEstimatedTimeOfArrival').value = card.dataset.eta;
    document.getElementById('editProductDescription').value = card.dataset.productdescription;
    document.getElementById('editOrigin').value = card.dataset.origin;
    document.getElementById('editDestination').value = card.dataset.destination;
    document.getElementById('editDeliveryDistance').value = card.dataset.deliverydistance;
    document.getElementById('editDeliveryStatus').value = card.dataset.deliverystatus;
    document.getElementById('editAssignedTruck').value = card.dataset.assignedtruck;
    document.getElementById('editDeliveryModal').style.display = 'block';
}

function confirmDeleteDelivery(id, name, status) {
    if (status === 'Active' || status === 'On Route') {
        alert(`Cannot delete delivery "${name}" (ID: ${id}). Its status is ${status}.`);
        return;
    }
    if (confirm(`Delete delivery "${name}" (ID: ${id})? This action is irreversible.`)) {
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

        // --- DRIVER CRUD JS ---
        function confirmDeleteDriver(id, name, isAssigned) {
            if (isAssigned) { alert(`Cannot delete ${name} (ID: ${id}). Driver is assigned to a truck/delivery.`); return; }
            if (confirm(`Delete driver "${name}" (ID: ${id})?`)) {
                const form = document.createElement('form'); form.method = 'POST'; form.action = 'AdminPage.php?view=drivers';
                const input = document.createElement('input'); input.type = 'hidden'; input.name = 'delete_driver_id'; input.value = id;
                form.appendChild(input); document.body.appendChild(form); form.submit();
            }
        }
        function openEditDriverModal(buttonElement) {
            const card = buttonElement.closest('.driver-card');
            document.getElementById('editAccountID').value = card.dataset.accountid;
            document.getElementById('editDriverIDDisplay').textContent = card.dataset.accountid;
            document.getElementById('editFirstName').value = card.dataset.firstname;
            document.getElementById('editLastName').value = card.dataset.lastname;
            document.getElementById('editUsername').value = card.dataset.username;
            document.getElementById('editEmail').value = card.dataset.email;
            document.getElementById('editPassword').value = '';
            document.getElementById('editDriverModal').style.display = 'block';
        }
    </script>
</body>
</html>