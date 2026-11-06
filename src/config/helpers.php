<?php

/* ===== Hàm tiện ích dùng chung ===== */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money($value): string
{
    return number_format((float) $value, 0, ',', '.') . ' đ';
}

function order_status_label(string $status): string
{
    $labels = [
        'Pending'   => 'Chờ xác nhận',
        'Confirmed' => 'Đã xác nhận',
        'Shipping'  => 'Đang giao',
        'Completed' => 'Hoàn thành',
        'Cancelled' => 'Đã hủy',
    ];

    return $labels[$status] ?? $status;
}

function order_status_badge(string $status): string
{
    $classes = [
        'Pending'   => 'bg-warning text-dark',
        'Confirmed' => 'bg-primary',
        'Shipping'  => 'bg-info text-dark',
        'Completed' => 'bg-success',
        'Cancelled' => 'bg-secondary',
    ];

    return $classes[$status] ?? 'bg-secondary';
}

/* ===== Thông báo flash (hiện 1 lần rồi mất) ===== */

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_render(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    $type = in_array($flash['type'], ['success', 'danger', 'warning', 'info'], true)
        ? $flash['type']
        : 'info';

    echo '<div class="container mt-3"><div class="alert alert-' . $type . ' mb-0">'
        . e($flash['message']) . '</div></div>';
}

/* ===== CSRF ===== */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? '';

    return is_string($sent)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/* Mọi yêu cầu POST đều phải có token hợp lệ */
function csrf_guard(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_valid()) {
        http_response_code(419);
        die('Phiên làm việc không hợp lệ hoặc đã hết hạn. '
            . 'Vui lòng quay lại, tải lại trang và thử lại.');
    }
}
