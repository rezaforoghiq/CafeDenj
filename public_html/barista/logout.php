<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/barista-auth.php';
BaristaAuth::logout();
header('Location: ' . APP_URL . '/barista/');
exit;
