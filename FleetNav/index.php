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
                <div class="switch-link">Don't have an account? 
                    <a href="#" onclick="showRegisterPage('Admin'); return false;">Register as Admin</a> 
                    <span class="or-text" style="display:inline; margin: 0 10px;">OR</span>
                    <a href="#" onclick="showSuperAdminRegPage(); return false;">Register as Super Admin</a>
                </div>
                <div class="switch-link">
                    <a href="#" onclick="showChangeSARegPassPage(); return false;">Change Super Admin Registration Password</a>
                </div>
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
                    
                    <div class="form-group" id="superAdminPassGroup" style="display: none;">
                        <label for="super_admin_reg_pass">Super Admin Registration Password</label>
                        <input type="password" id="super_admin_reg_pass" name="super_admin_reg_pass">
                    </div>
                    
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
            
            <div id="change-super-admin-reg-pass" class="page">
                <h2>CHANGE REGISTRATION PASSWORD</h2>
                <div class="form-info-text">
                    Enter your Super Admin credentials and the new Registration Password.
                </div>
                <form class="auth-form" method="POST">
                    <input type="hidden" name="change_sar_pass_submit" value="1">
                    <div class="form-group">
                        <label for="sa_auth_email">Super Admin Email</label>
                        <input type="email" id="sa_auth_email" name="sa_auth_email" required>
                    </div>
                    <div class="form-group">
                        <label for="sa_auth_password">Super Admin Password</label>
                        <input type="password" id="sa_auth_password" name="sa_auth_password" required>
                    </div>
                    <hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;">
                    <div class="form-group">
                        <label for="new_reg_pass">New Registration Password</label>
                        <input type="password" id="new_reg_pass" name="new_reg_pass" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_reg_pass">Confirm New Registration Password</label>
                        <input type="password" id="confirm_new_reg_pass" name="confirm_new_reg_pass" required>
                    </div>
                    <button type="submit" class="auth-btn">CHANGE PASSWORD</button>
                </form>
                <div class="switch-link"><a href="#" onclick="showPage('login-admin'); return false;">&larr; Back to Admin Login</a></div>
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
        // Show the appropriate login page based on role           
            if (role === 'Admin') {
                showPage('login-admin');
            } else if (role === 'Super Admin') {
                showPage('login-admin');          
            } else if (role === 'Driver') {
                showPage('login-driver');
            } else {
                showPage('role-select');
            }
        }

        function showRegisterPage(role) {
            document.getElementById('account_role_type').value = role;
            document.getElementById('register-header').textContent = role.toUpperCase() + " REGISTRATION";
            
            // --- [MODIFIED] Logic for Admin/Driver registration ---
            if (role === 'Admin') {
                // Show the password field for Admin
                document.getElementById('superAdminPassGroup').style.display = 'block';
                const passInput = document.getElementById('super_admin_reg_pass');
                passInput.setAttribute('required', 'required');
                
                // Change the label text
                document.querySelector("label[for='super_admin_reg_pass']").textContent = "Admin Registration Password";
            } else {
                // Hide for Driver
                document.getElementById('superAdminPassGroup').style.display = 'none';
                document.getElementById('super_admin_reg_pass').removeAttribute('required');
            }
            // --------------------------------------------------------------------------

            showPage('register');
        }
        
        // --- FUNCTION for Super Admin Registration ---
        function showSuperAdminRegPage() {
            document.getElementById('account_role_type').value = 'Super Admin';
            document.getElementById('register-header').textContent = "SUPER ADMIN REGISTRATION";

            // --- Show Super Admin Pass field and make it required ---
            document.getElementById('superAdminPassGroup').style.display = 'block';
            const passInput = document.getElementById('super_admin_reg_pass');
            passInput.setAttribute('required', 'required');
            
            // Change the label text
            document.querySelector("label[for='super_admin_reg_pass']").textContent = "Super Admin Registration Password";
            // ------------------------------------------------------------
            
            showPage('register');
        }
        
        // --- NEW FUNCTION for Changing Super Admin Registration Password ---
        function showChangeSARegPassPage() {
            showPage('change-super-admin-reg-pass');
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