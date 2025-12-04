<?php
// =========================================================
//                  HANDLE HISTORY REPORT UPDATE
// =========================================================
$reportsList = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gas_submit'])) {
    $historyID = (int)($_POST['historyID'] ?? 0);
    $gasUsed = (int)($_POST['gasUsed'] ?? 0);

    if ($historyID > 0 && $gasUsed >= 0) {
        
        // 1. Fetch current delivery info (for logging/message)
        $deliveryName = '';
        $deliveryID = 0;
        $infoStmt = $conn->prepare("
            SELECT D.productName, D.deliveryID
            FROM History_Reports HR
            JOIN Deliveries D ON HR.deliveryHistoryID = D.deliveryID
            WHERE HR.historyID = ?
        ");
        $infoStmt->bind_param("i", $historyID);
        $infoStmt->execute();
        $infoResult = $infoStmt->get_result();
        if ($infoRow = $infoResult->fetch_assoc()) {
            $deliveryName = $infoRow['productName'];
            $deliveryID = $infoRow['deliveryID'];
        }
        $infoStmt->close();
        
        // 2. Update the History_Reports table
        $updateStmt = $conn->prepare("UPDATE History_Reports SET gasUsed = ? WHERE historyID = ?");
        $updateStmt->bind_param("ii", $gasUsed, $historyID);
        
        if ($updateStmt->execute()) {
            logAction($conn, 'REPORT_GAS_UPDATE', "Updated gas used for delivery: {$deliveryName} (DeliveryID: {$deliveryID}, ReportID: {$historyID}). Gas Used: {$gasUsed}L.");
            $message = '<div class="alert alert-success">Gas Used (' . $gasUsed . 'L) successfully recorded for delivery report ID ' . $historyID . '**.</div>';
        } else {
            $message = '<div class="alert alert-danger">Failed to update gas used. Database Error: ' . $updateStmt->error . '</div>';
        }
        $updateStmt->close();
    } else {
        $message = '<div class="alert alert-danger">Error: Invalid report ID or Gas Used value.</div>';
    }
    $view = 'history_reports';
}

// =========================================================
//                  FETCH HISTORY REPORTS
// =========================================================
if ($view === 'history_reports') {
    // Query to fetch all completed deliveries data from History_Reports
    $queryReportsList = "
        SELECT 
            HR.historyID, HR.gasUsed, HR.dateTimeCompleted,
            D.deliveryID, D.productName, D.origin, D.destination, D.allocatedGas, D.deliveryDistance,
            T.truckName, T.plateNumber, T.truckID,
            A.firstName AS driverFirstName, A.lastName AS driverLastName
        FROM History_Reports HR
        JOIN Deliveries D ON HR.deliveryHistoryID = D.deliveryID
        JOIN Trucks T ON HR.truckID = T.truckID
        JOIN Accounts A ON T.assignedDriver = A.accountID
        ORDER BY HR.dateTimeCompleted DESC
    ";
    $resultReportsList = $conn->query($queryReportsList);
    if ($resultReportsList) {
        while ($row = $resultReportsList->fetch_assoc()) {
            
            // --- Gas Allocation Check Logic ---
$gasUsed = (int)$row['gasUsed'];
$allocatedGas = (int)$row['allocatedGas'];
$messageAlert = ''; // Initialize the alert message

if ($gasUsed > 0 && $gasUsed > $allocatedGas) {
    // If inputted gas used is greater than the Allocated Gas AND gas was entered
    $gasStatusMessage = "Exceeded Gas Allocation";
    $messageAlert = '<div class="alert alert-danger" style="padding: 8px 12px; margin-top: 10px; font-size: 0.9em; border-radius: 4px;">⚠️ ALERT: Gas used (' . number_format($gasUsed) . ' L) exceeded allocated gas (' . number_format($allocatedGas) . ' L)!</div>';
} elseif ($gasUsed > 0 && $gasUsed <= $allocatedGas) {
    // If it's equal to or lower than the Allocated Gas AND gas was entered
    $gasStatusMessage = "Gas Used is With In Allocation";
    $messageAlert = '<div class="alert alert-success" style="padding: 8px 12px; margin-top: 10px; font-size: 0.9em; border-radius: 4px;">✅ STATUS: Gas used is within allocation.</div>';
} else {
    // Gas Used is 0 or unrecorded
    $gasStatusMessage = "Gas Usage Pending Input";
    $messageAlert = '<div class="alert alert-info" style="padding: 8px 12px; margin-top: 10px; font-size: 0.9em; border-radius: 4px;">💡 INFO: Gas used is pending input.</div>';
}

// Store the status message in the report array for later display
$row['gasStatusMessage'] = $gasStatusMessage;
$row['messageAlert'] = $messageAlert; // Store the alert HTML

// Add the processed row to the reports list (assuming this is your data structure)
$reportsList[] = $row;
        }
        $resultReportsList->free();
    } else {
        $message = '<div class="alert alert-danger">Failed to fetch history reports. Database Error: ' . $conn->error . '</div>';
    }
}
?>