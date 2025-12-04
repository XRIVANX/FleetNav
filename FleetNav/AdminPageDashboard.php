<?php
// =========================================================
//                  DASHBOARD METRICS LOGIC
// =========================================================

// Only run these heavy queries if we are actually viewing the dashboard
if ($view === 'dashboard') {

    // =========================================================
    // 1. DRIVERS (Unchanged - Shows Total Fleet Drivers)
    // =========================================================
    $queryTotalDrivers = "SELECT COUNT(accountID) AS total FROM Accounts WHERE accountType = 'Driver'";
    $resultTotalDrivers = $conn->query($queryTotalDrivers);
    $totalDrivers = $resultTotalDrivers->fetch_assoc()['total'];
    
    // Count drivers linked to any truck (Assigned)
    $queryAssignedDrivers = "SELECT COUNT(DISTINCT assignedDriver) AS assigned FROM Trucks WHERE assignedDriver IS NOT NULL";
    $resultAssignedDrivers = $conn->query($queryAssignedDrivers);
    $driversAssigned = $resultAssignedDrivers->fetch_assoc()['assigned'];

    // Total drivers not assigned to a truck
    $unassignedPool = $totalDrivers - $driversAssigned;

    // LOGICAL SPLIT/PLACEHOLDER FOR UNAVAILABLE
    if ($unassignedPool > 2) {
        $driversUnavailable = floor($unassignedPool / 3); 
        $driversAvailable = $unassignedPool - $driversUnavailable;
    } else {
        $driversUnavailable = 0;
        $driversAvailable = $unassignedPool;
    }

    $driversData = [
        'total_drivers' => $totalDrivers,
        'assigned' => $driversAssigned,
        'available' => $driversAvailable,
        'unavailable' => $driversUnavailable,
    ];


    // =========================================================
    // 2. TRUCKS (Unchanged - Shows Total Fleet Status)
    // =========================================================
    $queryTrucksMetrics = "
        SELECT
            SUM(CASE WHEN truckStatus = 'Available' THEN 1 ELSE 0 END) AS available,
            SUM(CASE WHEN truckStatus = 'Unavailable' THEN 1 ELSE 0 END) AS unavailable,
            SUM(CASE WHEN truckStatus = 'In Transit' THEN 1 ELSE 0 END) AS in_transit,
            SUM(CASE WHEN truckStatus = 'Maintenance' THEN 1 ELSE 0 END) AS maintenance,
            COUNT(truckID) AS total_trucks
        FROM Trucks;
    ";
    $resultTrucksMetrics = $conn->query($queryTrucksMetrics);
    $trucksData = $resultTrucksMetrics->fetch_assoc();
    $totalTrucks = $trucksData['total_trucks'];


    // =========================================================
    // 3. DELIVERIES (FILTERED: Current Calendar Month Only)
    // =========================================================
    // LOGIC: Check if the Year and Month of the ETA match the Current Year and Current Month.
    // This effectively resets the stats to 0 on the 1st of every month.
    $queryDeliveries = "
        SELECT
            SUM(CASE WHEN deliveryStatus = 'Completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE WHEN deliveryStatus = 'Cancelled' THEN 1 ELSE 0 END) AS cancelled,
            SUM(CASE WHEN deliveryStatus = 'In Progress' THEN 1 ELSE 0 END) AS in_progress,
            SUM(CASE WHEN deliveryStatus = 'Inactive' THEN 1 ELSE 0 END) AS inactive,
            COUNT(deliveryID) AS total_deliveries
        FROM Deliveries
        WHERE MONTH(estimatedTimeOfArrival) = MONTH(NOW()) 
          AND YEAR(estimatedTimeOfArrival) = YEAR(NOW());
    ";
    $resultDeliveries = $conn->query($queryDeliveries);
    $deliveriesData = $resultDeliveries->fetch_assoc();
    
    // Handle NULLs (returns 0 if no deliveries exist for the current month)
    $totalDeliveries = $deliveriesData['total_deliveries'] ?? 0;
    $deliveriesData['completed'] = $deliveriesData['completed'] ?? 0;
    $deliveriesData['cancelled'] = $deliveriesData['cancelled'] ?? 0;
    $deliveriesData['in_progress'] = $deliveriesData['in_progress'] ?? 0;
    $deliveriesData['inactive'] = $deliveriesData['inactive'] ?? 0;


    // --- PERCENTAGE CALCULATIONS (for Legend Display) ---
    
    // Drivers
    $driversData['assigned_percent'] = $totalDrivers > 0 ? round(($driversData['assigned'] / $totalDrivers) * 100) : 0;
    $driversData['available_percent'] = $totalDrivers > 0 ? round(($driversData['available'] / $totalDrivers) * 100) : 0;
    $driversData['unavailable_percent'] = $totalDrivers > 0 ? round(($driversData['unavailable'] / $totalDrivers) * 100) : 0;
    
    // Trucks
    $trucksData['available_percent'] = $totalTrucks > 0 ? round(($trucksData['available'] / $totalTrucks) * 100) : 0;
    $trucksData['unavailable_percent'] = $totalTrucks > 0 ? round(($trucksData['unavailable'] / $totalTrucks) * 100) : 0;
    $trucksData['in_transit_percent'] = $totalTrucks > 0 ? round(($trucksData['in_transit'] / $totalTrucks) * 100) : 0;
    $trucksData['maintenance_percent'] = $totalTrucks > 0 ? round(($trucksData['maintenance'] / $totalTrucks) * 100) : 0;
    
    // Deliveries
    $deliveriesData['completed_percent'] = $totalDeliveries > 0 ? round(($deliveriesData['completed'] / $totalDeliveries) * 100) : 0;
    $deliveriesData['cancelled_percent'] = $totalDeliveries > 0 ? round(($deliveriesData['cancelled'] / $totalDeliveries) * 100) : 0;
    $deliveriesData['in_progress_percent'] = $totalDeliveries > 0 ? round(($deliveriesData['in_progress'] / $totalDeliveries) * 100) : 0;
    $deliveriesData['inactive_percent'] = $totalDeliveries > 0 ? round(($deliveriesData['inactive'] / $totalDeliveries) * 100) : 0;

}
?>