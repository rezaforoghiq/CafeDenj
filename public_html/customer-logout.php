<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/customer-auth.php';
customerLogout();
header('Location: index');
exit;
