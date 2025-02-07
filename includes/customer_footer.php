    <!-- Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Font Awesome -->
    <script src="https://kit.fontawesome.com/your-font-awesome-kit.js"></script>
    
    <!-- Custom JS -->
    <script src="../assets/js/main.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
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
