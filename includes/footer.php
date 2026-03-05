</div> <!-- Close main content wrapper -->

<script>
    // Toggle sidebar on mobile
    function toggleSidebar() {
        document.querySelector('.fixed').classList.toggle('-translate-x-64');
    }
    
    // Confirm delete
    function confirmDelete(message) {
        return confirm(message || 'Are you sure you want to delete this item?');
    }
</script>

<script src="assets/js/main.js"></script>
</body>
</html>