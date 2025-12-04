<?php
// =========================================================
//                  HANDLE DELIVERY STATUS CHANGES
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delivery_id'])) {
    // Access global variables needed for database connection, messages, and view state
    global $conn, $message, $view;
    
    $deliveryID = (int)$_POST['delivery_id'];
    $newStatus = null;
    $actionType = null;
    
    if (isset($_POST['start_delivery'])) {
        
        // =================================================
        //          START DELIVERY VALIDATION 
        // =================================================
        $canStart = false;
        $validationError = '';

        // 1. Fetch assigned truck, its driver, and allocated gas for this delivery
        $validationQuery = $conn->prepare("
            SELECT D.assignedTruck, D.allocatedGas, T.assignedDriver, T.truckName
            FROM Deliveries D
            LEFT JOIN Trucks T ON D.assignedTruck = T.truckID
            WHERE D.deliveryID = ?
        ");
        $validationQuery->bind_param("i", $deliveryID);
        $validationQuery->execute();
        $validationResult = $validationQuery->get_result()->fetch_assoc();
        $validationQuery->close();
        
        if ($validationResult) {
            $truckID = $validationResult['assignedTruck'];
            $driverID = $validationResult['assignedDriver'];
            $allocatedGas = (int)($validationResult['allocatedGas'] ?? 0); // Fetch allocated gas
            $truckName = $validationResult['truckName'] ?? 'Unassigned Truck'; 

            if (empty($truckID)) {
                $validationError = "Error: Cannot start delivery. No truck is assigned to Delivery ID {$deliveryID}.";
            } elseif (empty($driverID)) {
                $validationError = "Error: Cannot start delivery. The assigned truck ({$truckName}) has no driver assigned to it.";
            } elseif ($allocatedGas <= 0) { // <-- NEW VALIDATION CHECK
                 $validationError = "Error: Cannot start delivery. Allocated Gas must be greater than 0 for Delivery ID {$deliveryID}. Current value: {$allocatedGas} L.";
            } else {
                $canStart = true;
            }
        } else {
            $validationError = "Error: Delivery ID {$deliveryID} not found.";
        }

        if ($canStart) {
            $newStatus = 'In Progress';
            $actionType = 'START_DELIVERY';
        } else {
            // Validation failed, set error message and prevent status update
            $message = '<div class="alert alert-danger">' . $validationError . '</div>';
            $newStatus = null; 
            $actionType = null;
        }
        // =================================================
        //          END START DELIVERY VALIDATION
        // =================================================

    } elseif (isset($_POST['complete_delivery'])) {
        $newStatus = 'Completed';
        $actionType = 'COMPLETE_DELIVERY';
    } elseif (isset($_POST['cancel_delivery'])) {
        $newStatus = 'Cancelled';
        $actionType = 'CANCEL_DELIVERY';
    }

    if ($newStatus) {
        
        // =========================================================
        // 1. FETCH DETAILS FOR HISTORY/TRUCK SYNC
        // =========================================================
        $details = null;
        $assignedTruckID = null;

        $detailsQuery = $conn->prepare("
            SELECT 
                D.assignedTruck, 
                T.assignedDriver
            FROM Deliveries D
            LEFT JOIN Trucks T ON D.assignedTruck = T.truckID
            WHERE D.deliveryID = ?
        ");
        $detailsQuery->bind_param("i", $deliveryID);
        $detailsQuery->execute();
        $detailsResult = $detailsQuery->get_result();
        $details = $detailsResult->fetch_assoc();
        $detailsQuery->close();
        
        if ($details) {
            $assignedTruckID = $details['assignedTruck'];
        }

        // 2. Update Deliveries status
        $stmt = $conn->prepare("UPDATE Deliveries SET deliveryStatus = ? WHERE deliveryID = ?");
        if ($stmt) {
            $stmt->bind_param("si", $newStatus, $deliveryID);
            if ($stmt->execute()) {
                
                $successMessage = '<div class="alert alert-success">Delivery ID ' . $deliveryID . ' status updated to ' . $newStatus . '.</div>';
                $logDetails = "Delivery ID {$deliveryID} status changed to {$newStatus}";
                
                // =========================================================
                // 3. HISTORY REPORT INSERTION (NEW LOGIC)
                // =========================================================
                if ($newStatus === 'Completed' && $details && $details['assignedTruck'] && $details['assignedDriver']) {
                    $truckID = $details['assignedTruck'];
                    $driverID = $details['assignedDriver'];
                    $gasUsed = 0; // Initialized to 0, to be updated later by admin

                    $historyStmt = $conn->prepare("
                        INSERT INTO History_Reports 
                        (deliveryHistoryID, driverID, truckID, gasUsed, dateTimeCompleted) 
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    
                    $historyStmt->bind_param("iiii", $deliveryID, $driverID, $truckID, $gasUsed);
                    
                    if ($historyStmt->execute()) {
                        // Success log for completion and history
                        $logDetails = "Delivery ID {$deliveryID} completed and history report generated. Truck: {$truckID}, Driver: {$driverID}.";
                        $successMessage = '<div class="alert alert-success">Delivery ID ' . $deliveryID . ' status updated to ' . $newStatus . '. History Report Generated.</div>';
                    } else {
                        // Warning/Error message for history failure
                        $logDetails = "Delivery ID {$deliveryID} status changed to {$newStatus}, but FAILED to create History Report: {$historyStmt->error}";
                        $successMessage = '<div class="alert alert-warning">Delivery status updated to Completed, but failed to create History Report. Please check logs.</div>';
                    }
                    $historyStmt->close();
                }
                
                // 4. Log Action
                logAction($conn, $actionType, $logDetails);
                $message = $successMessage;

                // 5. Sync truck status 
                if ($assignedTruckID) {
                    // syncTruckStatus is defined in this file (AdminPageDelivery.php)
                    syncTruckStatus($conn, $assignedTruckID);
                }
                
            } else {
                $message = '<div class="alert alert-danger">Error updating delivery status for ID ' . $deliveryID . ': ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
             $message = '<div class="alert alert-danger">Database error preparing status update.</div>';
        }
    }
    // Always return to the deliveries view after a status change
    $view = 'deliveries'; 
}

// =========================================================
//                  HELPER FUNCTIONS
// =========================================================

function syncTruckStatus($conn, $truckID) {
    if ($truckID === null || $truckID <= 0) return;

    // --- NEW LOGIC: Don't override if truck is broken/unavailable ---
    $statusCheck = $conn->prepare("SELECT truckStatus FROM Trucks WHERE truckID = ?");
    $statusCheck->bind_param("i", $truckID);
    $statusCheck->execute();
    $sRes = $statusCheck->get_result()->fetch_assoc();
    $statusCheck->close();

    if ($sRes && ($sRes['truckStatus'] === 'Maintenance' || $sRes['truckStatus'] === 'Unavailable')) {
        return; // Do NOT change status automatically if manual override is in place
    }
    // ---------------------------------------------------------------

    $sql = "SELECT COUNT(*) as activeCount 
            FROM Deliveries 
            WHERE assignedTruck = ? 
            AND deliveryStatus IN ('In Progress', 'On Route')";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $truckID);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $isActive = $row['activeCount'] > 0;
    $stmt->close();

    $newStatus = $isActive ? 'In Transit' : 'Available';
    
    // Only update if the status actually needs changing
    if ($sRes['truckStatus'] !== $newStatus) {
        $updateStmt = $conn->prepare("UPDATE Trucks SET truckStatus = ? WHERE truckID = ?");
        $updateStmt->bind_param("si", $newStatus, $truckID);
        $updateStmt->execute();
        $updateStmt->close();
    }
}

// =========================================================
//                  HANDLE DELIVERY ADDITION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_delivery_submit'])) {
    $productName = trim($_POST['productName'] ?? '');
    $productDescription = trim($_POST['productDescription'] ?? '');
    $assignedTruck = empty($_POST['assignedTruck']) || $_POST['assignedTruck'] === '0' ? null : (int)$_POST['assignedTruck'];
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $deliveryDistance = trim($_POST['deliveryDistance'] ?? '');
    // NEW FIELD
    $allocatedGas = (int)($_POST['allocatedGas'] ?? 0); 
    $estimatedTimeOfArrival = $_POST['estimatedTimeOfArrival'] ?? null;
    $deliveryStatus = 'Inactive'; // Start as Inactive

    if (empty($productName) || empty($origin) || empty($destination) || empty($estimatedTimeOfArrival) || empty($deliveryDistance)) {
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        // SQL: Added allocatedGas
        $sql = "INSERT INTO Deliveries (productName, productDescription, assignedTruck, origin, destination, deliveryDistance, allocatedGas, estimatedTimeOfArrival, deliveryStatus) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt) {
            // Binding: sssisssis -> string, string, int (truckID), string, string, string, int (allocatedGas), string (datetime), string
            $stmt->bind_param("ssisssiss", 
                $productName, $productDescription, $assignedTruck, 
                $origin, $destination, $deliveryDistance, $allocatedGas, 
                $estimatedTimeOfArrival, $deliveryStatus
            );

            if ($stmt->execute()) {
                $deliveryID = $stmt->insert_id;
                logAction($conn, 'ADD_DELIVERY', "Scheduled new delivery: {$productName} (ID: {$deliveryID}).");
                
                // If a truck was assigned, sync its status
                if ($assignedTruck !== null) {
                    syncTruckStatus($conn, $assignedTruck);
                }
                
                $message = '<div class="alert alert-success">Delivery ' . htmlspecialchars($productName) . ' scheduled successfully!</div>';
            } else {
                $message = '<div class="alert alert-danger">Failed to schedule delivery. Database Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Failed to prepare statement.</div>';
        }
    }
    $view = 'deliveries';
}

// =========================================================
//                  HANDLE DELIVERY EDIT SUBMISSION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_delivery_submit'])) {
    global $conn, $message, $view;

    // 1. Collect and sanitize data
    $deliveryID = (int)($_POST['editDeliveryID'] ?? 0);
    $productName = trim($_POST['editProductName'] ?? '');
    $productDescription = trim($_POST['editProductDescription'] ?? '');
    $assignedTruck = (int)($_POST['editAssignedTruck'] ?? 0);
    $origin = trim($_POST['editOrigin'] ?? '');
    $destination = trim($_POST['editDestination'] ?? '');
    $deliveryDistance = trim($_POST['editDeliveryDistance'] ?? '');
    $allocatedGas = (int)($_POST['editAllocatedGas'] ?? 0);
    $eta = trim($_POST['editEstimatedTimeOfArrival'] ?? '');
    
    // REMOVED: $deliveryStatus = trim($_POST['editDeliveryStatus'] ?? 'Inactive'); 
    // The status is now implicitly managed by the Start/Complete/Cancel buttons.

    if ($deliveryID > 0 && !empty($productName) && !empty($origin) && !empty($destination) && !empty($eta)) {
        
        // 2. Build the UPDATE query (The deliveryStatus column is REMOVED from the update statement)
        $stmt = $conn->prepare("
            UPDATE Deliveries SET 
                productName = ?, 
                productDescription = ?, 
                assignedTruck = ?, 
                origin = ?, 
                destination = ?, 
                deliveryDistance = ?, 
                allocatedGas = ?, 
                estimatedTimeOfArrival = ?
            WHERE deliveryID = ?
        ");

        if ($stmt) {
            $stmt->bind_param(
                "ssisssisi", 
                $productName, 
                $productDescription, 
                $assignedTruck, 
                $origin, 
                $destination, 
                $deliveryDistance, 
                $allocatedGas, 
                $eta, 
                $deliveryID
            );

            if ($stmt->execute()) {
                // ... (rest of the log and success message logic)
                $logDetails = "Delivery ID {$deliveryID} updated. Truck assigned: {$assignedTruck}.";
                logAction($conn, 'EDIT_DELIVERY', $logDetails);
                $message = '<div class="alert alert-success">Delivery ID ' . $deliveryID . ' successfully updated.</div>';
                
                // 3. Sync truck status if a truck was assigned/changed
                if ($assignedTruck > 0) {
                    syncTruckStatus($conn, $assignedTruck);
                }

            } else {
                $message = '<div class="alert alert-danger">Error updating delivery ID ' . $deliveryID . ': ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Database error preparing edit delivery statement.</div>';
        }
    } else {
        $message = '<div class="alert alert-danger">Error: Missing required fields for editing delivery.</div>';
    }
    $view = 'deliveries';
}

// =========================================================
//                  HANDLE DELIVERY DELETION 
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_delivery_id'])) {
    $deliveryID = (int)$_POST['delete_delivery_id'];
    
    // Get assigned truck before deletion
    $truckIDToDelete = null;
    $preDelStmt = $conn->prepare("SELECT productName, assignedTruck FROM Deliveries WHERE deliveryID = ?");
    $preDelStmt->bind_param("i", $deliveryID);
    $preDelStmt->execute();
    $preDelResult = $preDelStmt->get_result();
    if ($row = $preDelResult->fetch_assoc()) {
        $truckIDToDelete = $row['assignedTruck'];
        $productName = $row['productName'];
    }
    $preDelStmt->close();

    if ($deliveryID > 0) {
        $stmt = $conn->prepare("DELETE FROM Deliveries WHERE deliveryID = ? AND deliveryStatus NOT IN ('Active', 'On Route')");
        $stmt->bind_param("i", $deliveryID);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                logAction($conn, 'DELETE_DELIVERY', "Deleted delivery: {$productName} (ID: {$deliveryID}).");
                $message = '<div class="alert alert-success">Delivery ID ' . $deliveryID . ' successfully removed.</div>';
                
                // Sync truck status after deletion
                if ($truckIDToDelete !== null) {
                    syncTruckStatus($conn, $truckIDToDelete);
                }
            } else {
                 $message = '<div class="alert alert-danger">Error: Delivery ID ' . $deliveryID . ' is active/on route and cannot be deleted.</div>';
            }
        } else {
            $message = '<div class="alert alert-danger">Error deleting delivery ID ' . $deliveryID . ': ' . $stmt->error . '</div>';
        }
        $stmt->close();
    }
    $view = 'deliveries';
}

// =========================================================
//                  FETCH DATA FOR VIEW
// =========================================================

$deliveriesList = [];
$allTrucks = [];
$availableTrucks = [];
$deliveryID = [];

if ($view === 'deliveries') {
    
    // Check for Filter Parameter
    $filterStatus = isset($_GET['delivery_filter']) ? $_GET['delivery_filter'] : '';

    // 1. Fetch Deliveries List (excluding Completed/Cancelled deliveries)
    $queryDeliveriesList = "
        SELECT 
            D.deliveryID, D.productName, D.productDescription, D.assignedTruck, D.origin, D.destination, 
            D.deliveryDistance, D.allocatedGas, D.estimatedTimeOfArrival, D.deliveryStatus,
            T.truckName, T.plateNumber, T.assignedDriver
        FROM Deliveries D
        LEFT JOIN Trucks T ON D.assignedTruck = T.truckID
        WHERE D.deliveryStatus NOT IN ('Completed', 'Cancelled')
    ";
    
    // Apply Status Filter if set
    if (!empty($filterStatus)) {
        $safeFilter = $conn->real_escape_string($filterStatus);
        $queryDeliveriesList .= " AND D.deliveryStatus = '$safeFilter' ";
    }

    $queryDeliveriesList .= " ORDER BY FIELD(D.deliveryStatus, 'In Progress', 'Inactive') ASC, D.deliveryID ASC";

    $resultDeliveriesList = $conn->query($queryDeliveriesList);
    if ($resultDeliveriesList) {
        while ($row = $resultDeliveriesList->fetch_assoc()) {
            $deliveriesList[] = $row;
        }
    }

    // 2. Fetch All Trucks
    $queryAllTrucks = "
        SELECT T.truckID, T.truckName, T.plateNumber, T.truckStatus, T.assignedDriver
        FROM Trucks T
        ORDER BY T.truckName ASC
    ";
    $resultTrucks = $conn->query($queryAllTrucks);
    if ($resultTrucks) {
        while ($row = $resultTrucks->fetch_assoc()) {
            $allTrucks[] = $row;
        }
    }
    
    // 3. Fetch Trucks Available for NEW deliveries
    $queryAvailableTrucks = "
        SELECT T.truckID, T.truckName, T.plateNumber
        FROM Trucks T
        WHERE NOT EXISTS (
            SELECT 1 
            FROM Deliveries D 
            WHERE D.assignedTruck = T.truckID 
            AND D.deliveryStatus IN ('In Progress', 'On Route')
        )
        AND T.truckStatus NOT IN ('Maintenance', 'Unavailable')
        ORDER BY T.truckName ASC
    ";
    $resultAvailableTrucks = $conn->query($queryAvailableTrucks);
    if ($resultAvailableTrucks) {
        while ($row = $resultAvailableTrucks->fetch_assoc()) {
            $availableTrucks[] = $row;
        }
    }
}
?>