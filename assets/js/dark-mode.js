/**
 * Dark Mode Toggle JavaScript
 * Handles localStorage-based theme switching with smooth transitions
 */

// Theme management object
const DarkMode = {
    // Configuration
    config: {
        storageKey: 'theme-preference',
        defaultTheme: 'light',
        themes: ['light', 'dark']
    },

    // Initialize dark mode functionality
    init() {
        this.loadTheme();
        this.bindEvents();
        this.updateToggleIcon();
    },

    // Load saved theme from localStorage and apply it
    loadTheme() {
        const savedTheme = localStorage.getItem(this.config.storageKey) || this.config.defaultTheme;
        this.setTheme(savedTheme);
    },

    // Set the theme and save to localStorage
    setTheme(theme) {
        if (!this.config.themes.includes(theme)) {
            theme = this.config.defaultTheme;
        }

        // Apply theme to document
        document.documentElement.setAttribute('data-theme', theme);
        
        // Save to localStorage
        localStorage.setItem(this.config.storageKey, theme);
        
        // Update toggle icon
        this.updateToggleIcon();
        
        // Dispatch custom event for other scripts
        window.dispatchEvent(new CustomEvent('themeChanged', { 
            detail: { theme: theme } 
        }));
    },

    // Toggle between light and dark themes
    toggle() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        this.setTheme(newTheme);
        
        // Show feedback to user
        this.showThemeFeedback(newTheme);
    },

    // Update the toggle button icon based on current theme
    updateToggleIcon() {
        const currentTheme = document.documentElement.getAttribute('data-theme');
        const toggleButtons = document.querySelectorAll('.dark-mode-toggle i');
        
        toggleButtons.forEach(icon => {
            if (currentTheme === 'dark') {
                icon.className = 'fas fa-sun theme-icon';
                icon.setAttribute('title', 'Switch to Light Mode');
            } else {
                icon.className = 'fas fa-moon theme-icon';
                icon.setAttribute('title', 'Switch to Dark Mode');
            }
        });
    },

    // Show visual feedback when theme changes
    showThemeFeedback(theme) {
        // Create a temporary toast notification
        const toast = document.createElement('div');
        toast.className = 'theme-feedback-toast';
        toast.innerHTML = `
            <div class="toast-content">
                <i class="fas ${theme === 'dark' ? 'fa-moon' : 'fa-sun'} me-2"></i>
                Switched to ${theme === 'dark' ? 'Dark' : 'Light'} Mode
            </div>
        `;
        
        // Style the toast
        toast.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-card);
            color: var(--text-primary);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 20px var(--shadow-medium);
            border: 1px solid var(--border-light);
            z-index: 9999;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
            font-size: 0.9rem;
            font-weight: 500;
        `;
        
        document.body.appendChild(toast);
        
        // Animate in
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);
        
        // Remove after 2 seconds
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 300);
        }, 2000);
    },

    // Bind event listeners
    bindEvents() {
        // Bind click events to all dark mode toggle buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.dark-mode-toggle')) {
                e.preventDefault();
                this.toggle();
            }
        });

        // Listen for theme changes from other sources
        window.addEventListener('themeChanged', (e) => {
            this.updateToggleIcon();
        });

        // Handle system theme changes (if supported)
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', (e) => {
                // Only auto-switch if user hasn't manually set a preference
                const savedTheme = localStorage.getItem(this.config.storageKey);
                if (!savedTheme) {
                    this.setTheme(e.matches ? 'dark' : 'light');
                }
            });
        }
    },

    // Get current theme
    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || this.config.defaultTheme;
    },

    // Check if dark mode is active
    isDarkMode() {
        return this.getCurrentTheme() === 'dark';
    },

    // Reset to default theme
    reset() {
        localStorage.removeItem(this.config.storageKey);
        this.setTheme(this.config.defaultTheme);
    }
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        DarkMode.init();
    });
} else {
    DarkMode.init();
}

// Expose DarkMode object globally for other scripts
window.DarkMode = DarkMode;

// Quick access functions
window.toggleDarkMode = () => DarkMode.toggle();
window.setDarkMode = (enabled) => DarkMode.setTheme(enabled ? 'dark' : 'light');
window.isDarkMode = () => DarkMode.isDarkMode();
