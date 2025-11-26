<?php
include("connect.php");

// Security Check: Only allow 'Driver' users
if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Driver') {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$firstName = $_SESSION['firstName'];
$accountType = htmlspecialchars($_SESSION['accountType']);
$driverId = $_SESSION['accountID'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>driverpage.css"> 
    <title>Driver Dashboard</title>
</head>
<body>
    <div class="container">
        <div class="sidebar">
            <h1 class="logo">FleetNav</h1>
            <div class="nav">
                <button class="nav-btn active">My Deliveries</button>
                <button class="nav-btn">My Truck</button>
                <button class="nav-btn">Profile</button>
            </div>
        </div>

        <div class="content-wrapper">
            
            <header class="top-nav-bar">
                <h2 class="page-title">DRIVER DASHBOARD</h2>
                <div class="user-profile">
                    <span class="user-name"><?php echo htmlspecialchars($firstName); ?> (<?php echo $accountType; ?>)</span>
                    <button class="logout-btn" onclick="window.location.href='logout.php';">Logout</button>
                </div>
            </header>

            <div class="main-content-area">
                <section id="current-assignment">
                    <h3>Current Assignment (ID: <?php echo htmlspecialchars($driverId); ?>)</h3>
                    <p>Delivery Route: Origin to Destination</p>
                    <p>Status: Inactive / In-Transit / Delivered</p>
                    <button class="action-btn">View Route Details</button>
                </section>
            </div>
        </div>
    </div>
</body>
</html>