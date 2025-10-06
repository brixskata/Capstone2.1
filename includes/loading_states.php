<?php
// Loading states component for better UX
?>

<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay" style="display: none;">
    <div class="loading-spinner">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
        <div class="loading-text mt-2">Please wait...</div>
    </div>
</div>

<!-- Loading Button State -->
<div class="btn-loading" id="btnLoading" style="display: none;">
    <span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>
    <span class="btn-text">Loading...</span>
</div>

<style>
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}

.loading-spinner {
    background: white;
    padding: 2rem;
    border-radius: 0.5rem;
    text-align: center;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

.loading-text {
    color: #6c757d;
    font-weight: 500;
}

.btn-loading {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn-loading .spinner-border-sm {
    width: 1rem;
    height: 1rem;
}

/* Button loading state */
.btn.loading {
    pointer-events: none;
    opacity: 0.7;
}

.btn.loading .btn-text {
    display: none;
}

.btn.loading .btn-loading {
    display: inline-flex;
}

/* Cart loading states */
.cart-item.loading {
    opacity: 0.6;
    pointer-events: none;
}

.cart-item.loading::after {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 20px;
    height: 20px;
    margin: -10px 0 0 -10px;
    border: 2px solid #f3f3f3;
    border-top: 2px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Loading state management
function showLoading(overlayId = 'loadingOverlay') {
    const overlay = document.getElementById(overlayId);
    if (overlay) {
        overlay.style.display = 'flex';
    }
}

function hideLoading(overlayId = 'loadingOverlay') {
    const overlay = document.getElementById(overlayId);
    if (overlay) {
        overlay.style.display = 'none';
    }
}

function setButtonLoading(button, loading = true) {
    if (loading) {
        button.classList.add('loading');
        button.disabled = true;
    } else {
        button.classList.remove('loading');
        button.disabled = false;
    }
}

function setCartItemLoading(item, loading = true) {
    if (loading) {
        item.classList.add('loading');
    } else {
        item.classList.remove('loading');
    }
}

// Global error handling
function showError(message, type = 'error') {
    // Create toast notification
    const toast = document.createElement('div');
    toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} alert-dismissible fade show position-fixed`;
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Success notification
function showSuccess(message) {
    showError(message, 'success');
}
</script>

