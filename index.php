<?php
// index.php
require_once 'includes/init.php';
$pageTitle = 'Welcome';

// Get database connection
$conn = Database::getInstance()->getConnection();

// Fetch featured events
$stmt = $conn->query("
    SELECT e.*, c.name as category_name,
           (SELECT COUNT(*) FROM bookings WHERE event_id = e.id AND status = 'confirmed') as booking_count
    FROM events e
    LEFT JOIN categories c ON e.category_id = c.id
    WHERE e.status = 'published' 
    AND e.start_date >= CURRENT_DATE
    ORDER BY e.start_date ASC
    LIMIT 6
");
$featured_events = $stmt->fetchAll();

// Fetch categories with event counts
$stmt = $conn->query("
    SELECT c.*, COUNT(e.id) as event_count
    FROM categories c
    LEFT JOIN events e ON c.id = e.category_id 
    AND e.status = 'published' 
    AND e.start_date >= CURRENT_DATE
    GROUP BY c.id
    HAVING event_count > 0
    ORDER BY event_count DESC
");
$categories = $stmt->fetchAll();

// Fetch upcoming events count
$stmt = $conn->query("
    SELECT COUNT(*) FROM events 
    WHERE status = 'published' 
    AND start_date >= CURRENT_DATE
");
$total_events = $stmt->fetchColumn();

include 'includes/header.php';
?>

<!-- Hero Section -->
<div class="hero-section position-relative mb-5">
    <div class="bg-primary text-white py-5 position-relative overflow-hidden">
        <div class="container position-relative" style="z-index: 1;">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4 animate__animated animate__fadeInUp">
                        Discover Amazing Events
                    </h1>
                    <p class="lead mb-4 animate__animated animate__fadeInUp animate__delay-1s">
                        Join <?php echo number_format($total_events); ?> upcoming events and create unforgettable memories.
                    </p>
                    <form action="events/search.php" method="GET" 
                          class="mb-4 animate__animated animate__fadeInUp animate__delay-2s">
                        <div class="input-group input-group-lg">
                            <input type="text" class="form-control" name="search" 
                                   placeholder="Search events...">
                            <button class="btn btn-light" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </form>
                    <div class="animate__animated animate__fadeInUp animate__delay-3s">
                        <a href="events/index.php" class="btn btn-light btn-lg me-2">
                            Browse Events
                        </a>
                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <a href="auth/register.php" class="btn btn-outline-light btn-lg">
                                Join Now
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-lg-6 d-none d-lg-block text-center">
                    <img src="assets/images/defaults/events/music/festival-1.jpg" alt="Events Illustration" 
                         class="img-fluid animate__animated animate__fadeInRight">
                </div>
            </div>
        </div>
        <!-- Decorative circles -->
        <div class="position-absolute top-0 end-0 mt-n3 me-n3">
            <div class="rounded-circle bg-white opacity-10" style="width: 200px; height: 200px;"></div>
        </div>
        <div class="position-absolute bottom-0 start-0 mb-n3 ms-n3">
            <div class="rounded-circle bg-white opacity-10" style="width: 150px; height: 150px;"></div>
        </div>
    </div>
</div>

<!-- Featured Events -->
<div class="container">
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Featured Events</h2>
                <a href="events/index.php" class="btn btn-outline-primary">
                    View All Events
                </a>
            </div>
        </div>
        
        <?php if (empty($featured_events)): ?>
            <div class="col-12">
                <div class="alert alert-info">
                    No upcoming events found. Check back soon!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($featured_events as $event): ?>
                <div class="col-md-4 mb-4">
                    <div class="card event-card h-100 shadow-sm">
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
                                <span class="badge bg-<?php echo $event['capacity'] - $event['booking_count'] <= 10 ? 'danger' : 'success'; ?>">
                                    <?php echo $event['capacity'] - $event['booking_count']; ?> spots left
                                </span>
                            </div>
                            
                            <h5 class="card-title">
                                <?php echo htmlspecialchars($event['title']); ?>
                            </h5>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-calendar-alt me-1"></i>
                                <?php echo date('M d, Y - g:i A', strtotime($event['start_date'])); ?>
                            </p>
                            
                            <p class="text-muted mb-2">
                                <i class="fas fa-map-marker-alt me-1"></i>
                                <?php echo htmlspecialchars($event['location']); ?>
                            </p>
                            
                            <p class="card-text">
                                <?php echo substr(htmlspecialchars($event['description']), 0, 100) . '...'; ?>
                            </p>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <span class="h5 mb-0">$<?php echo number_format($event['price'], 2); ?></span>
                                <a href="events/view.php?id=<?php echo $event['id']; ?>" 
                                   class="btn btn-outline-primary">
                                    Learn More
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Event Categories -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">Browse by Category</h2>
            </div>
        </div>
        
        <?php foreach ($categories as $category): ?>
            <div class="col-md-3 col-sm-6 mb-4">
                <a href="events/search.php?category=<?php echo $category['id']; ?>" 
                   class="text-decoration-none">
                    <div class="card category-card h-100 shadow-sm">
                        <div class="card-body text-center">
                            <div class="category-icon mb-3">
                                <i class="fas fa-<?php 
                                    echo strtolower($category['name']) === 'music' ? 'music' : 
                                        (strtolower($category['name']) === 'sports' ? 'basketball-ball' : 
                                        (strtolower($category['name']) === 'technology' ? 'laptop-code' : 
                                        'star')); ?> fa-3x text-primary"></i>
                            </div>
                            <h5 class="card-title mb-2"><?php echo htmlspecialchars($category['name']); ?></h5>
                            <p class="text-muted mb-0">
                                <?php echo number_format($category['event_count']); ?> Events
                            </p>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Call to Action -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card bg-primary text-white">
                <div class="card-body text-center py-5">
                    <h2 class="mb-4">Ready to Host Your Own Event?</h2>
                    <p class="lead mb-4">Join our community and start creating unforgettable experiences.</p>
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <a href="auth/register.php" class="btn btn-light btn-lg">Get Started</a>
                    <?php else: ?>
                        <a href="events/create.php" class="btn btn-light btn-lg">Create Event</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Add animation classes */
.animate__animated {
    animation-duration: 1s;
    animation-fill-mode: both;
}

.animate__fadeInUp {
    animation-name: fadeInUp;
}

.animate__fadeInRight {
    animation-name: fadeInRight;
}

.animate__delay-1s {
    animation-delay: 0.2s;
}

.animate__delay-2s {
    animation-delay: 0.4s;
}

.animate__delay-3s {
    animation-delay: 0.6s;
}

/* Card hover effects */
.event-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.event-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.category-card {
    transition: all 0.2s ease-in-out;
}

.category-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
}

.category-icon {
    transition: transform 0.2s ease-in-out;
}

.category-card:hover .category-icon {
    transform: scale(1.1);
}

/* Hero section decorative elements */
.opacity-10 {
    opacity: 0.1;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translate3d(0, 20px, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}

@keyframes fadeInRight {
    from {
        opacity: 0;
        transform: translate3d(20px, 0, 0);
    }
    to {
        opacity: 1;
        transform: translate3d(0, 0, 0);
    }
}
</style>

<?php include 'includes/footer.php'; ?>