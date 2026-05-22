<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

if (current_user()) {
    log_activity('Admin signed out', 'auth');
}

$_SESSION = [];
session_destroy();
redirect('login.php');
