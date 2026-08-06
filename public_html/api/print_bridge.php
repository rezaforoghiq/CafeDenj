<?php
declare(strict_types=1);
// Proxy endpoint for the Windows Print Bridge
// This file provides a clean API route: /api/print_bridge.php
// It includes the existing print_bridge.php implementation to avoid duplicating logic.

// Minimal bootstrap to match existing environment
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Setting.php';

// Forward execution to the main handler
require_once __DIR__ . '/../print_bridge.php';
