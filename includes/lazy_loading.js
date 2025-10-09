// Lazy loading implementation for images
class LazyLoader {
    constructor(options = {}) {
        this.options = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1,
            placeholder: 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjhmOWZhIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGZvbnQtZmFtaWx5PSJBcmlhbCwgc2Fucy1zZXJpZiIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OTk5OSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkxvYWRpbmcuLi48L3RleHQ+PC9zdmc+',
            ...options
        };
        
        this.observer = null;
        this.init();
    }

    init() {
        // Check if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver(
                this.handleIntersection.bind(this),
                this.options
            );
            this.observeImages();
        } else {
            // Fallback: load all images immediately
            this.loadAllImages();
        }
    }

    observeImages() {
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            this.observer.observe(img);
        });
    }

    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                this.loadImage(entry.target);
                this.observer.unobserve(entry.target);
            }
        });
    }

    loadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) return;

        // Create a new image to preload
        const imageLoader = new Image();
        
        imageLoader.onload = () => {
            img.src = src;
            img.classList.add('loaded');
            img.classList.remove('lazy');
        };
        
        imageLoader.onerror = () => {
            img.src = this.options.placeholder;
            img.classList.add('error');
            img.classList.remove('lazy');
        };
        
        imageLoader.src = src;
    }

    loadAllImages() {
        const images = document.querySelectorAll('img[data-src]');
        images.forEach(img => {
            this.loadImage(img);
        });
    }

    // Add new images to observe
    observeNewImages() {
        const newImages = document.querySelectorAll('img[data-src]:not(.lazy-loaded)');
        newImages.forEach(img => {
            img.classList.add('lazy-loaded');
            this.observer.observe(img);
        });
    }

    // Destroy observer
    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
    }
}

// CSS for lazy loading
const lazyLoadingCSS = `
.lazy {
    opacity: 0;
    transition: opacity 0.3s;
}

.lazy.loaded {
    opacity: 1;
}

.lazy.error {
    opacity: 1;
    background-color: #f8f9fa;
    border: 1px solid #dee2e6;
}

.lazy-placeholder {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
`;

// Add CSS to head
const style = document.createElement('style');
style.textContent = lazyLoadingCSS;
document.head.appendChild(style);

// Initialize lazy loader when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    window.lazyLoader = new LazyLoader();
    
    // Re-observe images when new content is loaded (e.g., AJAX)
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                window.lazyLoader.observeNewImages();
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });
});

// Utility function to convert regular images to lazy loading
function convertToLazyLoading(container = document) {
    const images = container.querySelectorAll('img[src]:not([data-src])');
    images.forEach(img => {
        const src = img.src;
        img.src = '';
        img.setAttribute('data-src', src);
        img.classList.add('lazy');
        img.classList.add('lazy-placeholder');
    });
    
    // Re-observe if lazy loader exists
    if (window.lazyLoader) {
        window.lazyLoader.observeNewImages();
    }
}

// Export for use in other scripts
window.LazyLoader = LazyLoader;
window.convertToLazyLoading = convertToLazyLoading;

