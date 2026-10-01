<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="lg:hidden fixed inset-x-0 top-0 z-30 h-16 bg-gray-900 text-white shadow-lg flex items-center justify-between px-4">
    <button type="button" onclick="openSidebar()" class="w-11 h-11 rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400" aria-label="Open navigation menu"><i class="fas fa-bars text-xl"></i></button>
    <span class="font-bold tracking-wider">BV SYSTEM</span>
    <a href="profile.php" class="w-10 h-10 rounded-full bg-gray-700 text-white flex items-center justify-center" aria-label="Open profile"><i class="fas fa-user"></i></a>
</div>

<div id="sidebarBackdrop" onclick="closeSidebar()" class="lg:hidden fixed inset-0 z-40 bg-black/50 opacity-0 pointer-events-none transition-opacity duration-300" aria-hidden="true"></div>

<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-72 sm:w-64 bg-gray-900 text-white shadow-xl sidebar-transition -translate-x-full lg:translate-x-0 flex flex-col" aria-label="Admin navigation">
    <div class="flex items-center justify-between h-20 border-b border-gray-800 px-5 shrink-0">
        <h1 class="text-2xl font-bold tracking-wider">BV SYSTEM</h1>
        <button type="button" onclick="closeSidebar()" class="lg:hidden w-9 h-9 rounded-lg hover:bg-blue-700" aria-label="Close navigation menu"><i class="fas fa-times text-xl"></i></button>
    </div>
    
    <nav class="mt-4 pb-4 overflow-y-auto flex-1">
        <a href="index.php" class="flex items-center px-6 py-3 <?php echo $current_page == 'index.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
            <i class="fas fa-dashboard w-6"></i>
            <span class="mx-3">Dashboard</span>
        </a>
        
        <a href="users.php" class="flex items-center px-6 py-3 <?php echo $current_page == 'users.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
            <i class="fas fa-users w-6"></i>
            <span class="mx-3">Students</span>
        </a>
        
        <a href="reservations.php" class="flex items-center px-6 py-3 <?php echo $current_page == 'reservations.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
            <i class="fas fa-calendar-alt w-6"></i>
            <span class="mx-3">Exam Reservations</span>
        </a>

        <a href="payments.php" class="flex items-center px-6 py-3 <?php echo $current_page == 'payments.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
            <i class="fas fa-money-check-dollar w-6"></i>
            <span class="mx-3">Payments</span>
        </a>

        <a href="payment-reports.php" class="flex items-center px-6 py-3 <?php echo $current_page == 'payment-reports.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
            <i class="fas fa-file-invoice-dollar w-6"></i>
            <span class="mx-3">Payment Reports</span>
        </a>
        <!-- Add this after the Reservations link or wherever appropriate -->
		<a href="bv-growth-upload.php" class="flex items-center px-6 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'bv-growth-upload.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
			<i class="fas fa-chart-line w-6"></i>
			<span class="mx-3">BV Growth Meter</span>
		</a>
        <div class="border-t border-blue-700 my-4"></div>
        
		<!-- Add after existing links -->
		<div class="border-t border-blue-700 my-4"></div>

		<a href="profile.php" class="flex items-center px-6 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
			<i class="fas fa-user-circle w-6"></i>
			<span class="mx-3">My Profile</span>
		</a>

		<a href="settings.php" class="flex items-center px-6 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
			<i class="fas fa-cog w-6"></i>
			<span class="mx-3">Settings</span>
		</a>

		<a href="activity-log.php" class="flex items-center px-6 py-3 <?php echo basename($_SERVER['PHP_SELF']) == 'activity-log.php' ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200">
			<i class="fas fa-history w-6"></i>
			<span class="mx-3">Activity Log</span>
		</a>
				
        <a href="logout.php" class="flex items-center px-6 py-3 hover:bg-blue-700 transition-colors duration-200">
            <i class="fas fa-sign-out-alt w-6"></i>
            <span class="mx-3">Logout</span>
        </a>
    </nav>
    
    <div class="w-full p-4 border-t border-blue-700 shrink-0">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-gray-200"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></p>
                <p class="text-xs text-gray-400">Administrator</p>
            </div>
        </div>
    </div>
</aside>

<!-- Main content wrapper start (to be closed in footer) -->
<div class="admin-content lg:ml-64 pt-16 lg:pt-0 min-h-screen">
