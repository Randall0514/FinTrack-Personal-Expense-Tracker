// Search functionality
document.getElementById('searchInput').addEventListener('keyup', function() {
    const searchValue = this.value.toLowerCase();
    const table = document.getElementById('usersTable');
    const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');

    for (let i = 0; i < rows.length; i++) {
        const cells = rows[i].getElementsByTagName('td');
        const fullname = cells[0].textContent.toLowerCase();
        const username = cells[1].textContent.toLowerCase();
        const email = cells[2].textContent.toLowerCase();

        if (fullname.includes(searchValue) || username.includes(searchValue) || email.includes(searchValue)) {
            rows[i].style.display = '';
        } else {
            rows[i].style.display = 'none';
        }
    }
});

// Delete Modal
function openDeleteModal(userId, username) {
    document.getElementById('deleteModal').style.display = 'block';
    document.getElementById('deleteUserId').value = userId;
    document.getElementById('deleteUsername').textContent = username;
}

function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteModal');

    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Close modal on ESC key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeDeleteModal();
    }
});
layout_change('false');
layout_theme_sidebar_change('dark');
change_box_container('false');
layout_caption_change('true');
layout_rtl_change('false');
preset_change('preset-1');
main_layout_change('vertical');