<?php

require_once '/var/www/src/config/session.php';

unset(
    $_SESSION['staff_id'],
    $_SESSION['staff_name'],
    $_SESSION['staff_role']
);

session_regenerate_id(true);

header('Location: /admin/login.php');
exit;
