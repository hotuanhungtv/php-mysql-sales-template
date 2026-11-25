<?php

require_once '/var/www/src/config/auth.php';
require_staff();

require_once '/var/www/src/config/database.php';
require_once '/var/www/src/config/reviews.php';
reviews_ensure_table($conn);

$pageTitle = 'Đánh giá sản phẩm';

/* ===== Xử lý thao tác (POST): ẩn/hiện, trả lời, xóa ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $reviewID = (int) ($_POST['review_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $back = '/admin/reviews/' . (!empty($_POST['back']) && str_starts_with($_POST['back'], '?')
        ? $_POST['back'] : '');

    if ($reviewID > 0) {
        if ($action === 'toggle') {
            $stmt = $conn->prepare('UPDATE reviews SET IsVisible = 1 - IsVisible, IsRead = 1 WHERE ReviewID = ?');
            $stmt->bind_param('i', $reviewID);
            $stmt->execute();
            $stmt->close();
            flash_set('success', 'Đã cập nhật trạng thái hiển thị của đánh giá.');

        } elseif ($action === 'reply') {
            $reply = trim((string) ($_POST['reply'] ?? ''));

            if (mb_strlen($reply, 'UTF-8') > 1000) {
                flash_set('danger', 'Nội dung phản hồi tối đa 1000 ký tự.');
            } else {
                $stmt = $conn->prepare(
                    'UPDATE reviews
                     SET AdminReply = NULLIF(?, \'\'),
                         RepliedAt = IF(? = \'\', NULL, NOW()),
                         IsRead = 1
                     WHERE ReviewID = ?'
                );
                $stmt->bind_param('ssi', $reply, $reply, $reviewID);
                $stmt->execute();
                $stmt->close();
                flash_set('success', $reply === '' ? 'Đã xóa phản hồi.' : 'Đã gửi phản hồi tới khách hàng.');
            }

        } elseif ($action === 'delete') {
            if (!is_admin()) {
                flash_set('danger', 'Chỉ quản trị viên mới được xóa đánh giá.');
            } else {
                $stmt = $conn->prepare('DELETE FROM reviews WHERE ReviewID = ?');
                $stmt->bind_param('i', $reviewID);
                $stmt->execute();
                $stmt->close();
                flash_set('success', 'Đã xóa đánh giá.');
            }
        }
    }

    header('Location: ' . $back);
    exit;
}

/* ===== Bộ lọc ===== */
$rating  = (int) ($_GET['rating'] ?? 0);
$state   = $_GET['state'] ?? '';
$keyword = trim($_GET['keyword'] ?? '');
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = [];
$types = '';
$params = [];

if ($rating >= 1 && $rating <= 5) {
    $where[] = 'r.Rating = ?';
    $types .= 'i';
    $params[] = $rating;
}

if ($state === 'new') {
    $where[] = 'r.IsRead = 0';
} elseif ($state === 'hidden') {
    $where[] = 'r.IsVisible = 0';
} elseif ($state === 'noreply') {
    $where[] = 'r.AdminReply IS NULL';
}

if ($keyword !== '') {
    $where[] = '(p.ProductName LIKE ? OR p.ProductCode LIKE ? OR c.CustomerName LIKE ? OR r.Comment LIKE ?)';
    $types .= 'ssss';
    $like = '%' . $keyword . '%';
    array_push($params, $like, $like, $like, $like);
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$from = "FROM reviews r
         JOIN products p ON p.ProductID = r.ProductID
         JOIN customers c ON c.CustomerID = r.CustomerID";

/* Thống kê tổng quát */
$stats = $conn->query("
    SELECT COUNT(*) AS total, COALESCE(AVG(Rating), 0) AS average,
           COALESCE(SUM(IsRead = 0), 0) AS unread,
           COALESCE(SUM(IsVisible = 0), 0) AS hidden
    FROM reviews
")->fetch_assoc();

/* Tổng số dòng theo bộ lọc */
$stmt = $conn->prepare("SELECT COUNT(*) AS total $from $whereSql");
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$totalRows = (int) $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$totalPages = max(1, (int) ceil($totalRows / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$stmt = $conn->prepare("
    SELECT r.ReviewID, r.ProductID, r.OrderID, r.Rating, r.Comment, r.IsVisible, r.IsRead,
           r.AdminReply, r.RepliedAt, r.CreatedAt,
           p.ProductName, p.ProductCode, c.CustomerName, c.Phone
    $from
    $whereSql
    ORDER BY r.IsRead ASC, r.CreatedAt DESC
    LIMIT ? OFFSET ?
");
$listTypes = $types . 'ii';
$listParams = array_merge($params, [$perPage, $offset]);
$stmt->bind_param($listTypes, ...$listParams);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* Đã mở trang là coi như nhân viên đã xem các đánh giá đang hiển thị trong danh sách */
$newIds = array_column(array_filter($reviews, static fn($r) => (int) $r['IsRead'] === 0), 'ReviewID');
$newLookup = array_flip($newIds);

if ($newIds) {
    $conn->query('UPDATE reviews SET IsRead = 1 WHERE ReviewID IN (' . implode(',', array_map('intval', $newIds)) . ')');
}

$queryString = '?' . http_build_query(array_filter([
    'rating' => $rating ?: null, 'state' => $state ?: null,
    'keyword' => $keyword ?: null, 'page' => $page > 1 ? $page : null,
]));
$queryString = $queryString === '?' ? '' : $queryString;

require_once '/var/www/src/includes/admin/header.php';
require_once '/var/www/src/includes/admin/navbar.php';
?>

<main class="container-fluid px-4 py-4">

    <div class="mb-4">
        <h1 class="mb-1">Đánh giá &amp; nhận xét</h1>
        <p class="text-muted mb-0">Phản hồi của khách hàng sau khi mua sản phẩm.</p>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon blue"><i class="bi bi-chat-square-text"></i></div>
                <div><div class="stat-label">Tổng đánh giá</div>
                <div class="stat-value"><?= (int) $stats['total'] ?></div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="stat-card">
                <div class="stat-icon orange"><i class="bi bi-star-fill"></i></div>
                <div><div class="stat-label">Điểm trung bình</div>
                <div class="stat-value"><?= number_format((float) $stats['average'], 1) ?> / 5</div></div>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <a class="status-link" href="/admin/reviews/?state=new">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="bi bi-bell"></i></div>
                    <div><div class="stat-label">Đánh giá mới</div>
                    <div class="stat-value"><?= (int) $stats['unread'] ?></div></div>
                </div>
            </a>
        </div>
        <div class="col-6 col-xl-3">
            <a class="status-link" href="/admin/reviews/?state=hidden">
                <div class="stat-card">
                    <div class="stat-icon green"><i class="bi bi-eye-slash"></i></div>
                    <div><div class="stat-label">Đang bị ẩn</div>
                    <div class="stat-value"><?= (int) $stats['hidden'] ?></div></div>
                </div>
            </a>
        </div>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-2">
            <select name="rating" class="form-select">
                <option value="">Tất cả số sao</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>" <?= $rating === $i ? 'selected' : '' ?>><?= $i ?> sao</option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="col-md-3">
            <select name="state" class="form-select">
                <option value="">Tất cả trạng thái</option>
                <option value="new" <?= $state === 'new' ? 'selected' : '' ?>>Mới (chưa xem)</option>
                <option value="noreply" <?= $state === 'noreply' ? 'selected' : '' ?>>Chưa phản hồi</option>
                <option value="hidden" <?= $state === 'hidden' ? 'selected' : '' ?>>Đang ẩn</option>
            </select>
        </div>
        <div class="col-md-4">
            <input type="text" name="keyword" class="form-control" value="<?= e($keyword) ?>"
                   placeholder="Sản phẩm, khách hàng hoặc nội dung nhận xét">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary">Lọc</button>
            <a href="/admin/reviews/" class="btn btn-outline-secondary">Xóa lọc</a>
        </div>
    </form>

    <?php if (!$reviews): ?>
        <div class="alert alert-info">Chưa có đánh giá nào phù hợp.</div>
    <?php endif; ?>

    <?php foreach ($reviews as $r): ?>
        <?php $rid = (int) $r['ReviewID']; ?>
        <div class="card review-admin-card mb-3 <?= $r['IsVisible'] ? '' : 'is-hidden' ?>">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                    <div>
                        <a class="fw-bold text-decoration-none" target="_blank"
                           href="/product-detail.php?id=<?= (int) $r['ProductID'] ?>#reviews">
                            <?= e($r['ProductName']) ?>
                        </a>
                        <span class="text-muted small">(<?= e($r['ProductCode']) ?>)</span>
                        <?php if (isset($newLookup[$rid])): ?>
                            <span class="badge bg-warning text-dark ms-1">Mới</span>
                        <?php endif; ?>
                        <?php if (!$r['IsVisible']): ?>
                            <span class="badge bg-secondary ms-1">Đang ẩn</span>
                        <?php endif; ?>
                    </div>
                    <small class="text-muted"><?= e(date('d/m/Y H:i', strtotime($r['CreatedAt']))) ?></small>
                </div>

                <div class="mb-2">
                    <?= review_stars((float) $r['Rating']) ?>
                    <strong class="ms-2"><?= e($r['CustomerName']) ?></strong>
                    <?php if (!empty($r['Phone'])): ?>
                        <span class="text-muted small">· <?= e($r['Phone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['OrderID'])): ?>
                        <a class="small ms-2" href="/admin/orders/detail.php?id=<?= (int) $r['OrderID'] ?>">
                            Đơn #<?= (int) $r['OrderID'] ?>
                        </a>
                    <?php endif; ?>
                </div>

                <p class="mb-3"><?= nl2br(e($r['Comment'])) ?></p>

                <?php if (!empty($r['AdminReply'])): ?>
                    <div class="review-reply mb-3">
                        <strong><i class="bi bi-shop"></i> Phản hồi của cửa hàng</strong>
                        <p class="mb-0"><?= nl2br(e($r['AdminReply'])) ?></p>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <button class="btn btn-sm btn-primary" type="button"
                            data-bs-toggle="collapse" data-bs-target="#reply<?= $rid ?>">
                        <i class="bi bi-reply"></i> <?= $r['AdminReply'] ? 'Sửa phản hồi' : 'Phản hồi' ?>
                    </button>

                    <form method="post" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="review_id" value="<?= $rid ?>">
                        <input type="hidden" name="back" value="<?= e($queryString) ?>">
                        <button class="btn btn-sm btn-warning" name="action" value="toggle">
                            <i class="bi <?= $r['IsVisible'] ? 'bi-eye-slash' : 'bi-eye' ?>"></i>
                            <?= $r['IsVisible'] ? 'Ẩn đánh giá' : 'Hiện đánh giá' ?>
                        </button>
                    </form>

                    <?php if (is_admin()): ?>
                        <form method="post" class="d-inline"
                              onsubmit="return confirm('Xóa vĩnh viễn đánh giá này?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="review_id" value="<?= $rid ?>">
                            <input type="hidden" name="back" value="<?= e($queryString) ?>">
                            <button class="btn btn-sm btn-outline-danger" name="action" value="delete">
                                <i class="bi bi-trash"></i> Xóa
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="collapse mt-3" id="reply<?= $rid ?>">
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="review_id" value="<?= $rid ?>">
                        <input type="hidden" name="back" value="<?= e($queryString) ?>">
                        <textarea name="reply" class="form-control mb-2" rows="3" maxlength="1000"
                                  placeholder="Nhập phản hồi gửi tới khách hàng (hiển thị công khai dưới đánh giá)"><?= e($r['AdminReply'] ?? '') ?></textarea>
                        <button class="btn btn-sm btn-success" name="action" value="reply">
                            <i class="bi bi-send"></i> Lưu phản hồi
                        </button>
                        <span class="text-muted small ms-2">Để trống rồi lưu để xóa phản hồi.</span>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
        <nav aria-label="Phân trang đánh giá">
            <ul class="pagination justify-content-center flex-wrap">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="/admin/reviews/?<?= e(http_build_query(array_filter([
                            'rating' => $rating ?: null, 'state' => $state ?: null,
                            'keyword' => $keyword ?: null, 'page' => $i,
                        ]))) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</main>

<?php
require_once '/var/www/src/includes/admin/footer.php';
$conn->close();
