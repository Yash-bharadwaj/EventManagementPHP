<?php
// events/search.php
require_once '../includes/init.php';

$pageTitle = 'Search Events';
$conn = Database::getInstance()->getConnection();

// Get search parameters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$date_filter = isset($_GET['date']) ? sanitize($_GET['date']) : '';
$price_range = isset($_GET['price_range']) ? sanitize($_GET['price_range']) : '';
$sort = isset($_GET['sort']) ? sanitize($_GET['sort']) : 'date_asc';

// Build query conditions
$where = ["e.status = 'published' AND e.start_date >= CURRENT_DATE"];
$params = [];

if ($search) {
    $where[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
}

if ($category) {
    $where[] = "e.category_id = ?";
    $params[] = $category;
}

if ($date_filter) {
    switch ($date_filter) {
        case 'today':
            $where[] = "DATE(e.start_date) = CURRENT_DATE";
            break;
        case 'tomorrow':
            $where[] = "DATE(e.start_date) = DATE_ADD(CURRENT_DATE, INTERVAL 1 DAY)";
            break;
        case 'this_week':
            $where[] = "YEARWEEK(e.start_date, 1) = YEARWEEK(CURRENT_DATE, 1)";
            break;
        case 'this_month':
            $where[] = "MONTH(e.start_date) = MONTH(CURRENT_DATE) AND YEAR(e.start_date) = YEAR(CURRENT_DATE)";
            break;
        case 'next_month':
            $where[] = "MONTH(e.start_date) = MONTH(DATE_ADD(CURRENT_DATE, INTERVAL 1 MONTH))";
            break;
    }
}

if ($price_range) {
    switch ($price_range) {
        case 'free':
            $where[] = "e.price = 0";
            break;
        case 'under_50':
            $where[] = "e.price > 0 AND e.price <= 50";
            break;
        case '50_100':
            $where[] = "e.price > 50 AND e.price <= 100";
            break;
        case '100_plus':
            $where[] = "e.price > 100";
            break;
    }
}

// Sort options
$order_by = match($sort) {
    'price_asc' => 'e.price ASC',
    'price_desc' => 'e.price DESC',
    'date_desc' => 'e.start_date DESC',
    default => 'e.start_date ASC'
};

// Fetch categories for filter
$categories = $conn->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Fetch events
$query = "
    SELECT e.*, 
           c.name as category_name,
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id AND status = 'confirmed') as booking_count
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY " . $order_by;

$stmt = $conn->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container py-4">
    <!-- Search Hero Section -->
    <div class="bg-primary text-white rounded-3 p-5 mb-4">
        <div class="row align-items-center">
            <div class="col-lg-8 col-md-12">
                <h1 class="display-5 fw-bold mb-3">Find Your Next Event</h1>
                <p class="lead mb-4">Discover amazing events happening around you</p>
                <form action="" method="GET" class="row g-3">
                    <div class="col-md-8">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search events..." 
                                   value="<?php echo htmlspecialchars($search); ?>">
                            <button class="btn btn-light" type="submit">
                                <i class="fas fa-search"></i> Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-4 d-none d-lg-block text-center">
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
                            <div class="list-group list-group-flush">
                                <?php foreach ($categories as $cat): ?>
                                    <label class="list-group-item">
                                        <input class="form-check-input me-2" type="radio" 
                                               name="category" value="<?php echo $cat['id']; ?>"
                                               <?php echo $category == $cat['id'] ? 'checked' : ''; ?>>
                                        <?php echo htmlspecialchars($cat['name']); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Date Filter -->
                        <div class="mb-4">
                            <h6 class="mb-2">Date</h6>
                            <div class="list-group list-group-flush">
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="date" value="today"
                                           <?php echo $date_filter === 'today' ? 'checked' : ''; ?>>
                                    Today
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="date" value="tomorrow"
                                           <?php echo $date_filter === 'tomorrow' ? 'checked' : ''; ?>>
                                    Tomorrow
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="date" value="this_week"
                                           <?php echo $date_filter === 'this_week' ? 'checked' : ''; ?>>
                                    This Week
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="date" value="this_month"
                                           <?php echo $date_filter === 'this_month' ? 'checked' : ''; ?>>
                                    This Month
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="date" value="next_month"
                                           <?php echo $date_filter === 'next_month' ? 'checked' : ''; ?>>
                                    Next Month
                                </label>
                            </div>
                        </div>

                        <!-- Price Range -->
                        <div class="mb-4">
                            <h6 class="mb-2">Price Range</h6>
                            <div class="list-group list-group-flush">
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="price_range" value="free"
                                           <?php echo $price_range === 'free' ? 'checked' : ''; ?>>
                                    Free
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="price_range" value="under_50"
                                           <?php echo $price_range === 'under_50' ? 'checked' : ''; ?>>
                                    Under $50
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="price_range" value="50_100"
                                           <?php echo $price_range === '50_100' ? 'checked' : ''; ?>>
                                    $50 - $100
                                </label>
                                <label class="list-group-item">
                                    <input class="form-check-input me-2" type="radio" 
                                           name="price_range" value="100_plus"
                                           <?php echo $price_range === '100_plus' ? 'checked' : ''; ?>>
                                    $100+
                                </label>
                            </div>
                        </div>

                        <!-- Sort -->
                        <div class="mb-4">
                            <h6 class="mb-2">Sort By</h6>
                            <select class="form-select" name="sort">
                                <option value="date_asc" <?php echo $sort === 'date_asc' ? 'selected' : ''; ?>>
                                    Date (Nearest First)
                                </option>
                                <option value="date_desc" <?php echo $sort === 'date_desc' ? 'selected' : ''; ?>>
                                    Date (Latest First)
                                </option>
                                <option value="price_asc" <?php echo $sort === 'price_asc' ? 'selected' : ''; ?>>
                                    Price (Low to High)
                                </option>
                                <option value="price_desc" <?php echo $sort === 'price_desc' ? 'selected' : ''; ?>>
                                    Price (High to Low)
                                </option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            Apply Filters
                        </button>
                        <a href="search.php" class="btn btn-outline-secondary w-100">
                            Clear Filters
                        </a>
                    </form>
                </div>
            </div>
        </div>

        <!-- Events Grid -->
        <div class="col-md-9">
            <?php if (!empty($search || $category || $date_filter || $price_range)): ?>
                <div class="mb-4">
                    <h5>Search Results</h5>
                    <div class="text-muted">
                        <?php 
                        $filters = [];
                        if ($search) $filters[] = "Search: \"" . htmlspecialchars($search) . "\"";
                        if ($category) {
                            foreach ($categories as $cat) {
                                if ($cat['id'] == $category) {
                                    $filters[] = "Category: " . htmlspecialchars($cat['name']);
                                    break;
                                }
                            }
                        }
                        if ($date_filter) {
                            $date_labels = [
                                'today' => 'Today',
                                'tomorrow' => 'Tomorrow',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'next_month' => 'Next Month'
                            ];
                            $filters[] = "Date: " . $date_labels[$date_filter];
                        }
                        if ($price_range) {
                            $price_labels = [
                                'free' => 'Free Events',
                                'under_50' => 'Under $50',
                                '50_100' => '$50 - $100',
                                '100_plus' => '$100+'
                            ];
                            $filters[] = "Price: " . $price_labels[$price_range];
                        }
                        echo count($events) . " events found for " . implode(", ", $filters);
                        ?>
                    </div>
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
                                    
                                    <div class="d-grid">
                                        <a href="view.php?id=<?php echo $event['id']; ?>" 
                                           class="btn btn-outline-primary">
                                            View Details
                                        </a>
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

<script>
// Auto-submit form when filters change
document.addEventListener('DOMContentLoaded', function() {
    const filterForm = document.querySelector('.card-body form');
    const filterInputs = filterForm.querySelectorAll('input[type="radio"], select');
    
    filterInputs.forEach(input => {
        input.addEventListener('change', () => {
            filterForm.submit();
        });
    });
});
</script>

<style>
/* Custom styles for event cards */
.event-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.event-card .card-img-top {
    height: 200px;
    object-fit: cover;
}

/* Custom styles for filters */
.list-group-item {
    border: none;
    padding: 0.5rem 0;
}

.list-group-item:hover {
    background: none;
}

.form-check-input:checked {
    background-color: var(--bs-primary);
    border-color: var(--bs-primary);
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .bg-primary {
        padding: 2rem !important;
    }
    
    .display-5 {
        font-size: 2rem;
    }
}
</style>

<?php include '../includes/footer.php'; ?>