<?php

declare(strict_types=1);

/**
 * api/logout.php — Smooth, instant logout endpoint for Lawable.
 */

require_once __DIR__ . '/../includes/functions.php';
start_secure_session();

logout_user();
set_flash('success', 'Logged out successfully.');

redirect('pages/login.php');

