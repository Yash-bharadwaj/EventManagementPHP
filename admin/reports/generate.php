<?php
// admin/reports/generate.php
require_once '../../includes/init.php';
requireAdmin();

$pageTitle = 'Generate Reports';
$conn = Database::getInstance()->getConnection();

// Get filter parameters
$report_type = isset($_GET['type']) ? sanitize($_GET['type']) : 'bookings';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01'); // First day of current month
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$format = isset($_GET['format']) ? sanitize($_GET['format']) : 'html';

// Generate report data based on type
$reportData = [];
$summary = [];

try {
    switch($report_type) {
        case 'bookings':
            // Bookings summary
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                    SUM(CASE WHEN status = 'confirmed' THEN total_amount ELSE 0 END) as total_revenue
                FROM bookings
                WHERE created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detailed bookings
            $stmt = $conn->prepare("
                SELECT 
                    b.id,
                    e.title as event_title,
                    CONCAT(u.first_name, ' ', u.last_name) as customer_name,
                    u.email as customer_email,
                    b.quantity,
                    b.total_amount,
                    b.status,
                    b.created_at
                FROM bookings b
                JOIN events e ON b.event_id = e.id
                JOIN users u ON b.user_id = u.id
                WHERE b.created_at BETWEEN ? AND ?
                ORDER BY b.created_at DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'events':
            // Events summary
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_events,
                    SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) as published_events,
                    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_events,
                    SUM(capacity) as total_capacity
                FROM events
                WHERE created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detailed events with booking stats
            $stmt = $conn->prepare("
                SELECT 
                    e.*,
                    c.name as category_name,
                    COUNT(b.id) as total_bookings,
                    SUM(b.total_amount) as total_revenue
                FROM events e
                LEFT JOIN categories c ON e.category_id = c.id
                LEFT JOIN bookings b ON e.id = b.event_id
                WHERE e.created_at BETWEEN ? AND ?
                GROUP BY e.id
                ORDER BY e.start_date DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'users':
            // Users summary
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_users,
                    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users,
                    SUM(CASE WHEN status = 'banned' THEN 1 ELSE 0 END) as banned_users
                FROM users
                WHERE created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Detailed user activity
            $stmt = $conn->prepare("
                SELECT 
                    u.*,
                    COUNT(b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'confirmed' THEN b.total_amount ELSE 0 END) as total_spent
                FROM users u
                LEFT JOIN bookings b ON u.id = b.user_id
                WHERE u.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_spent DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;

        case 'revenue':
            // Revenue summary
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(DISTINCT b.user_id) as unique_customers,
                    COUNT(b.id) as total_transactions,
                    SUM(b.total_amount) as total_revenue,
                    AVG(b.total_amount) as average_transaction
                FROM bookings b
                WHERE b.status = 'confirmed'
                AND b.created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $summary = $stmt->fetch(PDO::FETCH_ASSOC);

            // Daily revenue breakdown
            $stmt = $conn->prepare("
                SELECT 
                    DATE(b.created_at) as date,
                    COUNT(b.id) as transactions,
                    SUM(b.total_amount) as revenue,
                    COUNT(DISTINCT b.user_id) as unique_customers
                FROM bookings b
                WHERE b.status = 'confirmed'
                AND b.created_at BETWEEN ? AND ?
                GROUP BY DATE(b.created_at)
                ORDER BY date DESC
            ");
            $stmt->execute([$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            $reportData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }

    // Handle CSV export
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $report_type . '_report_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add UTF-8 BOM
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Add headers based on report type
        switch($report_type) {
            case 'bookings':
                fputcsv($output, ['ID', 'Event', 'Customer', 'Email', 'Quantity', 'Amount', 'Status', 'Date']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['event_title'],
                        $row['customer_name'],
                        $row['customer_email'],
                        $row['quantity'],
                        $row['total_amount'],
                        $row['status'],
                        $row['created_at']
                    ]);
                }
                break;

            case 'events':
                fputcsv($output, ['ID', 'Title', 'Category', 'Start Date', 'Capacity', 'Price', 'Bookings', 'Revenue', 'Status']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['title'],
                        $row['category_name'],
                        $row['start_date'],
                        $row['capacity'],
                        $row['price'],
                        $row['total_bookings'],
                        $row['total_revenue'],
                        $row['status']
                    ]);
                }
                break;

            case 'users':
                fputcsv($output, ['ID', 'Name', 'Email', 'Role', 'Status', 'Total Bookings', 'Total Spent', 'Joined Date']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['first_name'] . ' ' . $row['last_name'],
                        $row['email'],
                        $row['role'],
                        $row['status'],
                        $row['total_bookings'],
                        $row['total_spent'],
                        $row['created_at']
                    ]);
                }
                break;

            case 'revenue':
                fputcsv($output, ['Date', 'Transactions', 'Revenue', 'Unique Customers']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['date'],
                        $row['transactions'],
                        $row['revenue'],
                        $row['unique_customers']
                    ]);
                }
                break;
        }
        
        fclose($output);
        exit;
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    $error = 'Error generating report';
}

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
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0">Generate Reports</h1>
                        <?php if (!empty($reportData)): ?>
                            <a href="<?php echo $_SERVER['REQUEST_URI'] . '&format=csv'; ?>" class="btn btn-success">
                                <i class="fas fa-download me-1"></i> Export CSV
                            </a>
                        <?php endif; ?>
                    </div>

                    <!-- Report Filters -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-3">
                            <label class="form-label">Report Type</label>
                            <select name="type" class="form-select" required>
                                <option value="bookings" <?php echo $report_type === 'bookings' ? 'selected' : ''; ?>>Bookings Report</option>
                                <option value="events" <?php echo $report_type === 'events' ? 'selected' : ''; ?>>Events Report</option>
                                <option value="users" <?php echo $report_type === 'users' ? 'selected' : ''; ?>>Users Report</option>
                                <option value="revenue" <?php echo $report_type === 'revenue' ? 'selected' : ''; ?>>Revenue Report</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-sync-alt me-1"></i> Generate Report
                            </button>
                        </div>
                    </form>

                    <?php if (!empty($summary)): ?>
                        <!-- Summary Cards -->
                        <div class="row mb-4">
                            <?php foreach ($summary as $key => $value): ?>
                                <div class="col-md-3">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title text-muted">
                                                <?php echo ucwords(str_replace('_', ' ', $key)); ?>
                                            </h6>
                                            <h3 class="mb-0">
                                                <?php 
                                                if (strpos($key, 'revenue') !== false || strpos($key, 'amount') !== false || strpos($key, 'spent') !== false) {
                                                    echo '$' . number_format($value, 2);
                                                } else {
                                                    echo number_format($value);
                                                }
                                                ?>
                                            </h3>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($report_type === 'revenue'): ?>
                        <!-- Revenue Chart -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <canvas id="revenueChart" height="100"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Report Data Table -->
                    <?php if (!empty($reportData)): ?>
                        <div class="card">
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <?php switch($report_type):
                                            case 'bookings': ?>
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Event</th>
                                                        <th>Customer</th>
                                                        <th>Quantity</th>
                                                        <th>Amount</th>
                                                        <th>Status</th>
                                                        <th>Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td><?php echo $row['id']; ?></td>
                                                            <td><?php echo htmlspecialchars($row['event_title']); ?></td>
                                                            <td>
                                                                <?php echo htmlspecialchars($row['customer_name']); ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['customer_email']); ?></small>
                                                                </td>
                                                            <td><?php echo $row['quantity']; ?></td>
                                                            <td>$<?php echo number_format($row['total_amount'], 2); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $row['status'] === 'confirmed' ? 'success' : 
                                                                    ($row['status'] === 'pending' ? 'warning' : 'danger'); ?>">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo date('M d, Y H:i', strtotime($row['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            <?php break; ?>

                                            <?php case 'events': ?>
                                                <thead>
                                                    <tr>
                                                        <th>Event</th>
                                                        <th>Category</th>
                                                        <th>Date</th>
                                                        <th>Capacity</th>
                                                        <th>Price</th>
                                                        <th>Bookings</th>
                                                        <th>Revenue</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars($row['title']); ?></td>
                                                            <td><?php echo htmlspecialchars($row['category_name']); ?></td>
                                                            <td><?php echo date('M d, Y', strtotime($row['start_date'])); ?></td>
                                                            <td><?php echo number_format($row['capacity']); ?></td>
                                                            <td>$<?php echo number_format($row['price'], 2); ?></td>
                                                            <td><?php echo number_format($row['total_bookings']); ?></td>
                                                            <td>$<?php echo number_format($row['total_revenue'] ?? 0, 2); ?></td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $row['status'] === 'published' ? 'success' : 
                                                                    ($row['status'] === 'draft' ? 'warning' : 'danger'); ?>">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            <?php break; ?>

                                            <?php case 'users': ?>
                                                <thead>
                                                    <tr>
                                                        <th>User</th>
                                                        <th>Role</th>
                                                        <th>Status</th>
                                                        <th>Total Bookings</th>
                                                        <th>Total Spent</th>
                                                        <th>Joined Date</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td>
                                                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                                <br>
                                                                <small class="text-muted"><?php echo htmlspecialchars($row['email']); ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $row['role'] === 'admin' ? 'danger' : 'primary'; ?>">
                                                                    <?php echo ucfirst($row['role']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?php echo $row['status'] === 'active' ? 'success' : 
                                                                    ($row['status'] === 'inactive' ? 'warning' : 'danger'); ?>">
                                                                    <?php echo ucfirst($row['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td><?php echo number_format($row['total_bookings']); ?></td>
                                                            <td>$<?php echo number_format($row['total_spent'] ?? 0, 2); ?></td>
                                                            <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            <?php break; ?>

                                            <?php case 'revenue': ?>
                                                <thead>
                                                    <tr>
                                                        <th>Date</th>
                                                        <th>Transactions</th>
                                                        <th>Revenue</th>
                                                        <th>Unique Customers</th>
                                                        <th>Avg. Transaction</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($reportData as $row): ?>
                                                        <tr>
                                                            <td><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                                            <td><?php echo number_format($row['transactions']); ?></td>
                                                            <td>$<?php echo number_format($row['revenue'], 2); ?></td>
                                                            <td><?php echo number_format($row['unique_customers']); ?></td>
                                                            <td>$<?php echo number_format($row['revenue'] / $row['transactions'], 2); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            <?php break; ?>
                                        <?php endswitch; ?>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($report_type === 'revenue' && !empty($reportData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: <?php echo json_encode(array_map(function($row) {
            return date('M d', strtotime($row['date']));
        }, array_reverse($reportData))); ?>,
        datasets: [{
            label: 'Daily Revenue',
            data: <?php echo json_encode(array_map(function($row) {
                return $row['revenue'];
            }, array_reverse($reportData))); ?>,
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1,
            fill: false
        }]
    },
    options: {
        responsive: true,
        plugins: {
            title: {
                display: true,
                text: 'Daily Revenue Trend'
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});
</script>
<?php endif; ?>

<?php include '../../includes/footer.php'; ?>