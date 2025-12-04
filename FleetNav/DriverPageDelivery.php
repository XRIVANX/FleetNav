<?php
// Note: $assignedTruck and $activeDelivery are available from DriverPage.php context
?>

<?php if (!$assignedTruck): ?>
    <div class="alert alert-danger">
        <h3>No Vehicle Assigned</h3>
        <p>You must be assigned to a truck before you can view deliveries.</p>
    </div>
<?php elseif (!$activeDelivery): ?>
    <div class="alert alert-info">
        <h3>No Active Deliveries</h3>
        <p>Your truck (<?php echo htmlspecialchars($assignedTruck['truckName']); ?>) currently has no pending deliveries.</p>
    </div>
<?php else: ?>
    
    <div class="delivery-container">
        <!-- LEFT COLUMN: DETAILS -->
        <div class="delivery-details-card">
            <div class="card-header">
                <h2>Delivery #<?php echo $activeDelivery['deliveryID']; ?></h2>
                <span class="badge status-<?php echo strtolower(str_replace(' ', '-', $activeDelivery['deliveryStatus'])); ?>">
                    <?php echo htmlspecialchars($activeDelivery['deliveryStatus']); ?>
                </span>
            </div>
            
            <div class="detail-group">
                <label>Product</label>
                <p class="highlight"><?php echo htmlspecialchars($activeDelivery['productName']); ?></p>
            </div>
            
            <div class="detail-group">
                <label>Description</label>
                <p><?php echo htmlspecialchars($activeDelivery['productDescription'] ?: 'No description provided.'); ?></p>
            </div>

            <div class="route-info">
                <div class="route-point">
                    <i class="fas fa-map-marker-alt origin-icon"></i>
                    <div>
                        <label>Origin</label>
                        <p id="originText"><?php echo htmlspecialchars($activeDelivery['origin']); ?></p>
                    </div>
                </div>
                <div class="route-line"></div>
                <div class="route-point">
                    <i class="fas fa-map-pin dest-icon"></i>
                    <div>
                        <label>Destination</label>
                        <p id="destText"><?php echo htmlspecialchars($activeDelivery['destination']); ?></p>
                    </div>
                </div>
            </div>

            <div class="grid-2">
                <div class="detail-group">
                    <label>Est. Distance</label>
                    <p><?php echo htmlspecialchars($activeDelivery['deliveryDistance']); ?></p>
                </div>
                <div class="detail-group">
                    <label>Allocated Gas</label>
                    <p><?php echo number_format($activeDelivery['allocatedGas']); ?> Liters</p>
                </div>
            </div>

            <div class="detail-group">
                <label>Target Arrival (ETA)</label>
                <p class="eta-text"><?php echo date('F j, Y, g:i a', strtotime($activeDelivery['estimatedTimeOfArrival'])); ?></p>
            </div>

            <div class="action-area">
                <?php if ($activeDelivery['deliveryStatus'] === 'Inactive'): ?>
                    <form method="POST">
                        <input type="hidden" name="action_type" value="start_delivery">
                        <button type="submit" class="start-btn">
                            <i class="fas fa-play"></i> START DELIVERY
                        </button>
                    </form>
                <?php elseif ($activeDelivery['deliveryStatus'] === 'In Progress' || $activeDelivery['deliveryStatus'] === 'On Route'): ?>
                    <div class="status-box">
                        <i class="fas fa-spinner fa-spin"></i> Delivery is In Progress
                        <small>Only Admins can mark this as Completed.</small>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- RIGHT COLUMN: MAP -->
        <div class="delivery-map-card">
            <div id="driverMap">Loading Map...</div>
        </div>
    </div>

    <!-- GOOGLE MAPS SCRIPT -->
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCe0QPh_Jshd8UqAUsqYSrNmg7itHuzv0w&callback=initDriverMap" async defer></script>
    <script>
        function initDriverMap() {
            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer();
            
            // Center map roughly on Davao (will be overridden by route bounds)
            const map = new google.maps.Map(document.getElementById("driverMap"), {
                zoom: 10,
                center: { lat: 7.1907, lng: 125.4553 },
                disableDefaultUI: false,
            });

            directionsRenderer.setMap(map);

            const origin = "<?php echo htmlspecialchars($activeDelivery['origin']); ?>";
            const destination = "<?php echo htmlspecialchars($activeDelivery['destination']); ?>";

            if(origin && destination) {
                const request = {
                    origin: origin,
                    destination: destination,
                    travelMode: google.maps.TravelMode.DRIVING
                };

                directionsService.route(request, (result, status) => {
                    if (status === "OK") {
                        directionsRenderer.setDirections(result);
                    } else {
                        console.error("Directions request failed: " + status);
                        document.getElementById("driverMap").innerHTML = "<p style='padding:20px; text-align:center;'>Could not load route map. Please check address details.</p>";
                    }
                });
            }
        }
    </script>
<?php endif; ?>