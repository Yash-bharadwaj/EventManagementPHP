<?php
// user/bookings.php
require_once '../includes/init.php';
requireLogin();

$pageTitle = 'My Bookings';
$conn = Database::getInstance()->getConnection();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$period = isset($_GET['period']) ? sanitize($_GET['period']) : '';

// Build query conditions
$where = ['b.user_id = ?'];
$params = [$_SESSION['user_id']];

if ($status) {
    $where[] = "b.status = ?";
    $params[] = $status;
}

if ($period) {
    switch ($period) {
        case 'upcoming':
            $where[] = "e.start_date >= CURRENT_DATE";
            break;
        case 'past':
            $where[] = "e.start_date < CURRENT_DATE";
            break;
        case 'this-month':
            $where[] = "e.start_date >= DATE_FORMAT(CURRENT_DATE, '%Y-%m-01')";
            $where[] = "e.start_date < DATE_FORMAT(DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH), '%Y-%m-01')";
            break;
    }
}

// Fetch bookings
$query = "
    SELECT b.*, 
           e.title as event_title, 
           e.start_date,
           e.location,
           e.image_url,
           c.name as category_name
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY e.start_date DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0">
                            <i class="fas fa-user-circle fa-3x text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="mb-0"><?php echo htmlspecialchars($_SESSION['user_name']); ?></h6>
                            <small class="text-muted">My Account</small>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="bookings.php">
                                <i class="fas fa-ticket-alt me-2"></i>My Bookings
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user-edit me-2"></i>Edit Profile
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="col-md-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">My Bookings</h1>
                
                <!-- Filters -->
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <?php echo $period ? ucfirst($period) : 'All Time'; ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo !$period ? 'active' : ''; ?>" 
                              href="?<?php echo $status ? 'status=' . $status : ''; ?>">All Time</a></li>
                        <li><a class="dropdown-item <?php echo $period === 'upcoming' ? 'active' : ''; ?>" 
                              href="?period=upcoming<?php echo $status ? '&status=' . $status : ''; ?>">Upcoming</a></li>
                        <li><a class="dropdown-item <?php echo $period === 'past' ? 'active' : ''; ?>" 
                              href="?period=past<?php echo $status ? '&status=' . $status : ''; ?>">Past</a></li>
                        <li><a class="dropdown-item <?php echo $period === 'this-month' ? 'active' : ''; ?>" 
                              href="?period=this-month<?php echo $status ? '&status=' . $status : ''; ?>">This Month</a></li>
                    </ul>

                    <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                        <?php echo $status ? ucfirst($status) : 'All Status'; ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item <?php echo !$status ? 'active' : ''; ?>" 
                              href="?<?php echo $period ? 'period=' . $period : ''; ?>">All Status</a></li>
                        <li><a class="dropdown-item <?php echo $status === 'confirmed' ? 'active' : ''; ?>" 
                              href="?status=confirmed<?php echo $period ? '&period=' . $period : ''; ?>">Confirmed</a></li>
                        <li><a class="dropdown-item <?php echo $status === 'pending' ? 'active' : ''; ?>" 
                              href="?status=pending<?php echo $period ? '&period=' . $period : ''; ?>">Pending</a></li>
                        <li><a class="dropdown-item <?php echo $status === 'cancelled' ? 'active' : ''; ?>" 
                              href="?status=cancelled<?php echo $period ? '&period=' . $period : ''; ?>">Cancelled</a></li>
                    </ul>
                </div>
            </div>

            <?php if (empty($bookings)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-ticket-alt fa-3x text-muted mb-3"></i>
                        <h5>No bookings found</h5>
                        <p class="text-muted mb-3">You haven't made any bookings yet.</p>
                        <a href="/event_management/events" class="btn btn-primary">Browse Events</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 g-4">
                    <?php foreach ($bookings as $booking): ?>
                        <div class="col">
                            <div class="card shadow-sm">
                                <div class="card-body">
                                    <div class="row">
                                        <!-- Event Image -->
                                        <div class="col-md-3">
                                            <?php if ($booking['image_url']): ?>
                                                <img src="<?php echo htmlspecialchars($booking['image_url']); ?>" 
                                                     class="img-fluid rounded" 
                                                     alt="<?php echo htmlspecialchars($booking['event_title']); ?>">
                                            <?php else: ?>
                                                <div class="text-center p-3 bg-light rounded">
                                                    <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Event Details -->
                                        <div class="col-md-9">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <h5 class="card-title mb-0">
                                                    <?php echo htmlspecialchars($booking['event_title']); ?>
                                                </h5>
                                                <span class="badge bg-<?php 
                                                    echo $booking['status'] === 'confirmed' ? 'success' : 
                                                        ($booking['status'] === 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </div>
                                            
                                            <p class="card-text text-muted mb-2">
                                                <i class="fas fa-calendar me-2"></i>
                                                <?php echo date('F d, Y - g:i A', strtotime($booking['start_date'])); ?>
                                            </p>
                                            
                                            <p class="card-text text-muted mb-2">
                                                <i class="fas fa-map-marker-alt me-2"></i>
                                                <?php echo htmlspecialchars($booking['location']); ?>
                                            </p>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <p class="card-text mb-0">
                                                        <strong>Tickets:</strong> <?php echo $booking['quantity']; ?>
                                                    </p>
                                                    <p class="card-text">
                                                        <strong>Total:</strong> 
                                                        $<?php echo number_format($booking['total_amount'], 2); ?>
                                                    </p>
                                                </div>
                                                
                                                <div class="btn-group">
                                                    <?php if ($booking['status'] === 'confirmed'): ?>
                                                        <a href="ticket.php?booking_id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-primary">
                                                            <i class="fas fa-ticket-alt me-1"></i> View Ticket
                                                        </a>
                                                    <?php elseif ($booking['status'] === 'pending'): ?>
                                                        <a href="/event_management/payment/checkout.php?booking_id=<?php echo $booking['id']; ?>" 
                                                           class="btn btn-warning">
                                                            Complete Payment
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>