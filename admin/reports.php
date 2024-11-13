<?php
// admin/reports.php
require_once '../includes/init.php';
requireAdmin();

$pageTitle = 'Reports';
$conn = Database::getInstance()->getConnection();

// Get date range parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch summary statistics with COALESCE to handle NULL values
$stmt = $conn->prepare("
    SELECT 
        COALESCE(COUNT(*), 0) as total_bookings,
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COALESCE(SUM(quantity), 0) as total_tickets,
        COALESCE(COUNT(DISTINCT user_id), 0) as unique_customers
    FROM bookings
    WHERE status = 'confirmed'
    AND created_at BETWEEN ? AND ?
");
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Initialize summary with default values if null
$summary = array_merge([
    'total_bookings' => 0,
    'total_revenue' => 0,
    'total_tickets' => 0,
    'unique_customers' => 0
], $summary ?: []);

// Revenue by category with COALESCE
$stmt = $conn->prepare("
    SELECT 
        c.name as category_name,
        COALESCE(COUNT(b.id), 0) as booking_count,
        COALESCE(SUM(b.total_amount), 0) as revenue
    FROM categories c
    LEFT JOIN events e ON c.id = e.category_id
    LEFT JOIN bookings b ON e.id = b.event_id AND b.status = 'confirmed'
        AND b.created_at BETWEEN ? AND ?
    GROUP BY c.id, c.name
    ORDER BY revenue DESC
");
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$category_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily revenue chart data with COALESCE
$stmt = $conn->prepare("
    SELECT 
        DATE(created_at) as date,
        COALESCE(COUNT(*), 0) as bookings,
        COALESCE(SUM(total_amount), 0) as revenue
    FROM bookings
    WHERE status = 'confirmed'
    AND created_at BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date
");
$stmt->execute([$start_date, $end_date . ' 23:59:59']);
$daily_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Verify data types
$summary['total_revenue'] = (float)$summary['total_revenue'];
$summary['total_bookings'] = (int)$summary['total_bookings'];
$summary['total_tickets'] = (int)$summary['total_tickets'];
$summary['unique_customers'] = (int)$summary['unique_customers'];

include '../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2">
            <?php include 'admin_sidebar.php'; ?>
        </div>

        <!-- Main Content -->
        <div class="col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Reports</h1>
                <div class="btn-group">
                    <a href="reports.php?export=csv&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" 
                       class="btn btn-success">
                        <i class="fas fa-file-csv me-1"></i> Export CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-1"></i> Print Report
                    </button>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" 
                                   value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" 
                                   value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fas fa-filter me-1"></i> Apply Filter
                            </button>
                            <a href="reports.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Revenue</h6>
                            <h2 class="mb-0">$<?php echo number_format($summary['total_revenue'], 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6 class="card-title">Total Bookings</h6>
                            <h2 class="mb-0"><?php echo number_format($summary['total_bookings']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6 class="card-title">Tickets Sold</h6>
                            <h2 class="mb-0"><?php echo number_format($summary['total_tickets']); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6 class="card-title">Unique Customers</h6>
                            <h2 class="mb-0"><?php echo number_format($summary['unique_customers']); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Revenue Chart -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Daily Revenue</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="revenueChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Category Stats -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Revenue by Category</h5>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Category Performance</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Avg. Booking Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_stats as $stat): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($stat['category_name']); ?></td>
                                        <td><?php echo number_format((int)$stat['booking_count']); ?></td>
                                        <td>$<?php echo number_format((float)$stat['revenue'], 2); ?></td>
                                        <td>$<?php echo $stat['booking_count'] > 0 ? 
                                            number_format((float)$stat['revenue'] / (int)$stat['booking_count'], 2) : 
                                            '0.00'; ?></td>
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

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Daily Revenue Chart
const revenueData = <?php echo json_encode($daily_revenue); ?>;
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
        labels: revenueData.map(item => item.date),
        datasets: [{
            label: 'Revenue',
            data: revenueData.map(item => item.revenue),
            borderColor: 'rgb(75, 192, 192)',
            tension: 0.1
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value;
                    }
                }
            }
        }
    }
});

// Category Revenue Chart
const categoryData = <?php echo json_encode($category_stats); ?>;
new Chart(document.getElementById('categoryChart'), {
    type: 'pie',
    data: {
        labels: categoryData.map(item => item.category_name),
        datasets: [{
            data: categoryData.map(item => item.revenue),
            backgroundColor: [
                'rgb(255, 99, 132)',
                'rgb(54, 162, 235)',
                'rgb(255, 205, 86)',
                'rgb(75, 192, 192)',
                'rgb(153, 102, 255)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});
</script>

<?php include '../includes/footer.php'; ?>