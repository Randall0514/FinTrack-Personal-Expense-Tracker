function openConfirmModal() {
    const fromSelect = document.getElementById('from_user_id');
    const toSelect = document.getElementById('to_user_id');

    if (fromSelect.value === '' || toSelect.value === '') {
        showAlert('Please select both users for the transfer.', 'warning');
        return;
    }

    if (fromSelect.value === toSelect.value) {
        showAlert('You cannot transfer data to the same user.', 'warning');
        return;
    }

    const fromUserName = fromSelect.options[fromSelect.selectedIndex].text;
    const toUserName = toSelect.options[toSelect.selectedIndex].text;

    document.getElementById('fromUserName').textContent = fromUserName;
    document.getElementById('toUserName').textContent = toUserName;

    document.getElementById('confirmModal').style.display = 'block';
}

function closeConfirmModal() {
    document.getElementById('confirmModal').style.display = 'none';
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <div>${message}</div>
            `;

    const mainContent = document.querySelector('.main-content');
    const firstChild = mainContent.firstChild;
    mainContent.insertBefore(alertDiv, firstChild);

    setTimeout(() => {
        alertDiv.style.opacity = '0';
        alertDiv.style.transform = 'translateY(-10px)';
        setTimeout(() => alertDiv.remove(), 300);
    }, 3000);
}

window.onclick = function(event) {
    const modal = document.getElementById('confirmModal');
    if (event.target === modal) {
        closeConfirmModal();
    }
}

setTimeout(() => {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        alert.style.transition = 'all 0.3s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(-10px)';
        setTimeout(() => alert.remove(), 300);
    });
}, 5000);