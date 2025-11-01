// Client-side validation utilities
class FormValidator {
    constructor() {
        this.rules = {};
        this.errors = {};
    }

    // Add validation rule
    addRule(fieldName, rule, message) {
        if (!this.rules[fieldName]) {
            this.rules[fieldName] = [];
        }
        this.rules[fieldName].push({ rule, message });
    }

    // Validate single field
    validateField(fieldName, value) {
        const fieldRules = this.rules[fieldName] || [];
        const errors = [];

        for (const { rule, message } of fieldRules) {
            if (!rule(value)) {
                errors.push(message);
            }
        }

        this.errors[fieldName] = errors;
        return errors.length === 0;
    }

    // Validate entire form
    validateForm(formData) {
        let isValid = true;
        this.errors = {};

        for (const fieldName in this.rules) {
            const value = formData.get(fieldName) || '';
            if (!this.validateField(fieldName, value)) {
                isValid = false;
            }
        }

        return isValid;
    }

    // Get errors for field
    getFieldErrors(fieldName) {
        return this.errors[fieldName] || [];
    }

    // Clear field errors
    clearFieldErrors(fieldName) {
        delete this.errors[fieldName];
    }

    // Show field errors
    showFieldErrors(fieldName, container) {
        const errors = this.getFieldErrors(fieldName);
        if (errors.length > 0) {
            container.innerHTML = errors.map(error => 
                `<div class="invalid-feedback d-block">${error}</div>`
            ).join('');
        } else {
            container.innerHTML = '';
        }
    }
}

// Common validation rules
const ValidationRules = {
    required: (value) => value.trim() !== '',
    email: (value) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
    minLength: (min) => (value) => value.length >= min,
    maxLength: (max) => (value) => value.length <= max,
    phone: (value) => /^[\+]?[0-9\s\-\(\)]{10,}$/.test(value),
    positiveNumber: (value) => parseFloat(value) > 0,
    integer: (value) => Number.isInteger(parseFloat(value)) && parseFloat(value) >= 0,
    password: (value) => value.length >= 6,
    confirmPassword: (password) => (value) => value === password,
    fileSize: (maxSize) => (file) => file ? file.size <= maxSize : true,
    fileType: (allowedTypes) => (file) => file ? allowedTypes.includes(file.type) : true
};

// Initialize form validation
function initFormValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) return null;

    const validator = new FormValidator();
    
    // Add real-time validation
    form.addEventListener('input', (e) => {
        if (e.target.matches('input, select, textarea')) {
            const fieldName = e.target.name;
            const value = e.target.value;
            
            if (validator.rules[fieldName]) {
                validator.validateField(fieldName, value);
                const errorContainer = form.querySelector(`[data-error="${fieldName}"]`);
                if (errorContainer) {
                    validator.showFieldErrors(fieldName, errorContainer);
                }
            }
        }
    });

    // Form submission validation
    form.addEventListener('submit', (e) => {
        const formData = new FormData(form);
        if (!validator.validateForm(formData)) {
            e.preventDefault();
            
            // Show all errors
            for (const fieldName in validator.errors) {
                const errorContainer = form.querySelector(`[data-error="${fieldName}"]`);
                if (errorContainer) {
                    validator.showFieldErrors(fieldName, errorContainer);
                }
            }
            
            // Focus first error field
            const firstErrorField = form.querySelector('.is-invalid');
            if (firstErrorField) {
                firstErrorField.focus();
            }
        }
    });

    return validator;
}

// Profile form validation
function initProfileValidation() {
    const validator = initFormValidation('profileForm');
    if (!validator) return;

    // Add validation rules
    validator.addRule('first_name', ValidationRules.required, 'First name is required');
    validator.addRule('first_name', ValidationRules.minLength(2), 'First name must be at least 2 characters');
    validator.addRule('last_name', ValidationRules.required, 'Last name is required');
    validator.addRule('last_name', ValidationRules.minLength(2), 'Last name must be at least 2 characters');
    validator.addRule('email', ValidationRules.required, 'Email is required');
    validator.addRule('email', ValidationRules.email, 'Please enter a valid email address');
    // Phone is required and must be exactly 11 digits
    validator.addRule('contact_phone', ValidationRules.required, 'Phone number is required');
    validator.addRule('contact_phone', (value) => /^\d{11}$/.test(value), 'Phone number must be exactly 11 digits');

    // GCash number is required and must be exactly 11 digits
    validator.addRule('gcash_number', ValidationRules.required, 'GCash number is required');
    validator.addRule('gcash_number', (value) => /^\d{11}$/.test(value), 'GCash number must be exactly 11 digits');
}

// Address form validation
function initAddressValidation() {
    const validator = initFormValidation('addressForm');
    if (!validator) return;

    validator.addRule('address_line', ValidationRules.required, 'Address is required');
    validator.addRule('city', ValidationRules.required, 'City is required');
    validator.addRule('postal_code', ValidationRules.required, 'Postal code is required');
    validator.addRule('postal_code', ValidationRules.integer, 'Postal code must be a valid number');
}

// Product form validation
function initProductValidation() {
    const validator = initFormValidation('productForm');
    if (!validator) return;

    validator.addRule('quantity', ValidationRules.required, 'Quantity is required');
    validator.addRule('quantity', ValidationRules.positiveNumber, 'Quantity must be greater than 0');
    validator.addRule('quantity', ValidationRules.integer, 'Quantity must be a whole number');
}

// File upload validation
function validateFileUpload(file, options = {}) {
    const {
        maxSize = 5 * 1024 * 1024, // 5MB
        allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp']
    } = options;

    const errors = [];

    if (!file) {
        errors.push('Please select a file');
        return { isValid: false, errors };
    }

    // Check file size
    if (file.size > maxSize) {
        errors.push(`File size must be less than ${Math.round(maxSize / 1024 / 1024)}MB`);
    }

    // Check file type
    if (!allowedTypes.includes(file.type)) {
        errors.push('Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed');
    }

    // Check file extension
    const extension = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(extension)) {
        errors.push('Invalid file extension');
    }

    return {
        isValid: errors.length === 0,
        errors
    };
}

// Initialize all validations when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Initialize form validations
    initProfileValidation();
    initAddressValidation();
    initProductValidation();

    // Add file upload validation
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const validation = validateFileUpload(file);
            
            if (!validation.isValid) {
                showError(validation.errors.join(', '));
                e.target.value = ''; // Clear the input
            }
        });
    });
});

