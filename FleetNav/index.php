<?php

include("indexFunctions.php");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FleetNav - Login</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>loginstyle.css">
    <style>
        /* --- Pop-up Modal CSS --- */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6); /* Dark semi-transparent background */
            display: none; /* Hidden by default */
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            text-align: center;
            animation: fadeIn 0.3s ease-out;
        }
        
        .modal-content.success {
            border-top: 5px solid #28a745;
        }

        .modal-content.error {
            border-top: 5px solid #dc3545;
        }
        
        .modal-header {
            font-size: 1.5rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .modal-body {
            margin-bottom: 20px;
            color: #555;
        }

        .modal-close-btn {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 1rem;
            transition: background-color 0.2s;
        }

        .modal-close-btn:hover {
            background-color: #0056b3;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <h1 class="site-logo">FleetNav</h1>

    <div class="main-content">
        <div class="container">

            <div id="role-select" class="page <?php echo (!isset($status_data)) ? 'active' : ''; ?>">
                <h2>LOGIN AS</h2>
                <button class="choice-btn" onclick="showLoginPage('Admin')">Admin</button>
                <div class="or-text">OR</div>
                <button class="choice-btn" onclick="showLoginPage('Driver')">Driver</button>
            </div>

            <div id="login-admin" class="page">
                <h2>ADMIN LOGIN</h2>
                <form class="auth-form" method="POST">
                    <input type="hidden" name="account_type" value="Admin">
                    <div class="form-group">
                        <label for="admin_email">Email Address</label>
                        <input type="email" id="admin_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="admin_password">Password</label>
                        <input type="password" id="admin_password" name="password" required>
                    </div>
                    <div class="form-link"><a href="#" onclick="showPage('reset-password'); return false;">Forgot Password?</a></div>
                    <button type="submit" name="login_submit" class="auth-btn">LOGIN</button>
                </form>
                <div class="switch-link">Don't have an account? <a href="#" onclick="showRegisterPage('Admin'); return false;">Register as Admin</a></div>
                <div class="switch-link"><a href="#" onclick="showPage('role-select'); return false;">&larr; Back to Role Selection</a></div>
            </div>

            <div id="login-driver" class="page">
                <h2>DRIVER LOGIN</h2>
                <form class="auth-form" method="POST">
                    <input type="hidden" name="account_type" value="Driver">
                    <div class="form-group">
                        <label for="driver_email">Email Address</label>
                        <input type="email" id="driver_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="driver_password">Password</label>
                        <input type="password" id="driver_password" name="password" required>
                    </div>
                    <div class="form-link"><a href="#" onclick="showPage('reset-password'); return false;">Forgot Password?</a></div>
                    <button type="submit" name="login_submit" class="auth-btn">LOGIN</button>
                </form>
                <div class="switch-link">Don't have an account? <a href="#" onclick="showRegisterPage('Driver'); return false;">Register as Driver</a></div>
                <div class="switch-link"><a href="#" onclick="showPage('role-select'); return false;">&larr; Back to Role Selection</a></div>
            </div>

            <div id="register" class="page">
                <h2 id="register-header">REGISTRATION</h2>
                <form class="auth-form" method="POST">
                    <input type="hidden" id="account_role_type" name="account_role_type" value="Driver">
                    
                    <div class="form-group">
                        <label for="reg_firstName">First Name</label>
                        <input type="text" id="reg_firstName" name="firstName" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_lastName">Last Name</label>
                        <input type="text" id="reg_lastName" name="lastName" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_email">Email Address</label>
                        <input type="email" id="reg_email" name="email" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_contactNo">Contact No.</label>
                        <input type="tel" id="reg_contactNo" name="contactNo">
                    </div>
                    <div class="form-group">
                        <label for="reg_address">Address</label>
                        <input type="text" id="reg_address" name="address">
                    </div>
                    <div class="form-group">
                        <label for="reg_password">Password</label>
                        <input type="password" id="reg_password" name="password" required>
                    </div>
                    <div class="form-group">
                        <label for="reg_confirm_password">Confirm Password</label>
                        <input type="password" id="reg_confirm_password" name="confirm_password" required>
                    </div>

                    <div class="form-group image-upload-group">
                        <label for="profileImgInput">Profile Picture (Optional)</label>
                        
                        <input type="hidden" id="uploadedProfileImagePath" name="uploadedProfileImagePath" value="">
                        
                        <input type="file" id="profileImgInput" name="profileImg" accept="image/*" style="display: none;">

                        <div id="profileDropArea" class="drop-area profile-drop-area">
                            <p>Drag & drop a file here, or <span id="profileSelectFileLink">select a file</span>.</p>
                        </div>

                        <div id="profilePreviewContainer" class="preview-container" style="display: none;">
                            <img id="profileImagePreview" src="" alt="Profile Image Preview" class="profile-preview-img">
                            <p id="profileFileName"></p>
                            <button type="button" id="profileClearImageBtn" class="clear-image-btn">Clear Image</button>
                            <div id="profileUploadMessage" class="upload-message"></div>
                        </div>
                    </div>
                    <button type="submit" name="register_submit" class="auth-btn">REGISTER</button>
                </form>
                <div class="switch-link">Already have an account? <a href="#" onclick="showPage('role-select'); return false;">Login</a></div>
            </div>

            <div id="reset-password" class="page">
                <h2>PASSWORD RESET</h2>
                <div class="form-info-text">
                    Enter your account's email address and we will send you a link to reset your password.
                </div>
                <form class="auth-form" method="POST">
                    <div class="form-group">
                        <label for="reset_email">Email Address</label>
                        <input type="email" id="reset_email" name="email" required>
                    </div>
                    <button type="submit" name="reset_request_submit" class="auth-btn">SEND RESET LINK</button>
                </form>
                <div class="switch-link"><a href="#" onclick="showPage('role-select'); return false;">&larr; Back to Login</a></div>
            </div>

        </div>
    </div>
    
    <div id="statusModal" class="modal-overlay">
        <div class="modal-content">
            <h3 id="modalHeader" class="modal-header"></h3>
            <p id="modalBody" class="modal-body"></p>
            <button id="modalCloseBtn" class="modal-close-btn">OK</button>
        </div>
    </div>

    <script>
        const statusData = <?php echo json_encode($status_data); ?>;
        const modalOverlay = document.getElementById('statusModal');
        const modalContent = modalOverlay.querySelector('.modal-content');
        const modalHeader = document.getElementById('modalHeader');
        const modalBody = document.getElementById('modalBody');
        const modalCloseBtn = document.getElementById('modalCloseBtn');
        
        function showPage(pageId) {
            document.querySelectorAll('.page').forEach(page => {
                page.classList.remove('active');
            });
            document.getElementById(pageId).classList.add('active');
        }

        function showLoginPage(role) {
            // role will be 'Admin' or 'Driver'
            if (role === 'Admin') {
                showPage('login-admin');
            } else if (role === 'Driver') {
                showPage('login-driver');
            } else {
                showPage('role-select'); // Fallback
            }
        }

        function showRegisterPage(role) {
            document.getElementById('account_role_type').value = role;
            document.getElementById('register-header').textContent = role.toUpperCase() + " REGISTRATION";
            showPage('register');
        }
        
        /**
         * Displays the pop-up modal and sets up the redirection on close.
         */
        function showStatusModal(type, message, role) {
            // Set modal style and content
            modalContent.className = 'modal-content ' + type;
            modalHeader.textContent = type === 'success' ? 'Success! 🎉' : 'Error! ❌';
            modalBody.textContent = message;
            modalOverlay.style.display = 'flex'; // Show modal

            // Make sure the background shows the relevant page immediately (e.g., if login failed, show login form behind modal)
            showLoginPage(role);

            // Set up the redirect action on button click
            modalCloseBtn.onclick = function() {
                modalOverlay.style.display = 'none'; // Hide modal
                // Redirects/stays on the appropriate login page based on the role
                showLoginPage(role); 
            };
            
            // Also close if the user clicks the overlay
            modalOverlay.onclick = function(event) {
                if (event.target === modalOverlay) {
                    modalOverlay.style.display = 'none';
                    showLoginPage(role);
                }
            };
        }

        // --- Profile Image Upload Logic Functions ---
        // (Copied unchanged from your provided file)
        let profileFileInput, profileDropArea, profileImagePreview, profileFileNameDisplay, profileClearImageBtn, profilePreviewContainer, profileUploadMessage, profileSelectFileLink, uploadedProfileImagePath;

        function profileClearSelection() {
            if (profileFileInput) profileFileInput.value = '';
            if (profileImagePreview) profileImagePreview.src = '';
            if (profileFileNameDisplay) profileFileNameDisplay.textContent = '';
            if (profilePreviewContainer) profilePreviewContainer.style.display = 'none';
            if (profileDropArea) profileDropArea.style.display = 'block';
            if (uploadedProfileImagePath) uploadedProfileImagePath.value = '';
            if (profileUploadMessage) profileUploadMessage.textContent = '';
        }

        function profileUploadFile(file) {
            if (profileUploadMessage) {
                profileUploadMessage.textContent = 'Uploading...';
                profileUploadMessage.style.color = '#ffc107'; 
            }
            
            const formData = new FormData();
            formData.append('image', file);
            
            fetch('upload.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    const filePath = 'uploads/' + data.file; 
                    if (uploadedProfileImagePath) uploadedProfileImagePath.value = filePath;
                    if (profileUploadMessage) {
                        profileUploadMessage.textContent = 'Upload complete.';
                        profileUploadMessage.style.color = '#28a745'; 
                    }
                } else {
                    if (profileUploadMessage) {
                        profileUploadMessage.textContent = 'Upload failed: ' + data.message;
                        profileUploadMessage.style.color = 'red';
                    }
                    profileClearSelection();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                profileClearSelection();
            });
        }

        function profileShowPreview(file) {
            if (file && file.type.startsWith('image/')) {
                if (profileDropArea) profileDropArea.style.display = 'none';
                if (profilePreviewContainer) profilePreviewContainer.style.display = 'flex'; 
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (profileImagePreview) profileImagePreview.src = e.target.result;
                };
                reader.readAsDataURL(file);

                if (profileFileNameDisplay) profileFileNameDisplay.textContent = file.name;
                profileUploadFile(file);
            } else {
                alert('Please select an image file.');
                profileClearSelection();
            }
        }
        
        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        document.addEventListener('DOMContentLoaded', () => {
            // Initialize elements
            profileFileInput = document.getElementById('profileImgInput');
            profileDropArea = document.getElementById('profileDropArea');
            profileImagePreview = document.getElementById('profileImagePreview');
            profileFileNameDisplay = document.getElementById('profileFileName');
            profileClearImageBtn = document.getElementById('profileClearImageBtn');
            profilePreviewContainer = document.getElementById('profilePreviewContainer');
            profileUploadMessage = document.getElementById('profileUploadMessage');
            profileSelectFileLink = document.getElementById('profileSelectFileLink');
            uploadedProfileImagePath = document.getElementById('uploadedProfileImagePath');

            // --- MAIN STATUS MODAL LOGIC ---
            if (statusData && statusData.message) {
                // If status data exists (from Login OR Registration), show the modal
                showStatusModal(statusData.type, statusData.message, statusData.role);
            } else {
                // Otherwise, show the role selection page by default
                showPage('role-select');
            }

            // --- Event Listeners for Upload ---
            if (profileSelectFileLink) profileSelectFileLink.addEventListener('click', () => { profileFileInput.click(); });
            if (profileFileInput) profileFileInput.addEventListener('change', (e) => { const file = e.target.files[0]; if (file) profileShowPreview(file); });
            if (profileClearImageBtn) profileClearImageBtn.addEventListener('click', profileClearSelection);

            if (profileDropArea) {
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    profileDropArea.addEventListener(eventName, preventDefaults, false);
                });
                ['dragenter', 'dragover'].forEach(eventName => {
                    profileDropArea.addEventListener(eventName, () => { profileDropArea.classList.add('dragover'); }, false);
                });
                ['dragleave', 'drop'].forEach(eventName => {
                    profileDropArea.addEventListener(eventName, () => { profileDropArea.classList.remove('dragover'); }, false);
                });
                profileDropArea.addEventListener('drop', (e) => {
                    let dt = e.dataTransfer;
                    let files = dt.files;
                    if (files.length) profileShowPreview(files[0]);
                }, false);
            }
        });
    </script>
</body>
</html>