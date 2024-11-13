<?php
// events/index.php
require_once '../includes/init.php';

$pageTitle = 'Events';
$conn = Database::getInstance()->getConnection();

// Get filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query conditions
$where = ["status = 'published' AND end_date >= CURRENT_DATE"];
$params = [];

if ($search) {
    $where[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

if ($category_id) {
    $where[] = "category_id = ?";
    $params[] = $category_id;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where[] = "DATE(start_date) = CURRENT_DATE";
            break;
        case 'tomorrow':
            $where[] = "DATE(start_date) = DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)";
            break;
        case 'this-week':
            $where[] = "DATE(start_date) BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 7 DAY)";
            break;
        case 'this-month':
            $where[] = "DATE(start_date) BETWEEN CURRENT_DATE AND DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH)";
            break;
    }
}

// Get categories for filter
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Fetch events
$query = "
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id AND status = 'confirmed') as booking_count
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY e.start_date ASC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Hero Banner -->
    <div class="bg-primary text-white rounded-3 p-5 mb-4">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="display-4 mb-3">Discover Amazing Events</h1>
                <p class="lead mb-4">Find and book the best events happening in your area.</p>
                <!-- <form action="" method="GET" class="mt-3">
                    <div class="input-group input-group-lg">
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search events..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                        <button class="btn btn-light" type="submit">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                </form> -->
            </div>
            <div class="col-md-4 text-center">
                <i class="fas fa-calendar-alt fa-6x opacity-50"></i>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Filters Sidebar -->
        <div class="col-md-3">
            <div class="card shadow-sm mb-4">
                <div class="card-body">
                    <h5 class="card-title mb-3">Filters</h5>
                    <form action="" method="GET">
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>

                        <!-- Categories -->
                        <div class="mb-4">
                            <h6 class="mb-2">Categories</h6>
                            <?php foreach ($categories as $category): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" 
                                           name="category" 
                                           value="<?php echo $category['id']; ?>"
                                           id="category<?php echo $category['id']; ?>"
                                           <?php echo ($category_id == $category['id']) ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="category<?php echo $category['id']; ?>">
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Date Filter -->
                        <div class="mb-4">
                            <h6 class="mb-2">Date</h6>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="date" value="today"
                                       id="dateToday" <?php echo ($date_filter === 'today') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dateToday">Today</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="date" value="tomorrow"
                                       id="dateTomorrow" <?php echo ($date_filter === 'tomorrow') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dateTomorrow">Tomorrow</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="date" value="this-week"
                                       id="dateWeek" <?php echo ($date_filter === 'this-week') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dateWeek">This Week</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="date" value="this-month"
                                       id="dateMonth" <?php echo ($date_filter === 'this-month') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="dateMonth">This Month</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-100">Apply Filters</button>
                        <?php if ($category_id || $date_filter): ?>
                            <a href="index.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" 
                               class="btn btn-link w-100">Clear Filters</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="col-md-9">
            <?php if (!empty($search || $category_id || $date_filter)): ?>
                <div class="mb-4">
                    <h5>
                        <?php
                        $filters = [];
                        if ($search) $filters[] = "Search: \"" . htmlspecialchars($search) . "\"";
                        if ($category_id) {
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $category_id) {
                                    $filters[] = "Category: " . htmlspecialchars($cat['name']);
                                    break;
                                }
                            }
                        }
                        if ($date_filter) {
                            $date_labels = [
                                'today' => 'Today',
                                'tomorrow' => 'Tomorrow',
                                'this-week' => 'This Week',
                                'this-month' => 'This Month'
                            ];
                            $filters[] = "Date: " . $date_labels[$date_filter];
                        }
                        echo "Showing results for " . implode(", ", $filters);
                        ?>
                    </h5>
                </div>
            <?php endif; ?>

            <?php if (empty($events)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No events found matching your criteria. Try adjusting your filters.
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php foreach ($events as $event): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm event-card">
                                <?php if ($event['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($event['image_url']); ?>" 
                                         class="card-img-top" alt="<?php echo htmlspecialchars($event['title']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light text-center py-5">
                                        <i class="fas fa-calendar-alt fa-3x text-muted"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge bg-primary">
                                            <?php echo htmlspecialchars($event['category_name']); ?>
                                        </span>
                                        <span class="text-primary fw-bold">
                                            $<?php echo number_format($event['price'], 2); ?>
                                        </span>
                                    </div>
                                    
                                    <h5 class="card-title">
                                        <?php echo htmlspecialchars($event['title']); ?>
                                    </h5>
                                    
                                    <p class="card-text text-muted mb-2">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?php echo htmlspecialchars($event['location']); ?>
                                    </p>
                                    
                                    <p class="card-text text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y - g:i A', strtotime($event['start_date'])); ?>
                                    </p>
                                    
                                    <?php
                                    $available = $event['capacity'] - $event['booking_count'];
                                    $availability_class = $available <= 10 ? 'text-danger' : 'text-success';
                                    ?>
                                    <p class="card-text <?php echo $availability_class; ?> mb-3">
                                        <i class="fas fa-user me-1"></i>
                                        <?php echo $available; ?> spots remaining
                                    </p>
                                    
                                    <a href="view.php?id=<?php echo $event['id']; ?>" 
                                       class="btn btn-outline-primary w-100">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-submit form when filters change
    const filterForm = document.querySelector('.card-body form');
    const filterInputs = filterForm.querySelectorAll('input[type="radio"]');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            filterForm.submit();
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>