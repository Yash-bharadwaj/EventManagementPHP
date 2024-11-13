<?php
// admin/bookings/list.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Manage Bookings';
$conn = Database::getInstance()->getConnection();

// Get filter parameters
$status = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$period = isset($_GET['period']) ? sanitize($_GET['period']) : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query conditions
$where = ["1=1"];
$params = [];

if ($status) {
    $where[] = "b.status = ?";
    $params[] = $status;
}

if ($period) {
    switch ($period) {
        case 'today':
            $where[] = "DATE(b.created_at) = CURRENT_DATE";
            break;
        case 'this-week':
            $where[] = "YEARWEEK(b.created_at) = YEARWEEK(CURRENT_DATE)";
            break;
        case 'this-month':
            $where[] = "MONTH(b.created_at) = MONTH(CURRENT_DATE) AND YEAR(b.created_at) = YEAR(CURRENT_DATE)";
            break;
    }
}

if ($search) {
    $where[] = "(e.title LIKE ? OR u.email LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

// Handle booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $booking_id = (int)$_POST['booking_id'];
    $new_status = sanitize($_POST['new_status']);
    
    try {
        $stmt = $conn->prepare("
            UPDATE bookings 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$new_status, $booking_id]);
        
        // Log the status change
        $stmt = $conn->prepare("
            INSERT INTO booking_logs (booking_id, action, admin_id, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$booking_id, "Status changed to $new_status", $_SESSION['user_id']]);
        
        redirect('/event_management/admin/bookings/list.php', 'Booking status updated successfully', 'success');
    } catch (PDOException $e) {
        $error = 'Error updating booking status';
        error_log($e->getMessage());
    }
}

// Fetch bookings with related information
$query = "
    SELECT b.*, 
           e.title as event_title,
           e.start_date,
           u.email as user_email,
           u.first_name,
           u.last_name,
           (SELECT COUNT(*) FROM bookings WHERE status = 'confirmed') as total_confirmed,
           (SELECT SUM(total_amount) FROM bookings WHERE status = 'confirmed') as total_revenue
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    JOIN users u ON b.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY b.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Calculate summary statistics
$total_bookings = count($bookings);
$total_revenue = array_sum(array_column(array_filter($bookings, function($b) {
    return $b['status'] === 'confirmed';
}), 'total_amount'));

include '../../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <?php include '../admin_sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Manage Bookings</h1>
                
                <!-- Export Button -->
                <a href="export.php?<?php echo http_build_query($_GET); ?>" 
                   class="btn btn-success">
                    <i class="fas fa-file-export me-1"></i> Export to CSV
                </a>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Bookings</h6>
                            <h2 class="mb-0"><?php echo $total_bookings; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Revenue</h6>
                            <h2 class="mb-0">$<?php echo number_format($total_revenue, 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Confirmed Bookings</h6>
                            <h2 class="mb-0"><?php echo $bookings[0]['total_confirmed'] ?? 0; ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Average Booking Value</h6>
                            <h2 class="mb-0">
                                $<?php 
                                    echo $total_bookings ? 
                                        number_format($total_revenue / $total_bookings, 2) : 
                                        '0.00'; 
                                ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search events or users..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="confirmed" <?php echo $status === 'confirmed' ? 'selected' : ''; ?>>
                                    Confirmed
                                </option>
                                <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>
                                    Pending
                                </option>
                                <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>
                                    Cancelled
                                </option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="period">
                                <option value="">All Time</option>
                                <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>
                                    Today
                                </option>
                                <option value="this-week" <?php echo $period === 'this-week' ? 'selected' : ''; ?>>
                                    This Week
                                </option>
                                <option value="this-month" <?php echo $period === 'this-month' ? 'selected' : ''; ?>>
                                    This Month
                                </option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Apply Filters
                            </button>
                            <a href="list.php" class="btn btn-secondary">
                                <i class="fas fa-undo me-1"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bookings Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Event</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Quantity</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($bookings as $booking): ?>
                                    <tr>
                                        <td>
                                            <?php echo str_pad($booking['id'], 8, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td>
                                            <a href="../events/view.php?id=<?php echo $booking['event_id']; ?>" 
                                               class="text-decoration-none">
                                                <?php echo htmlspecialchars($booking['event_title']); ?>
                                            </a>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo date('M d, Y', strtotime($booking['start_date'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($booking['first_name'] . ' ' . $booking['last_name']); ?>
                                            <br>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($booking['user_email']); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php echo date('M d, Y g:i A', strtotime($booking['created_at'])); ?>
                                        </td>
                                        <td><?php echo $booking['quantity']; ?></td>
                                        <td>$<?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $booking['status'] === 'confirmed' ? 'success' : 
                                                    ($booking['status'] === 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-primary dropdown-toggle"
                                                        data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="view.php?id=<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-eye me-1"></i> View Details
                                                        </a>
                                                    </li>
                                                    <?php if ($booking['status'] !== 'confirmed'): ?>
                                                        <li>
                                                            <form method="POST" class="dropdown-item">
                                                                <input type="hidden" name="booking_id" 
                                                                       value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="new_status" value="confirmed">
                                                                <button type="submit" name="update_status" 
                                                                        class="btn btn-link text-success p-0">
                                                                    <i class="fas fa-check me-1"></i> Confirm Booking
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <?php if ($booking['status'] !== 'cancelled'): ?>
                                                        <li>
                                                            <form method="POST" class="dropdown-item">
                                                                <input type="hidden" name="booking_id" 
                                                                       value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="new_status" value="cancelled">
                                                                <button type="submit" name="update_status" 
                                                                        class="btn btn-link text-danger p-0">
                                                                    <i class="fas fa-times me-1"></i> Cancel Booking
                                                                </button>
                                                            </form>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li>
                                                        <a class="dropdown-item" 
                                                           href="send-reminder.php?id=<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-envelope me-1"></i> Send Reminder
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
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
</div>

<?php include '../../includes/footer.php'; ?>