// Confirm delete function
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// Toggle sidebar on mobile
function toggleSidebar() {
    document.querySelector('.fixed').classList.toggle('-translate-x-64');
}

// Format date for display
function formatDate(dateString) {
    const options = { year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Format time for display
function formatTime(timeString) {
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const hour12 = hour % 12 || 12;
    return `${hour12}:${minutes} ${ampm}`;
}

// Show notification
function showNotification(message, type = 'success') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 px-6 py-3 rounded-lg shadow-lg ${
        type === 'success' ? 'bg-green-500' : 'bg-red-500'
    } text-white z-50 animate-fade-in`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}

// Export table to CSV
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    const csv = [];
    
    for (const row of rows) {
        const cells = row.querySelectorAll('td, th');
        const rowData = [];
        for (const cell of cells) {
            rowData.push('"' + cell.innerText.replace(/"/g, '""') + '"');
        }
        csv.push(rowData.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
}