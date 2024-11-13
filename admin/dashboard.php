<?php
// admin/dashboard.php
require_once '../includes/init.php';
requireAdmin(); // Ensure user is admin

$pageTitle = 'Admin Dashboard';
$conn = Database::getInstance()->getConnection();

// Fetch total users
$stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$totalUsers = $stmt->fetchColumn();

// Fetch total events
$stmt = $conn->query("SELECT COUNT(*) FROM events");
$totalEvents = $stmt->fetchColumn();

// Fetch total bookings
$stmt = $conn->query("SELECT COUNT(*) FROM bookings");
$totalBookings = $stmt->fetchColumn();

// Fetch recent bookings
$stmt = $conn->query("
    SELECT b.*, u.first_name, u.last_name, e.title as event_title
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN events e ON b.event_id = e.id
    ORDER BY b.created_at DESC
    LIMIT 5
");
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch upcoming events
$stmt = $conn->query("
    SELECT *, 
           (SELECT COUNT(*) FROM bookings WHERE event_id = events.id) as total_bookings
    FROM events 
    WHERE start_date >= CURRENT_DATE
    ORDER BY start_date ASC 
    LIMIT 5
");
$upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
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
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="events/list.php">
                                <i class="fas fa-calendar me-2"></i>Events
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="users/manage.php">
                                <i class="fas fa-users me-2"></i>Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="bookings/list.php">
                                <i class="fas fa-ticket-alt me-2"></i>Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="settings.php">
                                <i class="fas fa-cog me-2"></i>Settings
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Users</h6>
                                    <h2 class="mb-0"><?php echo number_format($totalUsers); ?></h2>
                                </div>
                                <i class="fas fa-users fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Events</h6>
                                    <h2 class="mb-0"><?php echo number_format($totalEvents); ?></h2>
                                </div>
                                <i class="fas fa-calendar fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Total Bookings</h6>
                                    <h2 class="mb-0"><?php echo number_format($totalBookings); ?></h2>
                                </div>
                                <i class="fas fa-ticket-alt fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-title">Revenue</h6>
                                    <h2 class="mb-0">$<?php echo number_format(rand(5000, 15000)); ?></h2>
                                </div>
                                <i class="fas fa-dollar-sign fa-2x opacity-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Recent Bookings -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Bookings</h5>
                            <a href="bookings/list.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Event</th>
                                            <th>Status</th>
                                            <th>Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recentBookings as $booking): ?>
                                            <tr>
                                            <td><?php echo htmlspecialchars($booking['event_title']); ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $booking['status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td>$<?php echo number_format($booking['total_amount'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-md-6 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Upcoming Events</h5>
                            <a href="events/list.php" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Bookings</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($upcomingEvents as $event): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($event['title']); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($event['start_date'])); ?></td>
                                                <td><?php echo $event['total_bookings']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $event['status'] === 'published' ? 'success' : 'warning'; ?>">
                                                        <?php echo ucfirst($event['status']); ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-md-12">
                    <div class="card shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <a href="events/create.php" class="btn btn-outline-primary w-100">
                                        <i class="fas fa-plus-circle me-2"></i>Create Event
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="users/create.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-user-plus me-2"></i>Add User
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="reports/generate.php" class="btn btn-outline-info w-100">
                                        <i class="fas fa-file-export me-2"></i>Generate Report
                                    </a>
                                </div>
                                <div class="col-md-3">
                                    <a href="settings.php" class="btn btn-outline-secondary w-100">
                                        <i class="fas fa-cog me-2"></i>Settings
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add custom JavaScript for any dashboard interactions -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Add any dashboard-specific JavaScript here
    
    // Example: Auto-refresh stats every 5 minutes
    setInterval(function() {
        // You would typically make an AJAX call here to refresh the stats
        console.log('Stats would refresh here');
    }, 300000);
});
</script>

<?php include '../includes/footer.php'; ?>