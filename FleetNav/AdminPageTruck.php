<?php
// =========================================================
//                  HELPER: HANDLE FILE UPLOAD
// =========================================================
function handleFileUpload($fileInputName) {
    if (!isset($_FILES[$fileInputName]) || $_FILES[$fileInputName]['error'] != 0) {
        return ['success' => false, 'filename' => null];
    }

    $target_dir = "uploads/";
    // Create dir if it doesn't exist
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0775, true);
    }

    $fileInfo = pathinfo($_FILES[$fileInputName]["name"]);
    $imgName = $fileInfo['filename'];
    $imgExt = strtolower($fileInfo['extension']);
    
    // Generate unique name to prevent overwriting
    $newFilename = $imgName . "_" . time() . "." . $imgExt;
    $target_file = $target_dir . $newFilename;

    $allowed = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($imgExt, $allowed) && $_FILES[$fileInputName]["size"] <= 5000000) {
        if (move_uploaded_file($_FILES[$fileInputName]["tmp_name"], $target_file)) {
            return ['success' => true, 'filename' => $newFilename];
        }
    }
    return ['success' => false, 'filename' => null];
}

// =========================================================
//                  HANDLE TRUCK ADDITION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_truck_submit'])) {
    
    $truckName = trim($_POST['truckName'] ?? '');
    $plateNumber = trim($_POST['plateNumber'] ?? '');
    $truckStatus = $_POST['truckStatus'] ?? 'Available';
    $odometer = (int)($_POST['odometerOrMileage'] ?? 0);
    $registrationDate = $_POST['registrationDate'] ?? null;
    $assignedDriverID = empty($_POST['assignedDriver']) || $_POST['assignedDriver'] === '0' ? null : (int)$_POST['assignedDriver'];

    // 1. Handle Image Upload Direct Processing
    $truckImgPath = null;
    $uploadResult = handleFileUpload('truckImage'); // Check $_FILES['truckImage']
    if ($uploadResult['success']) {
        $truckImgPath = $uploadResult['filename'];
    }

    // 2. Basic Validation
    if (empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        $canProceed = true;

        // 3a. CHECK: Is this driver already assigned?
        if ($assignedDriverID !== null) {
            $checkStmt = $conn->prepare("SELECT truckName FROM Trucks WHERE assignedDriver = ?");
            $checkStmt->bind_param("i", $assignedDriverID);
            $checkStmt->execute();
            if ($row = $checkStmt->get_result()->fetch_assoc()) {
                $message = '<div class="alert alert-danger">Error: Driver already assigned to <b>' . htmlspecialchars($row['truckName']) . '</b>.</div>';
                $canProceed = false;
            }
            $checkStmt->close();
        }

        // 3b. CHECK: Does this plate number already exist?
        if ($canProceed) {
            $checkPlateStmt = $conn->prepare("SELECT truckID FROM Trucks WHERE plateNumber = ?");
            $checkPlateStmt->bind_param("s", $plateNumber);
            $checkPlateStmt->execute();
            if ($checkPlateStmt->get_result()->num_rows > 0) {
                $message = '<div class="alert alert-danger">Error: A truck with Plate Number <b>' . htmlspecialchars($plateNumber) . '</b> already exists.</div>';
                $canProceed = false;
            }
            $checkPlateStmt->close();
        }

        // 4. Insert
        if ($canProceed) {
            $stmt = null;
            if ($assignedDriverID === null) {
                $sql = "INSERT INTO Trucks (truckName, plateNumber, truckStatus, odometerOrMileage, registrationDate, truckImg) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) $stmt->bind_param("sssiss", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $truckImgPath);
            } else {
                $sql = "INSERT INTO Trucks (truckName, plateNumber, truckStatus, odometerOrMileage, registrationDate, assignedDriver, truckImg) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) $stmt->bind_param("sssisis", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $assignedDriverID, $truckImgPath);
            }

            if ($stmt && $stmt->execute()) {
                $newTruckID = $stmt->insert_id;
                logAction($conn, "ADD_TRUCK", "Added truck '$truckName' ($plateNumber). ID: $newTruckID");
                $message = '<div class="alert alert-success">Truck **' . htmlspecialchars($truckName) . '** added successfully!</div>';
            } else {
                $error_msg = $stmt ? $stmt->error : $conn->error;
                $message = '<div class="alert alert-danger">Failed to add truck. ' . $error_msg . '</div>';
            }
            if ($stmt) $stmt->close();
        }
    }
    $view = 'trucks';
}

// =========================================================
//                  HANDLE TRUCK UPDATES (EDIT)
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_truck_submit'])) {
    
    $truckID = (int)($_POST['editTruckID'] ?? 0);
    $truckName = trim($_POST['editTruckName'] ?? '');
    $plateNumber = trim($_POST['editPlateNumber'] ?? '');
    $truckStatus = $_POST['editTruckStatus'] ?? 'Available';
    $odometer = (int)($_POST['editOdometerOrMileage'] ?? 0);
    $registrationDate = $_POST['editRegistrationDate'] ?? null;
    $assignedDriverID = empty($_POST['editAssignedDriver']) || $_POST['editAssignedDriver'] === '0' ? null : (int)$_POST['editAssignedDriver'];

    // --- Image Logic ---
    // 1. Start with current image in DB (passed via hidden input)
    $finalTruckImgPath = trim($_POST['editCurrentTruckImg'] ?? '');
    if ($finalTruckImgPath === 'null' || empty($finalTruckImgPath)) $finalTruckImgPath = null;

    // 2. Check if a NEW file was uploaded
    $uploadResult = handleFileUpload('editTruckImage');
    if ($uploadResult['success']) {
        $finalTruckImgPath = $uploadResult['filename']; // Replace with new file
    }
    
    // 3. Check if user Explicitly Removed the image (JavaScript sets this hidden field to 'null')
    // We check $_POST['editUploadedImagePath'] as a flag for "User clicked Remove"
    if (isset($_POST['editUploadedImagePath']) && $_POST['editUploadedImagePath'] === 'null') {
        $finalTruckImgPath = null;
    }

    if ($truckID <= 0 || empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        $canProceed = true;

        // Check Driver Assignment Conflict
        if ($assignedDriverID !== null) {
            $checkStmt = $conn->prepare("SELECT truckName FROM Trucks WHERE assignedDriver = ? AND truckID != ?");
            $checkStmt->bind_param("ii", $assignedDriverID, $truckID);
            $checkStmt->execute();
            if ($row = $checkStmt->get_result()->fetch_assoc()) {
                $message = '<div class="alert alert-danger">Error: Driver already assigned to <b>' . htmlspecialchars($row['truckName']) . '</b>.</div>';
                $canProceed = false;
            }
            $checkStmt->close();
        }

        // 3b. CHECK: Does this plate number already exist (on a DIFFERENT truck)?
        if ($canProceed) {
            $checkPlateStmt = $conn->prepare("SELECT truckID FROM Trucks WHERE plateNumber = ? AND truckID != ?");
            $checkPlateStmt->bind_param("si", $plateNumber, $truckID);
            $checkPlateStmt->execute();
            if ($checkPlateStmt->get_result()->num_rows > 0) {
                $message = '<div class="alert alert-danger">Error: A truck with Plate Number <b>' . htmlspecialchars($plateNumber) . '</b> already exists.</div>';
                $canProceed = false;
            }
            $checkPlateStmt->close();
        }

        if ($canProceed) {
            // Status Change Logic (Unassign Deliveries)
            $unassignMsg = "";
            if ($truckStatus === 'Unavailable' || $truckStatus === 'Maintenance') {
                $chkDelStmt = $conn->prepare("SELECT deliveryID FROM Deliveries WHERE assignedTruck = ?");
                $chkDelStmt->bind_param("i", $truckID);
                $chkDelStmt->execute();
                $chkDelResult = $chkDelStmt->get_result();
                
                if ($chkDelResult->num_rows > 0) {
                    $unassignStmt = $conn->prepare("UPDATE Deliveries SET assignedTruck = NULL WHERE assignedTruck = ?");
                    $unassignStmt->bind_param("i", $truckID);
                    $unassignStmt->execute();
                    $unassignStmt->close();
                    $unassignMsg = " Truck unassigned from " . $chkDelResult->num_rows . " deliveries.";
                    logAction($conn, "TRUCK_UNASSIGNED", "Truck ID $truckID set to $truckStatus. Auto-unassigned.");
                }
                $chkDelStmt->close();
            }

            $sql = "UPDATE Trucks SET truckName = ?, plateNumber = ?, truckStatus = ?, odometerOrMileage = ?, registrationDate = ?, assignedDriver = ?, truckImg = ? WHERE truckID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssisisi", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $assignedDriverID, $finalTruckImgPath, $truckID);

            if ($stmt && $stmt->execute()) {
                logAction($conn, "EDIT_TRUCK", "Updated Truck ID $truckID ($plateNumber).");
                $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** updated successfully!' . $unassignMsg . '</div>';
            } else {
                $error_msg = $stmt ? $stmt->error : $conn->error;
                $message = '<div class="alert alert-danger">Failed to update truck. ' . $error_msg . '</div>';
            }
            if ($stmt) $stmt->close();
        }
    }
    $view = 'trucks';
}

// =========================================================
//                  HANDLE TRUCK DELETION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_truck_id'])) {
    $truckID = (int)$_POST['delete_truck_id'];
    if ($truckID > 0) {
        $unassignStmt = $conn->prepare("UPDATE Deliveries SET assignedTruck = NULL WHERE assignedTruck = ?");
        $unassignStmt->bind_param("i", $truckID);
        $unassignStmt->execute();
        $unassignStmt->close();

        $stmt = $conn->prepare("DELETE FROM Trucks WHERE truckID = ?");
        $stmt->bind_param("i", $truckID);
        
        if ($stmt->execute()) {
            logAction($conn, "DELETE_TRUCK", "Deleted Truck ID $truckID.");
            $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** removed.</div>';
        } else {
            $message = '<div class="alert alert-danger">Error deleting truck: ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
    $view = 'trucks';
}

// =========================================================
//                  FETCH TRUCKS & DRIVER LISTS
// =========================================================
$trucksList = [];
$availableDrivers = [];
$allDrivers = [];

if ($view === 'trucks') {
    // 1. Fetch Trucks
    $queryTrucksList = "
        SELECT T.*, A.firstName, A.lastName
        FROM Trucks T
        LEFT JOIN Accounts A ON T.assignedDriver = A.accountID
        ORDER BY T.truckStatus DESC, T.truckID ASC
    ";
    $resultTrucksList = $conn->query($queryTrucksList);
    if ($resultTrucksList) {
        while ($row = $resultTrucksList->fetch_assoc()) {
            $trucksList[] = $row;
        }
    }

    // 2. Fetch Available Drivers
    $queryDrivers = "
        SELECT A.accountID, A.firstName, A.lastName
        FROM Accounts A
        LEFT JOIN Trucks T ON A.accountID = T.assignedDriver
        WHERE A.accountType = 'Driver' AND T.assignedDriver IS NULL
        ORDER BY A.firstName ASC
    ";
    $resultDrivers = $conn->query($queryDrivers);
    if ($resultDrivers) {
        while ($row = $resultDrivers->fetch_assoc()) {
            $availableDrivers[] = $row;
        }
    }

    // 3. Fetch All Drivers
    $queryAllDrivers = "
        SELECT A.accountID, A.firstName, A.lastName
        FROM Accounts A 
        WHERE A.accountType = 'Driver' 
        ORDER BY A.firstName ASC";
    $resultAllDrivers = $conn->query($queryAllDrivers);
    if ($resultAllDrivers) {
        while ($row = $resultAllDrivers->fetch_assoc()) {
            $allDrivers[] = $row;
        }
    }
}
?>