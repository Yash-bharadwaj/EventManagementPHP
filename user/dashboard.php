<?php
// user/dashboard.php
require_once '../includes/init.php';
requireLogin(); // Ensure user is logged in

$pageTitle = 'My Dashboard';
$userId = $_SESSION['user_id'];
$conn = Database::getInstance()->getConnection();

// Fetch user's upcoming events
$stmt = $conn->prepare("
    SELECT e.*, b.status as booking_status, b.quantity, b.total_amount 
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE b.user_id = ? AND e.start_date >= CURRENT_DATE
    ORDER BY e.start_date ASC
    LIMIT 5
");
$stmt->execute([$userId]);
$upcomingEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's past events
$stmt = $conn->prepare("
    SELECT e.*, b.status as booking_status, b.quantity, b.total_amount 
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    WHERE b.user_id = ? AND e.start_date < CURRENT_DATE
    ORDER BY e.start_date DESC
    LIMIT 5
");
$stmt->execute([$userId]);
$pastEvents = $stmt->fetchAll(PDO::FETCH_ASSOC);

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-circle fa-3x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
                            <small class="text-muted">Member since <?php echo date('M Y'); ?></small>
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
                            <a class="nav-link" href="bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>Edit Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="notifications.php">
                                <i class="fas fa-bell me-2"></i>Notifications
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Upcoming Events</h6>
                            <h2 class="mb-0"><?php echo count($upcomingEvents); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Active Bookings</h6>
                            <h2 class="mb-0"><?php echo count($upcomingEvents); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Past Events</h6>
                            <h2 class="mb-0"><?php echo count($pastEvents); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="card shadow-sm mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Upcoming Events</h5>
                    <a href="bookings.php" class="btn btn-sm btn-outline-primary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (empty($upcomingEvents)): ?>
                        <p class="text-muted text-center mb-0">No upcoming events found</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Event</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($upcomingEvents as $event): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($event['title']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($event['start_date'])); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $event['booking_status'] === 'confirmed' ? 'success' : 'warning'; ?>">
                                                    <?php echo ucfirst($event['booking_status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="../events/view.php?id=<?php echo $event['id']; ?>" class="btn btn-sm btn-outline-primary">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($pastEvents as $event): ?>
                            <div class="timeline-item">
                                <div class="timeline-date">
                                    <?php echo date('M d', strtotime($event['start_date'])); ?>
                                </div>
                                <div class="timeline-content">
                                    <h6><?php echo htmlspecialchars($event['title']); ?></h6>
                                    <p class="text-muted mb-0">
                                        Attended event - <?php echo date('g:i A', strtotime($event['start_date'])); ?>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>