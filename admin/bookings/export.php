<?php
// admin/bookings/export.php
require_once '../../includes/init.php';
requireAdmin();

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

// Fetch bookings for export
$query = "
    SELECT 
        b.id as booking_id,
        e.title as event_title,
        e.start_date as event_date,
        CONCAT(u.first_name, ' ', u.last_name) as customer_name,
        u.email as customer_email,
        b.quantity,
        b.total_amount,
        b.status,
        b.payment_status,
        b.created_at as booking_date
    FROM bookings b
    JOIN events e ON b.event_id = e.id
    JOIN users u ON b.user_id = u.id
    WHERE " . implode(" AND ", $where) . "
    ORDER BY b.created_at DESC
";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="bookings-export-' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel encoding
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Booking ID',
    'Event',
    'Event Date',
    'Customer Name',
    'Customer Email',
    'Quantity',
    'Total Amount',
    'Status',
    'Payment Status',
    'Booking Date'
]);

// Add data rows
foreach ($bookings as $booking) {
    fputcsv($output, [
        str_pad($booking['booking_id'], 8, '0', STR_PAD_LEFT),
        $booking['event_title'],
        date('Y-m-d H:i', strtotime($booking['event_date'])),
        $booking['customer_name'],
        $booking['customer_email'],
        $booking['quantity'],
        number_format($booking['total_amount'], 2),
        ucfirst($booking['status']),
        ucfirst($booking['payment_status']),
        date('Y-m-d H:i:s', strtotime($booking['booking_date']))
    ]);
}

fclose($output);
exit;
?>
