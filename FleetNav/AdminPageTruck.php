<?php
// =========================================================
//                  HANDLE TRUCK ADDITION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_truck_submit'])) {
    $truckName = trim($_POST['truckName'] ?? '');
    $plateNumber = trim($_POST['plateNumber'] ?? '');
    $truckStatus = $_POST['truckStatus'] ?? 'Available';
    $odometer = (int)($_POST['odometerOrMileage'] ?? 0);
    $registrationDate = $_POST['registrationDate'] ?? null;
    $truckImgPath = isset($_POST['uploadedImagePath']) && !empty($_POST['uploadedImagePath']) ? trim($_POST['uploadedImagePath']) : null;
    $assignedDriverID = empty($_POST['assignedDriver']) || $_POST['assignedDriver'] === '0' ? null : (int)$_POST['assignedDriver'];

    if (empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
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
            $message = '<div class="alert alert-success">Truck **' . htmlspecialchars($truckName) . '** added successfully!</div>';
        } else {
            $error_msg = $stmt ? $stmt->error : $conn->error;
            $message = '<div class="alert alert-danger">Failed to add truck. ' . $error_msg . '</div>';
        }
        if ($stmt) $stmt->close();
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
    $newTruckImgPath = isset($_POST['editUploadedImagePath']) && !empty($_POST['editUploadedImagePath']) ? trim($_POST['editUploadedImagePath']) : null;
    $currentTruckImg = trim($_POST['editCurrentTruckImg'] ?? '');
    $finalTruckImgPath = ($newTruckImgPath === 'null' ? null : $newTruckImgPath) ?? ($currentTruckImg !== 'null' ? $currentTruckImg : null);
    $assignedDriverID = empty($_POST['editAssignedDriver']) || $_POST['editAssignedDriver'] === '0' ? null : (int)$_POST['editAssignedDriver'];

    if ($truckID <= 0 || empty($truckName) || empty($plateNumber) || empty($truckStatus) || empty($registrationDate) || $odometer < 0) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        $stmt = null;
        $sql = "UPDATE Trucks SET truckName = ?, plateNumber = ?, truckStatus = ?, odometerOrMileage = ?, registrationDate = ?, assignedDriver = ?, truckImg = ? WHERE truckID = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssisisi", $truckName, $plateNumber, $truckStatus, $odometer, $registrationDate, $assignedDriverID, $finalTruckImgPath, $truckID);

        if ($stmt && $stmt->execute()) {
            $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** updated successfully!</div>';
        } else {
            $error_msg = $stmt ? $stmt->error : $conn->error;
            $message = '<div class="alert alert-danger">Failed to update truck. ' . $error_msg . '</div>';
        }
        if ($stmt) $stmt->close();
    }
    $view = 'trucks';
}

// =========================================================
//                  HANDLE TRUCK DELETION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_truck_id'])) {
    $truckID = (int)$_POST['delete_truck_id'];
    if ($truckID > 0) {
        // 1. Unassign this truck from any Deliveries first (Set assignedTruck to NULL)
        $unassignStmt = $conn->prepare("UPDATE Deliveries SET assignedTruck = NULL WHERE assignedTruck = ?");
        $unassignStmt->bind_param("i", $truckID);
        $unassignStmt->execute();
        $unassignStmt->close();

        // 2. Now delete the truck
        $stmt = $conn->prepare("DELETE FROM Trucks WHERE truckID = ?");
        $stmt->bind_param("i", $truckID);
        
        if ($stmt->execute()) {
            $message = '<div class="alert alert-success">Truck ID **' . $truckID . '** successfully removed (Deliveries unassigned).</div>';
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
    // 1. Fetch Trucks List
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

    // 3. Fetch All Drivers (for edit modal)
    $queryAllDrivers = "SELECT accountID, firstName, lastName FROM Accounts WHERE accountType = 'Driver' ORDER BY firstName ASC";
    $resultAllDrivers = $conn->query($queryAllDrivers);
    if ($resultAllDrivers) {
        while ($row = $resultAllDrivers->fetch_assoc()) {
            $allDrivers[] = $row;
        }
    }
}
?>