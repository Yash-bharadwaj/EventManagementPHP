<?php
// admin/admin_sidebar.php
?>
<div class="card shadow-sm">
    <div class="card-body">
        <div class="d-flex align-items-center mb-3">
            <div class="flex-shrink-0">
                <i class="fas fa-user-shield fa-2x text-primary"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h6 class="mb-0">Admin Panel</h6>
            </div>
        </div>
        <hr>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'dashboard.php') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/dashboard.php">
                    <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/events/') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/events/list.php">
                    <i class="fas fa-calendar me-2"></i>Events
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/users/') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/users/list.php">
                    <i class="fas fa-users me-2"></i>Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/bookings/') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/bookings/list.php">
                    <i class="fas fa-ticket-alt me-2"></i>Bookings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'reports.php') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/reports.php">
                    <i class="fas fa-chart-bar me-2"></i>Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'settings.php') !== false ? 'active' : ''; ?>" 
                   href="/event_management/admin/settings.php">
                    <i class="fas fa-cog me-2"></i>Settings
                </a>
            </li>
        </ul>
    </div>
</div>