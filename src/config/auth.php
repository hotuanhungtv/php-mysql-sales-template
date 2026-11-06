<?php

require_once __DIR__ . '/session.php';

function is_staff(): bool
{
    return !empty($_SESSION['staff_id']);
}

function is_admin(): bool
{
    return is_staff() && ($_SESSION['staff_role'] ?? '') === 'admin';
}

/* Nhân viên hoặc admin */
function require_staff(): void
{
    if (!is_staff()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/* Chỉ admin */
function require_admin(): void
{
    require_staff();

    if (!is_admin()) {
        http_response_code(403);
        die('403 - Bạn không có quyền truy cập chức năng này. '
            . '<a href="/admin/">Quay lại</a>');
    }
}
