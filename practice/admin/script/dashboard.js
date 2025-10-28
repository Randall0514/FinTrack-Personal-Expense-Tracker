function openDeleteModal(userId, userName) {
    document.getElementById('deleteModal').style.display = 'block';
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUserName').textContent = userName;
}

function openApprovalModal(userId, userName, isApproved) {
    document.getElementById('approvalModal').style.display = 'block';
    document.getElementById('approvalUserId').value = userId;
    document.getElementById('approvalCurrentStatus').value = isApproved;
    document.getElementById('approvalUserName').textContent = userName;

    if (isApproved === 1) {
        document.getElementById('approvalModalText').textContent = 'Are you sure you want to revoke approval?';
        document.getElementById('approvalBtnText').textContent = 'Revoke';
    } else {
        document.getElementById('approvalModalText').textContent = 'Are you sure you want to approve this user?';
        document.getElementById('approvalBtnText').textContent = 'Approve';
    }
}

function openAdminModal(userId, userName, isAdmin) {
    document.getElementById('adminModal').style.display = 'block';
    document.getElementById('adminUserId').value = userId;
    document.getElementById('adminCurrentStatus').value = isAdmin;
    document.getElementById('adminUserName').textContent = userName;

    if (isAdmin === 1) {
        document.getElementById('adminModalText').textContent = 'Are you sure you want to revoke admin privileges?';
        document.getElementById('adminBtnText').textContent = 'Revoke Admin';
    } else {
        document.getElementById('adminModalText').textContent = 'Are you sure you want to grant admin privileges?';
        document.getElementById('adminBtnText').textContent = 'Grant Admin';
    }
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function confirmLogout(event) {
    event.preventDefault();
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../logout.php';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeModal('deleteModal');
        closeModal('approvalModal');
        closeModal('adminModal');
    }
});
layout_change('false');
layout_theme_sidebar_change('dark');
change_box_container('false');
layout_caption_change('true');
layout_rtl_change('false');
preset_change('preset-1');
main_layout_change('vertical');