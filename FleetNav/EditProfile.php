<?php
// Ensure connect.php is included to establish DB connection ($conn) and start session
include("connect.php"); 

// Helper function for bind_param with dynamic array
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) { // Reference is required for PHP 5.3+
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

// Logger Function
function logAction($conn, $accountID, $actionType, $details) {
    if ($conn && $accountID > 0) {
        $stmt = $conn->prepare("INSERT INTO Action_Logs (accountID, action_type, action_details) VALUES (?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("iss", $accountID, $actionType, $details);
            $stmt->execute();
            $stmt->close();
        }
    }
}

// 1. Security Check: Ensure user is logged in
if (!isset($_SESSION['accountID'])) {
    header("Location: " . BASE_URL . "index.php");
    exit();
}

$accountID = $_SESSION['accountID'];
$accountType = $_SESSION['accountType'];
$message = '';
$error = false;

// 2. Determine Back URL based on accountType
$backUrl = 'index.php'; 
if ($accountType === 'Admin' || $accountType === 'Super Admin') {
    $backUrl = 'AdminPage.php';
} elseif ($accountType === 'Driver') {
    $backUrl = 'DriverPage.php';
}

// 3. Handle Profile Update POST Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstName = trim($_POST['firstName']);
    $lastName = trim($_POST['lastName']);
    $email = trim($_POST['email']);
    $contactNo = trim($_POST['contactNo']);
    $address = trim($_POST['address']);
    $age = (int)$_POST['age'];

    // Update Query Setup
    $updateFields = [];
    $bindTypes = '';
    $bindValues = [];

    // Fields to be updated (Excluding accountType, which is user's request)
    $updateFields[] = "firstName = ?"; $bindTypes .= 's'; $bindValues[] = $firstName;
    $updateFields[] = "lastName = ?"; $bindTypes .= 's'; $bindValues[] = $lastName;
    $updateFields[] = "email = ?"; $bindTypes .= 's'; $bindValues[] = $email;
    $updateFields[] = "contactNo = ?"; $bindTypes .= 's'; $bindValues[] = $contactNo;
    $updateFields[] = "address = ?"; $bindTypes .= 's'; $bindValues[] = $address;
    $updateFields[] = "age = ?"; $bindTypes .= 'i'; $bindValues[] = $age;
    
    $newProfileImg = $_SESSION['profileImg'] ?? '';
    
    // Handle Profile Image Upload
    if (isset($_FILES['profileImg']) && $_FILES['profileImg']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        
        $fileTmpPath = $_FILES['profileImg']['tmp_name'];
        $fileExtension = strtolower(pathinfo($_FILES['profileImg']['name'], PATHINFO_EXTENSION));
        $newFileName = 'profile_' . $accountID . '_' . time() . '.' . $fileExtension;
        $destPath = $uploadDir . $newFileName;

        if(move_uploaded_file($fileTmpPath, $destPath)) {
            $newProfileImg = $newFileName; 
            if (!empty($_SESSION['profileImg']) && strpos($_SESSION['profileImg'], 'blank-profile') === false) {
                @unlink($uploadDir . $_SESSION['profileImg']);
            }
        } else {
            $message = '<div class="alert-danger">File upload failed.</div>';
            $error = true;
        }
    }
    
    if (!$error) {
        // Update profileImg field
        $updateFields[] = "profileImg = ?"; $bindTypes .= 's'; $bindValues[] = $newProfileImg;

        // Check for password update
        if (!empty($_POST['newPassword'])) {
            $hashedPassword = password_hash($_POST['newPassword'], PASSWORD_DEFAULT);
            $updateFields[] = "PASSWORD = ?"; $bindTypes .= 's'; $bindValues[] = $hashedPassword;
        }

        // Finalize query
        $query = "UPDATE Accounts SET " . implode(', ', $updateFields) . " WHERE accountID = ?";
        $bindTypes .= 'i'; $bindValues[] = $accountID;

        // Execute update
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $params = array_merge([$bindTypes], $bindValues);
            call_user_func_array([$stmt, 'bind_param'], refValues($params));
            
            if ($stmt->execute()) {
                // Update session variables
                $_SESSION['firstName'] = $firstName;
                $_SESSION['lastName'] = $lastName;
                $_SESSION['email'] = $email;
                $_SESSION['contactNo'] = $contactNo;
                $_SESSION['address'] = $address;
                $_SESSION['age'] = $age;
                $_SESSION['profileImg'] = $newProfileImg;
                
                // [ADDED] Log the action
                $logDetails = "User updated their own profile (Name: $firstName $lastName).";
                logAction($conn, $accountID, 'PROFILE_UPDATE', $logDetails);

                $message = '<div class="alert-success">Profile updated successfully!</div>';
            } else {
                $message = '<div class="alert-danger">Database error: ' . $stmt->error . '</div>';
            }
            $stmt->close();
        } else {
             $message = '<div class="alert-danger">SQL prepare failed: ' . $conn->error . '</div>';
        }
    }
}

// 4. Fetch current user data for the form display
$currentUser = null;
$query = "SELECT accountID, firstName, lastName, age, accountType, contactNo, email, address, profileImg FROM Accounts WHERE accountID = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $accountID);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 1) {
    $currentUser = $result->fetch_assoc();
} else {
    header("Location: " . BASE_URL . "logout.php");
    exit();
}
$stmt->close();

// Profile Image Source Logic
$profileFilename = $currentUser['profileImg'] ?? null;
if (!empty($profileFilename)) {
    // Logic to correctly set image path from filename
    $profileImgSrc = BASE_URL . "uploads/" . $profileFilename;
    if (strpos($profileFilename, 'uploads/') === 0) {
         $profileImgSrc = BASE_URL . $profileFilename;
    }
} else {
    $profileImgSrc = BASE_URL . "blank-profile-picture-973460_960_720-587709513.png";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>editprofile.css"> 
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Edit Profile - <?php echo htmlspecialchars($currentUser['firstName']); ?></title>
        
</head>
<body>
    <div class="profile-container">
        
        <button class="submit-btn back-btn" onclick="window.location.href='<?php echo $backUrl; ?>';">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </button>
        
        <div class="profile-header">
            <h2>Edit Your Profile</h2>
        </div>
        
        <?php echo $message; // Display status messages ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="profile-img-upload">
                <label for="profileImg">
                    <img src="<?php echo $profileImgSrc; ?>" alt="Profile Image" id="profileImagePreview">
                    <p style="font-size: 0.9em; color: #007bff; margin-top: 5px;">Click image to change</p>
                </label>
                <input type="file" id="profileImg" name="profileImg" accept="image/*" style="display:none;">
            </div>

            <div class="role-display">
                <i class="fas fa-user-tag"></i> Your Role: <strong><?php echo htmlspecialchars($currentUser['accountType']); ?></strong>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="firstName">First Name *</label>
                    <input type="text" id="firstName" name="firstName" value="<?php echo htmlspecialchars($currentUser['firstName']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="lastName">Last Name *</label>
                    <input type="text" id="lastName" name="lastName" value="<?php echo htmlspecialchars($currentUser['lastName']); ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email']); ?>" required>
                </div>
                <div class="form-group">
                    <label for="age">Age</label>
                    <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($currentUser['age']); ?>" min="18" max="100">
                </div>
            </div>

            <div class="form-group">
                <label for="contactNo">Contact Number *</label>
                <input type="text" id="contactNo" name="contactNo" value="<?php echo htmlspecialchars($currentUser['contactNo']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="address">Address *</label>
                <textarea id="address" name="address" rows="2" required><?php echo htmlspecialchars($currentUser['address']); ?></textarea>
            </div>

            <div class="form-group">
                <label for="newPassword">New Password (Leave blank to keep current)</label>
                <input type="password" id="newPassword" name="newPassword" placeholder="Enter new password">
            </div>

            <button type="submit" name="update_profile" class="submit-btn">SAVE PROFILE CHANGES</button>
        </form>
    </div>
    
    <script>
        // JavaScript for real-time image preview
        document.getElementById('profileImg').onchange = function (evt) {
            const [file] = evt.target.files
            if (file) {
                document.getElementById('profileImagePreview').src = URL.createObjectURL(file)
            }
        }
    </script>
</body>
</html>