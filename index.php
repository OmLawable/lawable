<?php
require_once __DIR__ . '/includes/functions.php';
start_secure_session();

if (is_logged_in()) {
    header('Location: pages/dashboard.php');
} else {
    header('Location: pages/offerings.php');
}
exit;
