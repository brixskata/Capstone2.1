
<script>
  // Sidebar toggle functionality
  document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarToggleNav = document.getElementById('sidebarToggleNav');
    const toggleIcon = document.getElementById('toggleIcon');
    
    // Load saved sidebar state
    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (sidebarCollapsed) {
      sidebar.classList.add('collapsed');
      if (mainContent) mainContent.classList.add('sidebar-collapsed');
      toggleIcon.classList.remove('fa-chevron-left');
      toggleIcon.classList.add('fa-chevron-right');
    }
    
    // Toggle function
    function toggleSidebar() {
      sidebar.classList.toggle('collapsed');
      if (mainContent) mainContent.classList.toggle('sidebar-collapsed');
      
      if (sidebar.classList.contains('collapsed')) {
        toggleIcon.classList.remove('fa-chevron-left');
        toggleIcon.classList.add('fa-chevron-right');
        localStorage.setItem('sidebarCollapsed', 'true');
      } else {
        toggleIcon.classList.remove('fa-chevron-right');
        toggleIcon.classList.add('fa-chevron-left');
        localStorage.setItem('sidebarCollapsed', 'false');
      }
    }
    
    // Event listeners for both toggle buttons
    if (sidebarToggle) {
      sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (sidebarToggleNav) {
      sidebarToggleNav.addEventListener('click', toggleSidebar);
    }
    
    // Dark mode toggle functionality
    const darkModeToggle = document.getElementById('darkModeToggle');
    
    // Load saved theme preference
    const savedTheme = localStorage.getItem('adminTheme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    
    // Toggle function
    function toggleDarkMode() {
      const currentTheme = document.documentElement.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      document.documentElement.setAttribute('data-theme', newTheme);
      localStorage.setItem('adminTheme', newTheme);
    }
    
    // Event listener for dark mode toggle
    if (darkModeToggle) {
      darkModeToggle.addEventListener('click', toggleDarkMode);
    }
    
    // Sidebar dropdown functionality - let Bootstrap handle the collapse
    const dropdownToggles = document.querySelectorAll('[data-bs-toggle="collapse"]');
    
    dropdownToggles.forEach(toggle => {
      toggle.addEventListener('click', function(e) {
        const arrow = this.querySelector('.dropdown-arrow');
        
        // Let Bootstrap handle the collapse, just manage the arrow rotation
        setTimeout(() => {
          const targetId = this.getAttribute('data-bs-target');
          const target = document.querySelector(targetId);
          
          if (target && arrow) {
            if (target.classList.contains('show')) {
              arrow.style.transform = 'rotate(0deg)';
            } else {
              arrow.style.transform = 'rotate(-90deg)';
            }
          }
        }, 10);
      });
    });
    
    // Auto-expand dropdown if current page is in that section
    const currentPage = window.location.pathname.split('/').pop();
    const currentPageLink = document.querySelector(`a[href*="${currentPage}"]`);
    
    if (currentPageLink) {
      const dropdown = currentPageLink.closest('.nav-dropdown');
      if (dropdown) {
        dropdown.classList.add('show');
        const toggle = document.querySelector(`[data-bs-target="#${dropdown.id}"]`);
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'true');
          const arrow = toggle.querySelector('.dropdown-arrow');
          if (arrow) arrow.style.transform = 'rotate(0deg)';
        }
      }
    }
  });
</script>
