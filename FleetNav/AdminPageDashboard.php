<?php
// =========================================================
//                  DASHBOARD METRICS LOGIC
// =========================================================

// Only run these heavy queries if we are actually viewing the dashboard
if ($view === 'dashboard') {
    // 1. Drivers: Assigned vs Unassigned
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

    // 2. Trucks: Available vs Unavailable
    $queryTrucksMetrics = "
        SELECT
            SUM(CASE WHEN truckStatus = 'Available' THEN 1 ELSE 0 END) AS available_trucks,
            SUM(CASE WHEN truckStatus != 'Available' THEN 1 ELSE 0 END) AS unavailable_trucks
        FROM Trucks;
    ";
    $resultTrucksMetrics = $conn->query($queryTrucksMetrics);
    $trucksData = $resultTrucksMetrics->fetch_assoc();
    $totalTrucks = $trucksData['available_trucks'] + $trucksData['unavailable_trucks'];

    // 3. Deliveries: In Progress vs Not
    $queryDeliveries = "
        SELECT
            SUM(CASE WHEN deliveryStatus = 'In Progress' THEN 1 ELSE 0 END) AS deliveries_in_progress,
            SUM(CASE WHEN deliveryStatus != 'In Progress' THEN 1 ELSE 0 END) AS deliveries_not_in_progress
        FROM Deliveries;
    ";
    $resultDeliveries = $conn->query($queryDeliveries);
    $deliveriesData = $resultDeliveries->fetch_assoc();
    $totalDeliveries = $deliveriesData['deliveries_in_progress'] + $deliveriesData['deliveries_not_in_progress'];

    // Percentages (Prevent Division by Zero)
    $drivers_avail_percent = $totalDrivers > 0 ? round(($driversData['available_drivers'] / $totalDrivers) * 100) : 0;
    $drivers_unavail_percent = 100 - $drivers_avail_percent;
    
    $trucks_avail_percent = $totalTrucks > 0 ? round(($trucksData['available_trucks'] / $totalTrucks) * 100) : 0;
    $trucks_unavail_percent = 100 - $trucks_avail_percent;
    
    $deliveries_in_percent = $totalDeliveries > 0 ? round(($deliveriesData['deliveries_in_progress'] / $totalDeliveries) * 100) : 0;
    $deliveries_not_in_percent = 100 - $deliveries_in_percent;
}

?>