function showModal(modalId) {
    document.getElementById(modalId).classList.add('show');
}

function hideModal(modalId) {
    document.getElementById(modalId).classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('show');
    }
}

function sortTable(column) {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSort = urlParams.get('sort');
    const currentOrder = urlParams.get('order') || 'DESC';

    // If clicking the same column, toggle order
    if (currentSort === column) {
        urlParams.set('order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
    } else {
        // New column, default to DESC
        urlParams.set('sort', column);
        urlParams.set('order', 'DESC');
    }

    window.location.search = urlParams.toString();
}