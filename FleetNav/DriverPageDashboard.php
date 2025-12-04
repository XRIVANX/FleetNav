<?php
// Note: $assignedTruck and $activeDelivery are available from DriverPage.php context
?>

<div id="dashboard-widgets">
    
    <!-- TRUCK WIDGET -->
    <div class="widget">
        <div class="widget-header">
            <h3><i class="fas fa-truck-moving"></i> My Vehicle</h3>
        </div>
        <div class="widget-body">
            <?php if ($assignedTruck): ?>
                <div class="info-row">
                    <span class="label">Name/Model:</span>
                    <span class="value"><?php echo htmlspecialchars($assignedTruck['truckName']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Plate Number:</span>
                    <span class="value badge"><?php echo htmlspecialchars($assignedTruck['plateNumber']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status:</span>
                    <span class="value status-<?php echo strtolower(str_replace(' ', '-', $assignedTruck['truckStatus'])); ?>">
                        <?php echo htmlspecialchars($assignedTruck['truckStatus']); ?>
                    </span>
                </div>
                <div class="truck-img-preview">
                    <?php 
                    $tImg = !empty($assignedTruck['truckImg']) ? BASE_URL . "uploads/" . $assignedTruck['truckImg'] : BASE_URL . "assets/default-truck.png"; 
                    ?>
                    <img src="<?php echo $tImg; ?>" alt="Truck Image">
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-exclamation-circle"></i>
                    <p>You are not currently assigned to any truck.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- DELIVERY WIDGET -->
    <div class="widget">
        <div class="widget-header">
            <h3><i class="fas fa-box-open"></i> Current Assignment</h3>
        </div>
        <div class="widget-body">
            <?php if ($activeDelivery): ?>
                <div class="info-row">
                    <span class="label">Product:</span>
                    <span class="value highlight"><?php echo htmlspecialchars($activeDelivery['productName']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Destination:</span>
                    <span class="value"><?php echo htmlspecialchars($activeDelivery['destination']); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">ETA:</span>
                    <span class="value"><?php echo date('M j, h:i A', strtotime($activeDelivery['estimatedTimeOfArrival'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="label">Status:</span>
                    <span class="value status-<?php echo strtolower(str_replace(' ', '-', $activeDelivery['deliveryStatus'])); ?>">
                        <?php echo htmlspecialchars($activeDelivery['deliveryStatus']); ?>
                    </span>
                </div>
                <button class="action-btn" onclick="window.location.href='DriverPage.php?view=delivery'">
                    View Full Details & Route
                </button>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-check-circle"></i>
                    <p>No active deliveries assigned to your truck.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>