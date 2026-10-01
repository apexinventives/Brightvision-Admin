<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="fixed inset-y-0 left-0 w-64 bg-gradient-to-b from-blue-800 to-blue-900 text-white shadow-xl">
    <div class="flex items-center justify-center h-20 border-b border-blue-700">
        <h1 class="text-2xl font-bold tracking-wider">BV SYSTEM</h1>
    </div>
    
    <nav class="mt-8">
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
    
    <div class="absolute bottom-0 w-full p-4 border-t border-blue-700">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-yellow-400 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-blue-900"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo $_SESSION['user_name'] ?? 'Admin'; ?></p>
                <p class="text-xs text-blue-300">Administrator</p>
            </div>
        </div>
    </div>
</div>

<!-- Main content wrapper start (to be closed in footer) -->
<div class="ml-64">
