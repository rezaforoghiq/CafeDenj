<?php

declare(strict_types=1);

// Barista events wrapper: events are admin-only. If an admin visits, include admin page;
// if a barista visits, explicitly deny access with 403.
require_once __DIR__ . '/../../includes/barista-auth.php';
require_once __DIR__ . '/../../classes/Auth.php';

// Ensure user is logged in (redirects to /barista/ if not)
requireBaristaLogin();

if (Auth::isAdmin()) {
    // Admins may access the real admin/events.php page
    require_once __DIR__ . '/../admin/events.php';
    exit;
}

// For baristas always deny access
http_response_code(403);
echo 'Access denied';
exit;
