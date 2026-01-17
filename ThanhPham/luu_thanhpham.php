<?php
include '../include/connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('Method not allowed');
}

$ten_hang      = trim($_POST['ten_hang'] ?? '');
$don_vi_tinh   = trim($_POST['don_vi_tinh'] ?? '');
$don_gia       = $_POST['don_gia'] ?? '';
$muc_du_tru_max = $_POST['muc_du_tru_max'] ?? '';
$muc_du_tru_min = $_POST['muc_du_tru_min'] ?? '';
$ma_loai_hang  = $_POST['ma_loai_hang'] ?? '';

// Kiểm tra tất cả các trường bắt buộc
if ($ten_hang === '' || $don_vi_tinh === '' || $don_gia === '' || $muc_du_tru_min === '' || $muc_du_tru_max === '' || $ma_loai_hang === '') {
    header('Location: thanhpham.php?error=' . urlencode('Vui lòng điền đầy đủ tất cả các trường bắt buộc'));
    exit;
}

$don_gia = floatval($don_gia);
$muc_du_tru_max = floatval($muc_du_tru_max);
$muc_du_tru_min = floatval($muc_du_tru_min);

// Kiểm tra min phải nhỏ hơn max
if ($muc_du_tru_min > 0 && $muc_du_tru_max > 0 && $muc_du_tru_min >= $muc_du_tru_max) {
    header('Location: thanhpham.php?error=' . urlencode('Mức dự trù Min phải nhỏ hơn Mức dự trù Max'));
    exit;
}

// Tự động tạo mã hàng theo định dạng H001, H002, ...
// Lấy mã hàng lớn nhất có định dạng H###
$sql_max_ma = "SELECT MAX(ma_hang) AS max_ma FROM hang_hoa WHERE ma_hang LIKE 'H%' AND LENGTH(ma_hang) = 4";
$stmt_max = $pdo->prepare($sql_max_ma);
$stmt_max->execute();
$result = $stmt_max->fetch(PDO::FETCH_ASSOC);

$max_ma = $result['max_ma'] ?? 'H000';

// Trích xuất số và tăng lên 1
$next_number = 1;
if (preg_match('/H(\d+)/', $max_ma, $matches)) {
    $next_number = (int)$matches[1] + 1;
}

// Định dạng mã hàng: H001, H002, ...
$ma_hang = 'H' . str_pad($next_number, 3, '0', STR_PAD_LEFT);

// Kiểm tra trùng mã hàng (phòng trường hợp có mã không theo định dạng)
do {
    $check = $pdo->prepare("SELECT COUNT(*) FROM hang_hoa WHERE ma_hang = ?");
    $check->execute([$ma_hang]);
    $exists = $check->fetchColumn() > 0;
    
    if ($exists) {
        // Nếu trùng, tìm mã tiếp theo
        $next_number++;
        $ma_hang = 'H' . str_pad($next_number, 3, '0', STR_PAD_LEFT);
    }
} while ($exists);

try {
    // Thêm thành phẩm vào bảng hang_hoa
    $stmt = $pdo->prepare("
        INSERT INTO hang_hoa
        (ma_hang, ten_hang, don_vi_tinh, don_gia, muc_du_tru_min, muc_du_tru_max, ma_loai_hang)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $ma_hang,
        $ten_hang,
        $don_vi_tinh,
        $don_gia,
        $muc_du_tru_min,
        $muc_du_tru_max,
        $ma_loai_hang
    ]);

    header('Location: thanhpham.php?success=1');
    exit;
} catch (Exception $e) {
    // Rollback nếu có lỗi
    $pdo->rollBack();
    header('Location: thanhpham.php?error=' . urlencode("Lỗi: " . $e->getMessage()));
    exit;
}
