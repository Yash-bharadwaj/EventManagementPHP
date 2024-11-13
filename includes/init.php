<?php
// includes/init.php


// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load configurations
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once __DIR__ . '/helpers/ImageHelper.php';
// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: ' . BASE_URL . '/auth/login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// Function to handle uncaught exceptions
function handleException($exception) {
    error_log($exception->getMessage());
    // Redirect to error page in production
    die('An error occurred. Please try again later.');
}
set_exception_handler('handleException');