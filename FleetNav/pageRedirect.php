<?php

include("connect.php");
// Security Check: Only allow 'Admin' users
if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Admin') {
    header("Location: " . BASE_URL . "index.php");
    exit();
}
else if (!isset($_SESSION['accountID']) || $_SESSION['accountType'] !== 'Driver') {
    header("Location: " . BASE_URL . "index.php");
    exit();
}


?>