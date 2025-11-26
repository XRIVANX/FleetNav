<?php

$target_dir = "uploads/";
$uploadOk = 1;

// 1. Check if the directory exists and is writable, otherwise try to create it
if (!is_dir($target_dir)) {
    if (!mkdir($target_dir, 0775, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Upload directory does not exist and could not be created.']);
        $uploadOk = 0;
    }
}

if ($uploadOk == 1 && $_FILES['image']['error'] == 0) {
    $original_filename = basename($_FILES["image"]["name"]);
    $target_file = $target_dir . $original_filename;
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

    // 2. Check if file is an actual image (optional but recommended)
    $check = getimagesize($_FILES["image"]["tmp_name"]);
    if($check === false) {
        echo json_encode(['status' => 'error', 'message' => 'File is not an image.']);
        $uploadOk = 0;
    }

    // 3. Check file size (e.g., limit to 5MB)
    if ($_FILES["image"]["size"] > 5000000) { 
        echo json_encode(['status' => 'error', 'message' => 'Sorry, your file is too large (max 5MB).']);
        $uploadOk = 0;
    }

    // 4. Allow certain file formats
    if($imageFileType != "jpg" && $imageFileType != "png" && $imageFileType != "jpeg" && $imageFileType != "gif" ) {
        echo json_encode(['status' => 'error', 'message' => 'Sorry, only JPG, JPEG, PNG & GIF files are allowed.']);
        $uploadOk = 0;
    }

    // 5. If everything is checked, try to upload the file
    if ($uploadOk == 1) {
        if (move_uploaded_file($_FILES["image"]["tmp_name"], $target_file)) {
            // Success: Return only the filename
            echo json_encode(['status' => 'success', 'file' => $original_filename]); 
        } else {
            // Failure: Likely due to permissions
            echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file. Check folder permissions (should be 775 or 777).']);
        }
    }
} else if ($_FILES['image']['error'] != 0) {
    // Handle general PHP upload errors
    echo json_encode(['status' => 'error', 'message' => 'File error code: ' . $_FILES['image']['error']]);
}

?>