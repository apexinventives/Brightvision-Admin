<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<?php if (canAccess('sidebar')): ?>
<div class="lg:hidden fixed inset-x-0 top-0 z-30 h-16 bg-gray-900 text-white shadow-lg flex items-center justify-between px-4">
    <button type="button" onclick="openSidebar()" class="w-11 h-11 rounded-lg hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-400" aria-label="Open navigation menu"><i class="fas fa-bars text-xl"></i></button>
    <span class="font-bold tracking-wider">BV SYSTEM</span>
    <?php if (canAccess('profile')): ?><a href="profile.php" class="w-10 h-10 rounded-full bg-gray-700 text-white flex items-center justify-center" aria-label="Open profile"><i class="fas fa-user"></i></a><?php endif; ?>
</div>

<div id="sidebarBackdrop" onclick="closeSidebar()" class="lg:hidden fixed inset-0 z-40 bg-black/50 opacity-0 pointer-events-none transition-opacity duration-300" aria-hidden="true"></div>

<aside id="adminSidebar" class="fixed inset-y-0 left-0 z-50 w-72 sm:w-64 bg-gray-900 text-white shadow-xl sidebar-transition -translate-x-full lg:translate-x-0 flex flex-col" aria-label="Admin navigation">
    <div class="flex items-center justify-between h-20 border-b border-gray-800 px-5 shrink-0">
        <h1 class="text-2xl font-bold tracking-wider">BV SYSTEM</h1>
        <button type="button" onclick="closeSidebar()" class="lg:hidden w-9 h-9 rounded-lg hover:bg-blue-700" aria-label="Close navigation menu"><i class="fas fa-times text-xl"></i></button>
    </div>
    
    <nav class="mt-4 pb-4 overflow-y-auto flex-1">
        <?php
        $navigation = [
            ['index.php', 'Dashboard', 'fa-dashboard', 'dashboard'],
            ['users.php', 'Students', 'fa-users', 'students'],
            ['reservations.php', 'Exam Reservations', 'fa-calendar-alt', 'reservations'],
            ['payments.php', 'Payments', 'fa-money-check-dollar', 'payments'],
            ['payment-reports.php', 'Payment Reports', 'fa-file-invoice-dollar', 'reports'],
            ['bv-growth-upload.php', 'BV Growth Meter', 'fa-chart-line', 'growth'],
            ['profile.php', 'My Profile', 'fa-user-circle', 'profile'],
            ['accounts.php', 'User Accounts', 'fa-user-shield', 'accounts'],
            ['settings.php', 'Settings', 'fa-cog', 'settings'],
            ['activity-log.php', 'Activity Log', 'fa-history', 'activity'],
            ['logout.php', 'Logout', 'fa-sign-out-alt', 'logout'],
        ];
        foreach ($navigation as list($url, $label, $icon, $section)):
            if ($section && !canAccess($section)) continue;
        ?>
            <a href="<?php echo $url; ?>" class="flex items-center px-6 py-3 <?php echo $current_page === $url ? 'bg-blue-700 border-l-4 border-yellow-400' : 'hover:bg-blue-700'; ?> transition-colors duration-200"><i class="fas <?php echo $icon; ?> w-6"></i><span class="mx-3"><?php echo htmlspecialchars($label); ?></span></a>
        <?php endforeach; ?>
    </nav>
    
    <div class="w-full p-4 border-t border-blue-700 shrink-0">
        <div class="flex items-center">
            <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-gray-200"></i>
            </div>
            <div class="ml-3">
                <p class="text-sm font-medium"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'User'); ?></p>
                <p class="text-xs text-gray-400"><?php echo isAdmin() ? 'Administrator' : 'Staff'; ?></p>
            </div>
        </div>
    </div>
</aside>

<?php endif; ?>
<!-- Main content wrapper start (to be closed in footer) -->
<div class="admin-content <?php echo canAccess('sidebar') ? 'lg:ml-64 pt-16 lg:pt-0' : ''; ?> min-h-screen">
