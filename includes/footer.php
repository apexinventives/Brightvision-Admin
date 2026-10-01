</div> <!-- Close main content wrapper -->

<script>
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');

    function openSidebar() {
        if (!adminSidebar || !sidebarBackdrop) return;
        adminSidebar.classList.remove('-translate-x-full');
        sidebarBackdrop.classList.remove('opacity-0', 'pointer-events-none');
        document.body.classList.add('sidebar-open');
    }

    function closeSidebar() {
        if (!adminSidebar || !sidebarBackdrop) return;
        adminSidebar.classList.add('-translate-x-full');
        sidebarBackdrop.classList.add('opacity-0', 'pointer-events-none');
        document.body.classList.remove('sidebar-open');
    }

    function toggleSidebar() {
        if (adminSidebar && adminSidebar.classList.contains('-translate-x-full')) openSidebar();
        else closeSidebar();
    }

    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeSidebar(); });
    window.addEventListener('resize', function () {
        if (window.innerWidth >= 1024) {
            document.body.classList.remove('sidebar-open');
            if (adminSidebar) adminSidebar.classList.add('-translate-x-full');
            if (sidebarBackdrop) sidebarBackdrop.classList.add('opacity-0', 'pointer-events-none');
        }
    });
    
    // Confirm delete
    function confirmDelete(message) {
        return confirm(message || 'Are you sure you want to delete this item?');
    }
</script>

<script src="assets/js/main.js"></script>
</body>
</html>
