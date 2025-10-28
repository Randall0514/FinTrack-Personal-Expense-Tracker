// ==================== NOTIFICATION ====================

window.addEventListener('load', function() {
    setTimeout(function() {
        const allDropdowns = document.querySelectorAll('.dropdown-menu');
        const allToggles = document.querySelectorAll('.dropdown-toggle');

        allDropdowns.forEach(dropdown => {
            dropdown.classList.remove('show');
            dropdown.style.display = '';
        });

        allToggles.forEach(toggle => {
            toggle.setAttribute('aria-expanded', 'false');
        });
    }, 100);
});

// Prevent any dropdown from opening on page load
document.addEventListener('DOMContentLoaded', function() {
    const dropdowns = document.querySelectorAll('.dropdown-toggle');
    dropdowns.forEach(toggle => {
        toggle.setAttribute('aria-expanded', 'false');
    });

    const dropdownMenus = document.querySelectorAll('.dropdown-menu');
    dropdownMenus.forEach(menu => {
        menu.classList.remove('show');
    });

    // Reinitialize feather icons
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    const allDropdowns = document.querySelectorAll('.dropdown');

    allDropdowns.forEach(dropdownContainer => {
        if (!dropdownContainer.contains(event.target)) {
            const dropdown = dropdownContainer.querySelector('.dropdown-menu');
            const toggle = dropdownContainer.querySelector('.dropdown-toggle');

            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
                if (toggle) {
                    toggle.setAttribute('aria-expanded', 'false');
                }
            }
        }
    });
});

// Mark all notifications as read
function markAllAsRead(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    const btn = event ? event.target.closest('button') : null;
    if (!btn) {
        console.error('Button not found');
        return;
    }

    const originalHTML = btn.innerHTML;

    // Show loading state
    btn.disabled = true;
    btn.innerHTML = '<i data-feather="loader" class="w-4 h-4 icon-loader"></i> Processing...';

    // Reinitialize feather icons for the loader
    if (typeof feather !== 'undefined') {
        feather.replace();
    }

    // CORRECTED PATH: Now points to admin folder where the file actually is
    fetch('../admin/mark_notifications_read.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=mark_all_read'
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            if (data.success) {
                // Show success message
                btn.innerHTML = '<i data-feather="check" class="w-4 h-4"></i> Marked as Read!';
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }

                // Remove unread indicators
                const unreadDots = document.querySelectorAll('.unread-indicator');
                unreadDots.forEach(dot => {
                    dot.style.transition = 'opacity 0.3s ease';
                    dot.style.opacity = '0';
                    setTimeout(() => dot.remove(), 300);
                });

                // Remove unread class from notifications
                const notifications = document.querySelectorAll('.notification-item.unread');
                notifications.forEach(notification => {
                    notification.classList.remove('unread');
                    notification.style.transition = 'all 0.3s ease';
                });

                // Remove the badge
                const badge = document.querySelector('.notification-badge');
                if (badge) {
                    badge.style.transition = 'all 0.3s ease';
                    badge.style.transform = 'scale(0)';
                    badge.style.opacity = '0';
                    setTimeout(() => badge.remove(), 300);
                }

                // Remove the count badge
                const countBadge = document.querySelector('.notification-count-badge');
                if (countBadge) {
                    countBadge.style.transition = 'all 0.3s ease';
                    countBadge.style.transform = 'scale(0)';
                    countBadge.style.opacity = '0';
                    setTimeout(() => countBadge.remove(), 300);
                }

                // Reload page after a short delay
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                alert('❌ Error: ' + (data.message || 'Unknown error occurred'));
                btn.disabled = false;
                btn.innerHTML = originalHTML;
                if (typeof feather !== 'undefined') {
                    feather.replace();
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('❌ An error occurred while marking notifications as read: ' + error.message);
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            if (typeof feather !== 'undefined') {
                feather.replace();
            }
        });
}

// ==================== ABOUT MODAL (HEADER VERSION) ====================

// Modal Functions for Header About
function openHeaderAboutModal() {
    document.getElementById('headerAboutModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    // Re-initialize feather icons for modal content
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeHeaderAboutModal() {
    document.getElementById('headerAboutModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('headerAboutModal');
    if (event.target == modal) {
        closeHeaderAboutModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeHeaderAboutModal();
    }
});

// Spinning loader animation
const style = document.createElement('style');
style.textContent = `
  .icon-loader {
    animation: spin 1s linear infinite;
  }
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
  @keyframes fadeOut {
    from {
        opacity: 1;
        transform: scale(1);
    }
    to {
        opacity: 0;
        transform: scale(0.8);
    }
  }
`;
document.head.appendChild(style);

// Auto-refresh notification count every 60 seconds (optional)
setInterval(function() {
    // Optional: Add AJAX call to check for new notifications
}, 60000);

// Reinitialize feather icons periodically
setInterval(function() {
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}, 500);