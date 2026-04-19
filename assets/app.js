/**
 * Sentinel Net Admin Dashboard - JavaScript Application
 * Handles API calls, UI interactions, and common functionality
 */

// ======================== API Helper Functions ========================

/**
 * Make API call with error handling and SweetAlert notifications
 */
function apiCall(endpoint, data, successCallback, errorCallback = null) {
    Swal.fire({
        title: 'Processing...',
        allowOutsideClick: false,
        allowEscapeKey: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });

    fetch(endpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(result => {
        Swal.close();
        if (result.success || result.device_id || result.domain_id || result.alert_id) {
            if (successCallback) {
                successCallback(result);
            }
        } else {
            throw new Error(result.error || 'Unknown error');
        }
    })
    .catch(error => {
        Swal.close();
        const errorMsg = error.message || 'An error occurred';
        Swal.fire('Error!', errorMsg, 'error');
        if (errorCallback) {
            errorCallback(error);
        }
    });
}

/**
 * Format timestamp to readable format
 */
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleString();
}

/**
 * Copy text to clipboard
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        Swal.fire({
            toast: true,
            icon: 'success',
            title: 'Copied to clipboard',
            position: 'top-end',
            showConfirmButton: false,
            timer: 1500
        });
    }).catch(() => {
        alert('Failed to copy');
    });
}

// ======================== Form Utilities ========================

/**
 * Get form data as object
 */
function getFormData(formElement) {
    const formData = new FormData(formElement);
    const data = {};
    for (let [key, value] of formData.entries()) {
        data[key] = value;
    }
    return data;
}

/**
 * Clear form fields
 */
function clearForm(formElement) {
    formElement.reset();
}

// ======================== UI Utilities ========================

/**
 * Toggle loading state on element
 */
function setLoading(element, isLoading) {
    if (isLoading) {
        element.disabled = true;
        element.classList.add('loading');
    } else {
        element.disabled = false;
        element.classList.remove('loading');
    }
}

/**
 * Show toast notification
 */
function showToast(title, message, type = 'info') {
    Swal.fire({
        toast: true,
        icon: type,
        title: title,
        text: message,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000
    });
}

/**
 * Confirm action with SweetAlert
 */
function confirm(title, text, icon = 'warning') {
    return new Promise((resolve) => {
        Swal.fire({
            title: title,
            text: text,
            icon: icon,
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes'
        }).then((result) => {
            resolve(result.isConfirmed);
        });
    });
}

// ======================== Data Formatting ========================

/**
 * Format bytes to human readable
 */
function formatBytes(bytes, decimals = 2) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const dm = decimals < 0 ? 0 : decimals;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

/**
 * Format percentage
 */
function formatPercent(value, total) {
    if (total === 0) return '0%';
    return Math.round((value / total) * 100) + '%';
}

/**
 * Truncate text
 */
function truncate(text, length = 50) {
    if (text.length > length) {
        return text.substring(0, length) + '...';
    }
    return text;
}

// ======================== Validation ========================

/**
 * Validate email
 */
function isValidEmail(email) {
    const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return regex.test(email);
}

/**
 * Validate IP address
 */
function isValidIP(ip) {
    const regex = /^(\d{1,3}\.){3}\d{1,3}$/;
    return regex.test(ip);
}

/**
 * Validate domain
 */
function isValidDomain(domain) {
    const regex = /^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/;
    return regex.test(domain);
}

// ======================== Initialization ========================

/**
 * Initialize Dashboard
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('Sentinel Net Dashboard Initialized');
    
    // Auto-refresh dashboard data
    setInterval(refreshDashboard, 30000);
});

/**
 * Refresh dashboard statistics
 */
function refreshDashboard() {
    // This could make an AJAX call to get updated stats
    // For now, just log that it's refreshing
    console.log('Dashboard refresh check...');
}

// ======================== Export Functions ========================

window.apiCall = apiCall;
window.formatTimestamp = formatTimestamp;
window.copyToClipboard = copyToClipboard;
window.getFormData = getFormData;
window.clearForm = clearForm;
window.setLoading = setLoading;
window.showToast = showToast;
window.confirm = confirm;
window.formatBytes = formatBytes;
window.formatPercent = formatPercent;
window.truncate = truncate;
window.isValidEmail = isValidEmail;
window.isValidIP = isValidIP;
window.isValidDomain = isValidDomain;
