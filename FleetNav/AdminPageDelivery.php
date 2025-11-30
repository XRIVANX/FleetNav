<?php

// =========================================================
//                  HELPER FUNCTIONS
// =========================================================

/**
 * Updates the status of a truck in the Trucks table.
 * @param mysqli $conn The database connection object.
 * @param int|null $truckID The ID of the truck to update.
 * @param string $newStatus The new status to set ('Available' or 'In Transit').
 */
function updateTruckStatus($conn, $truckID, $newStatus) {
    if ($truckID === null) return;
    $sql = "UPDATE Trucks SET truckStatus = ? WHERE truckID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $newStatus, $truckID);
    $stmt->execute();
    $stmt->close();
}

/**
 * Checks if a truck is assigned to any other delivery that is not Completed or Cancelled.
 * @param mysqli $conn The database connection object.
 * @param int|null $truckID The ID of the truck to check.
 * @param int|null $deliveryID The current delivery ID to exclude from the check (for edits).
 * @return bool True if the truck is needed for another active/inactive delivery, False otherwise.
 */
function isTruckNeededForOtherDeliveries($conn, $truckID, $deliveryID = null) {
    if ($truckID === null) return false;
    // 'Inactive', 'In Progress', 'On Route' are all considered 'busy' states based on the truck filtering logic.
    $sql = "SELECT deliveryID FROM Deliveries WHERE assignedTruck = ? AND deliveryStatus NOT IN ('Completed', 'Cancelled')";
    if ($deliveryID !== null) {
        $sql .= " AND deliveryID != ?";
    }
    $sql .= " LIMIT 1";

    $stmt = $conn->prepare($sql);
    if ($deliveryID !== null) {
        $stmt->bind_param("ii", $truckID, $deliveryID);
    } else {
        $stmt->bind_param("i", $truckID);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $isNeeded = $result->num_rows > 0;
    $stmt->close();
    return $isNeeded;
}


// =========================================================
//                  HANDLE DELIVERY ADDITION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_delivery_submit'])) {
    $productName = trim($_POST['productName'] ?? '');
    $productDescription = trim($_POST['productDescription'] ?? '');
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $deliveryDistance = trim($_POST['deliveryDistance'] ?? ''); // NEW
    
    // Logic: If value is 0 or empty, treat as NULL
    $assignedTruckInput = $_POST['assignedTruck'] ?? '';
    $assignedTruckID = ($assignedTruckInput === '' || $assignedTruckInput === '0') ? null : (int)$assignedTruckInput;
    
    $estimatedTimeOfArrival = $_POST['estimatedTimeOfArrival'] ?? null;
    $deliveryStatus = 'Inactive'; 

    if (empty($productName) || empty($origin) || empty($destination) || empty($deliveryDistance) || empty($estimatedTimeOfArrival)) { // deliveryDistance added to validation
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields.</div>';
    } else {
        // --- CHECK: PREVENT ASSIGNING BUSY TRUCK TO NEW DELIVERY ---
        if ($assignedTruckID !== null) {
            $checkTruckSql = "SELECT deliveryID FROM Deliveries WHERE assignedTruck = ? AND deliveryStatus NOT IN ('Completed', 'Cancelled') LIMIT 1";
            $checkTruckStmt = $conn->prepare($checkTruckSql);
            $checkTruckStmt->bind_param("i", $assignedTruckID);
            $checkTruckStmt->execute();
            $checkTruckResult = $checkTruckStmt->get_result();
            
            if ($checkTruckResult->num_rows > 0) {
                // If the truck is already assigned to an uncompleted delivery
                $message = '<div class="alert alert-danger">Error: This truck is already assigned to a delivery that is not yet completed or cancelled. Please select an unassigned truck.</div>';
                $checkTruckStmt->close();
                goto end_delivery_add_logic; // Skip insertion logic
            }
            $checkTruckStmt->close();
        }
        // --- END CHECK ---

        // SQL updated to include deliveryDistance
        $sql = "INSERT INTO Deliveries (productName, productDescription, assignedTruck, origin, destination, deliveryDistance, estimatedTimeOfArrival, deliveryStatus) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            
            // Temporary variable for binding truck ID (to handle NULL safely)
            $truck_bind = $assignedTruckID;
            // Bind parameters: s s i s s s s s (deliveryDistance is a string)
            $stmt->bind_param("ssisssss", $productName, $productDescription, $truck_bind, $origin, $destination, $deliveryDistance, $estimatedTimeOfArrival, $deliveryStatus);

            if ($stmt->execute()) {
                // --- NEW: POST-INSERT TRUCK STATUS UPDATE ---
                // If a truck was assigned, set its status to 'In Transit' (busy state)
                if ($assignedTruckID !== null) {
                    updateTruckStatus($conn, $assignedTruckID, 'In Transit');
                }
                // --- END NEW ---
                
                $message = '<div class="alert alert-success">Success: New delivery **' . htmlspecialchars($productName) . '** scheduled successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: Could not schedule delivery. DB Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Error: Failed to prepare SQL statement.</div>';
        }
    }
    end_delivery_add_logic:; // Label for goto

}

// =========================================================
//                  HANDLE DELIVERY EDIT
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit_delivery_submit'])) {
    $deliveryID = (int)$_POST['editDeliveryID'];
    $productName = trim($_POST['editProductName'] ?? '');
    $productDescription = trim($_POST['editProductDescription'] ?? '');
    $origin = trim($_POST['editOrigin'] ?? '');
    $destination = trim($_POST['editDestination'] ?? '');
    $deliveryDistance = trim($_POST['editDeliveryDistance'] ?? ''); // NEW
    $assignedTruckInput = $_POST['editAssignedTruck'] ?? '';
    $assignedTruckID = ($assignedTruckInput === '' || $assignedTruckInput === '0') ? null : (int)$assignedTruckInput;
    $estimatedTimeOfArrival = $_POST['editEstimatedTimeOfArrival'] ?? null;
    $deliveryStatus = trim($_POST['editDeliveryStatus'] ?? 'Inactive');
    
    if (empty($deliveryID) || empty($productName) || empty($origin) || empty($destination) || empty($deliveryDistance) || empty($estimatedTimeOfArrival)) { // deliveryDistance added to validation
        $message = '<div class="alert alert-danger">Error: Please fill in all required fields and ensure the ID is valid.</div>';
    } else {
        
        // 1. FETCH CURRENT DELIVERY & TRUCK INFO
        $preUpdateSql = "SELECT assignedTruck, deliveryStatus FROM Deliveries WHERE deliveryID = ?";
        $preUpdateStmt = $conn->prepare($preUpdateSql);
        $preUpdateStmt->bind_param("i", $deliveryID);
        $preUpdateStmt->execute();
        $preUpdateResult = $preUpdateStmt->get_result();
        $oldDelivery = $preUpdateResult->fetch_assoc();
        $preUpdateStmt->close();
        
        $oldTruckID = $oldDelivery['assignedTruck'] ?? null;
        $oldDeliveryStatus = $oldDelivery['deliveryStatus'] ?? '';
        
        // --- CHECK: PREVENT EDITING A COMPLETED DELIVERY (Existing Logic) ---
        if ($oldDelivery && $oldDeliveryStatus === 'Completed') {
            $message = '<div class="alert alert-danger">Error: Delivery **' . htmlspecialchars($productName) . '** (ID: ' . $deliveryID . ') cannot be edited because its status is **Completed**.</div>';
            goto end_delivery_edit_logic;
        }
        // --- END CHECK ---
        
        // 2. CHECK: PREVENT 'IN PROGRESS'/'ON ROUTE' WITHOUT A DRIVER
        $isAttemptingToActivate = ($deliveryStatus === 'In Progress' || $deliveryStatus === 'On Route');

        if ($assignedTruckID !== null && $isAttemptingToActivate) {
            // Fetch truck's assigned driver
            $driverCheckSql = "SELECT assignedDriver FROM Trucks WHERE truckID = ?";
            $driverCheckStmt = $conn->prepare($driverCheckSql);
            $driverCheckStmt->bind_param("i", $assignedTruckID);
            $driverCheckStmt->execute();
            $driverCheckResult = $driverCheckStmt->get_result();
            $truck = $driverCheckResult->fetch_assoc();
            $driverCheckStmt->close();
            
            if (!$truck || $truck['assignedDriver'] === null) {
                // Truck exists but has no driver, block setting to In Progress/On Route
                $message = '<div class="alert alert-danger">Error: Cannot set delivery to **In Progress** or **On Route**. The assigned truck (ID: ' . $assignedTruckID . ') does not have an assigned driver.</div>';
                goto end_delivery_edit_logic;
            }
        }
        // --- END CHECK ---
        
        // --- CHECK: PREVENT ASSIGNING BUSY TRUCK TO *ANOTHER* DELIVERY (Existing Logic) ---
        if ($assignedTruckID !== null) {
            $checkTruckSql = "
                SELECT deliveryID 
                FROM Deliveries 
                WHERE assignedTruck = ? 
                AND deliveryID != ? 
                AND deliveryStatus NOT IN ('Completed', 'Cancelled') 
                LIMIT 1
            ";
            $checkTruckStmt = $conn->prepare($checkTruckSql);
            $checkTruckStmt->bind_param("ii", $assignedTruckID, $deliveryID); // Check if the truck is busy with another delivery
            $checkTruckStmt->execute();
            $checkTruckResult = $checkTruckStmt->get_result();

            if ($checkTruckResult->num_rows > 0) {
                $busyDeliveryID = $checkTruckResult->fetch_assoc()['deliveryID'];
                $message = '<div class="alert alert-danger">Error: Truck (ID: ' . $assignedTruckID . ') is already assigned to another active delivery (ID: ' . $busyDeliveryID . ').</div>';
                $checkTruckStmt->close();
                goto end_delivery_edit_logic; // Skip update logic
            }
            $checkTruckStmt->close();
        }
        // --- END CHECK ---
        
        // 3. PERFORM DATABASE UPDATE
        // SQL updated to include deliveryDistance
        $sql = "UPDATE Deliveries SET productName=?, productDescription=?, assignedTruck=?, origin=?, destination=?, deliveryDistance=?, estimatedTimeOfArrival=?, deliveryStatus=? WHERE deliveryID=?";
        $stmt = $conn->prepare($sql);

        if ($stmt) {
            $truck_bind = $assignedTruckID;
            // Bind parameters: s s i s s s s s i (deliveryDistance is a string, deliveryID is int)
            $stmt->bind_param("ssisssssi", $productName, $productDescription, $truck_bind, $origin, $destination, $deliveryDistance, $estimatedTimeOfArrival, $deliveryStatus, $deliveryID);
            
            if ($stmt->execute()) {
                // 4. POST-UPDATE TRUCK STATUS LOGIC (Unchanged from last step)
                
                $deliveryEnded = ($deliveryStatus === 'Completed' || $deliveryStatus === 'Cancelled' || $deliveryStatus === 'Inactive');
                
                // Case A: Truck was UNASSIGNED (new ID is null)
                if ($oldTruckID !== null && $assignedTruckID === null) {
                    // Revert old truck to 'Available' if it's not needed for any other uncompleted delivery
                    if (!isTruckNeededForOtherDeliveries($conn, $oldTruckID, $deliveryID)) {
                        updateTruckStatus($conn, $oldTruckID, 'Available');
                    }
                }
                
                // Case B: Truck was CHANGED (new ID is different from old ID)
                if ($oldTruckID !== null && $assignedTruckID !== null && $oldTruckID != $assignedTruckID) {
                    // 1. Revert old truck to 'Available' (if not needed for other uncompleted deliveries)
                    if (!isTruckNeededForOtherDeliveries($conn, $oldTruckID, $deliveryID)) {
                        updateTruckStatus($conn, $oldTruckID, 'Available');
                    }

                    // 2. Set new truck's status to 'In Transit' (because assigning it makes it busy)
                    updateTruckStatus($conn, $assignedTruckID, 'In Transit'); 
                }
                
                // Case C: Truck remained the SAME (old ID equals new ID, and both are not null)
                if ($oldTruckID !== null && $assignedTruckID !== null && $oldTruckID == $assignedTruckID) {
                    if ($deliveryEnded) {
                        // Delivery ended/reverted to inactive: make truck available if no other uncompleted deliveries
                        if (!isTruckNeededForOtherDeliveries($conn, $assignedTruckID, $deliveryID)) {
                            updateTruckStatus($conn, $assignedTruckID, 'Available');
                        }
                    } else {
                        // Delivery is active/re-assigned: set truck to In Transit (already set, but for completeness)
                        updateTruckStatus($conn, $assignedTruckID, 'In Transit');
                    }
                }
                
                // Case D: Truck was ASSIGNED (old ID was null, new ID is not null)
                if ($oldTruckID === null && $assignedTruckID !== null) {
                    // Set new truck's status to 'In Transit' (because assigning it makes it busy)
                    updateTruckStatus($conn, $assignedTruckID, 'In Transit'); 
                }
                
                $message = '<div class="alert alert-success">Success: Delivery **' . htmlspecialchars($productName) . ' (ID: ' . $deliveryID . ')** updated successfully.</div>';
            } else {
                $message = '<div class="alert alert-danger">Error: Could not update delivery. DB Error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
            $message = '<div class="alert alert-danger">Error: Failed to prepare SQL statement.</div>';
        }
    }
    end_delivery_edit_logic:; // Label for goto
}

// =========================================================
//                  HANDLE DELIVERY DELETION
// =========================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_delivery_id'])) {
    $deliveryID = (int)$_POST['delete_delivery_id'];

    if ($deliveryID > 0) {
        // First, fetch the status and assigned truck for the check/cleanup
        $checkSql = "SELECT deliveryStatus, productName, assignedTruck FROM Deliveries WHERE deliveryID = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param("i", $deliveryID);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $delivery = $checkResult->fetch_assoc();
        $checkStmt->close();

        if ($delivery) {
            $status = $delivery['deliveryStatus'];
            $name = $delivery['productName'];
            $truckID = $delivery['assignedTruck'];

            if ($status === 'In Progress' || $status === 'On Route') {
                 $message = '<div class="alert alert-danger">Error: Cannot delete delivery **' . htmlspecialchars($name) . '** because its status is **' . htmlspecialchars($status) . '**.</div>';
            } else {
                $sql = "DELETE FROM Deliveries WHERE deliveryID = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("i", $deliveryID);
                    if ($stmt->execute()) {
                        
                        // --- NEW: POST-DELETE TRUCK STATUS UPDATE ---
                        // If truck was assigned, set its status to 'Available' if not needed elsewhere
                        if ($truckID !== null && !isTruckNeededForOtherDeliveries($conn, $truckID, null)) {
                            updateTruckStatus($conn, $truckID, 'Available');
                        }
                        // --- END NEW ---
                        
                        $message = '<div class="alert alert-success">Success: Delivery **' . htmlspecialchars($name) . ' (ID: ' . $deliveryID . ')** deleted successfully.</div>';
                    } else {
                        $message = '<div class="alert alert-danger">Error: Could not delete delivery. DB Error: ' . $stmt->error . '</div>';
                    }
                    $stmt->close();
                } else {
                    $message = '<div class="alert alert-danger">Error: Failed to prepare SQL statement.</div>';
                }
            }
        } else {
            $message = '<div class="alert alert-danger">Error: Delivery with ID **' . $deliveryID . '** not found.</div>';
        }

    } else {
        $message = '<div class="alert alert-danger">Error: Invalid Delivery ID for deletion.</div>';
    }
}


// =========================================================
//                  FETCH DELIVERIES & TRUCKS
// =========================================================
$deliveriesList = [];
$availableTrucks = []; // For Add Modal
$allTrucks = [];       // For Edit Modal (Includes currently assigned trucks)

if ($view === 'deliveries') {
    // 1. Fetch Deliveries (D.* includes deliveryDistance)
    $queryDeliveriesList = "
        SELECT D.*, T.truckName, T.plateNumber, T.assignedDriver
        FROM Deliveries D
        LEFT JOIN Trucks T ON D.assignedTruck = T.truckID
        ORDER BY D.deliveryStatus DESC, D.deliveryID ASC
    ";
    $resultDeliveriesList = $conn->query($queryDeliveriesList);
    if ($resultDeliveriesList) {
        while ($row = $resultDeliveriesList->fetch_assoc()) {
            $deliveriesList[] = $row;
        }
    }

    // 2. Fetch All Trucks (Unchanged)
    $queryAllTrucks = "
        SELECT T.truckID, T.truckName, T.plateNumber, T.truckStatus, T.assignedDriver, A.firstName, A.lastName
        FROM Trucks T
        LEFT JOIN Accounts A ON T.assignedDriver = A.accountID
        ORDER BY T.truckName ASC
    ";
    $resultTrucks = $conn->query($queryAllTrucks);
    if ($resultTrucks) {
        while ($row = $resultTrucks->fetch_assoc()) {
            $allTrucks[] = $row;
        }
    }
    
    // 3. Fetch Trucks Available for NEW deliveries (Unchanged)
    $queryAvailableTrucks = "
        SELECT T.truckID, T.truckName, T.plateNumber
        FROM Trucks T
        WHERE NOT EXISTS (
            SELECT 1 
            FROM Deliveries D 
            WHERE D.assignedTruck = T.truckID 
            AND D.deliveryStatus NOT IN ('Completed', 'Cancelled')
        )
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