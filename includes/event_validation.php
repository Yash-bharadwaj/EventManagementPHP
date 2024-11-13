<?php
// includes/event_validation.php

function validateEventData($data, &$errors = []) {
    // Title validation
    if (empty($data['title'])) {
        $errors[] = "Title is required";
    } elseif (strlen($data['title']) > 100) {
        $errors[] = "Title must be less than 100 characters";
    }
    
    // Description validation
    if (empty($data['description'])) {
        $errors[] = "Description is required";
    }
    
    // Category validation
    if (empty($data['category_id']) || !is_numeric($data['category_id'])) {
        $errors[] = "Please select a valid category";
    }
    
    // Date validation
    if (empty($data['start_date']) || empty($data['start_time'])) {
        $errors[] = "Start date and time are required";
    }
    
    if (empty($data['end_date']) || empty($data['end_time'])) {
        $errors[] = "End date and time are required";
    }
    
    // Create datetime objects for comparison
    $start = new DateTime($data['start_date'] . ' ' . $data['start_time']);
    $end = new DateTime($data['end_date'] . ' ' . $data['end_time']);
    
    if ($end <= $start) {
        $errors[] = "End date must be after start date";
    }
    
    // Location validation
    if (empty($data['location'])) {
        $errors[] = "Location is required";
    }
    
    // Capacity validation
    if (!is_numeric($data['capacity']) || $data['capacity'] < 1) {
        $errors[] = "Capacity must be a positive number";
    }
    
    // Price validation
    if (!is_numeric($data['price']) || $data['price'] < 0) {
        $errors[] = "Price must be zero or greater";
    }
    
    // Status validation
    $validStatuses = ['draft', 'published', 'cancelled'];
    if (!in_array($data['status'], $validStatuses)) {
        $errors[] = "Invalid status selected";
    }
    
    return empty($errors);
}

function validateEventImage($file, &$errors = []) {
    if (empty($file['tmp_name'])) {
        return true; // Image is optional
    }
    
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    // Check file type
    if (!in_array($file['type'], $allowed_types)) {
        $errors[] = "Invalid image type. Please upload JPG, PNG, or GIF";
        return false;
    }
    
    // Check file size
    if ($file['size'] > $max_size) {
        $errors[] = "Image size must be less than 5MB";
        return false;
    }
    
    return true;
}

function uploadEventImage($file, $old_image = null) {
    $upload_dir = BASE_PATH . '/uploads/events/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $file_extension;
    $upload_path = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
        // Delete old image if exists
        if ($old_image && file_exists(BASE_PATH . $old_image)) {
            unlink(BASE_PATH . $old_image);
        }
        return '/uploads/events/' . $filename;
    }
    
    return false;
}

function handleEventImageUpload($file, $old_image = null, &$errors = []) {
    if (empty($file['tmp_name'])) {
        return $old_image;
    }
    
    if (!validateEventImage($file, $errors)) {
        return $old_image;
    }
    
    $image_url = uploadEventImage($file, $old_image);
    if (!$image_url) {
        $errors[] = "Failed to upload image";
        return $old_image;
    }
    
    return $image_url;
}
?>