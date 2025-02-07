    </div>
    <!-- Container End -->

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Optional: Custom Admin JS -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add any admin-specific JavaScript here
            console.log('Admin page loaded');

            // Optional: Confirm actions
            const confirmButtons = document.querySelectorAll('.btn-confirm');
            confirmButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!confirm('Are you sure you want to perform this action?')) {
                        e.preventDefault();
                    }
                });
            });

            // Optional: Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    if (!form.checkValidity()) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });

            // Prevent automatic dismissal of alerts
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                // Remove the data-bs-dismiss attribute to prevent automatic dismissal
                alert.removeAttribute('data-bs-dismiss');
                
                // Add a close button manually if it doesn't exist
                if (!alert.querySelector('.btn-close')) {
                    const closeButton = document.createElement('button');
                    closeButton.type = 'button';
                    closeButton.className = 'btn-close';
                    closeButton.setAttribute('aria-label', 'Close');
                    closeButton.addEventListener('click', function() {
                        alert.style.display = 'none';
                    });
                    alert.appendChild(closeButton);
                }
            });
        });
    </script>
</body>
</html>
