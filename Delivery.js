document.addEventListener("DOMContentLoaded", () => {
    const sidebar = document.getElementById("sidebar");
    const collapseBtn = document.querySelector(".collapse-btn");
    const mobileBtn = document.getElementById("mobileMenuBtn");
  
    // Desktop collapse button
    if (collapseBtn) {
      collapseBtn.addEventListener("click", () => {
        if (window.innerWidth > 768) {
          sidebar.classList.toggle("collapsed");
        } else {
          sidebar.classList.toggle("open"); 
        }
      });
    }
  
    // Mobile topbar menu button
    if (mobileBtn) {
      mobileBtn.addEventListener("click", () => {
        sidebar.classList.toggle("open");
      });
    }
  
    // Close sidebar when clicking outside (mobile only)
    document.addEventListener("click", (e) => {
      if (window.innerWidth <= 768) {
        if (sidebar.classList.contains("open") && !sidebar.contains(e.target) && !mobileBtn.contains(e.target)) {
          sidebar.classList.remove("open");
        }
      }
    });
  
    // Footer year
    document.getElementById("year").textContent = new Date().getFullYear();
  
    // Dummy Account
    const accountName = document.getElementById("accountName");
    if (accountName) {
      accountName.textContent = "John Doe";
    }
  
    // Logout
    const logoutBtn = document.getElementById("logoutBtn");
    if (logoutBtn) {
      logoutBtn.addEventListener("click", () => {
        alert("Logging out...");
        window.location.href = "login.html";
      });
    }
  });
  