<?php
include '../include/connect.php';

$ten_hang = trim($_POST['ten_hang'] ?? '');
$don_gia = $_POST['don_gia'] ?? '';
$don_vi_tinh = trim($_POST['don_vi_tinh'] ?? '');
$muc_du_tru_min = $_POST['muc_du_tru_min'] ?? '';
$muc_du_tru_max = $_POST['muc_du_tru_max'] ?? '';
$ma_hang = trim($_POST['ma_hang'] ?? '');

// Kiểm tra tất cả các trường bắt buộc
if ($ten_hang === '' || $don_vi_tinh === '' || $don_gia === '' || $muc_du_tru_min === '' || $muc_du_tru_max === '' || $ma_hang === '') {
    header('Location: thanhpham.php?error=' . urlencode('Vui lòng điền đầy đủ tất cả các trường bắt buộc'));
    exit;
}

$don_gia = floatval($don_gia);
$muc_du_tru_min = floatval($muc_du_tru_min);
$muc_du_tru_max = floatval($muc_du_tru_max);

// Kiểm tra min phải nhỏ hơn max
if ($muc_du_tru_min > 0 && $muc_du_tru_max > 0 && $muc_du_tru_min >= $muc_du_tru_max) {
    header('Location: thanhpham.php?error=' . urlencode('Mức dự trù Min phải nhỏ hơn Mức dự trù Max'));
    exit;
}

$stmt = $pdo->prepare("
  UPDATE hang_hoa 
  SET ten_hang = ?, don_gia = ?, don_vi_tinh = ?, muc_du_tru_min = ?, muc_du_tru_max = ?
  WHERE ma_hang = ?
");

$stmt->execute([
  $ten_hang,
  $don_gia,
  $don_vi_tinh,
  $muc_du_tru_min,
  $muc_du_tru_max,
  $ma_hang
]);

header("Location: thanhpham.php?updated=1");
exit;
?>