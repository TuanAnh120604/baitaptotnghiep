<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('nguoidung');

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

if ($action !== 'get_user') {
  echo json_encode(['error' => 'Action không hợp lệ']);
  exit;
}

$ma_nd = $_GET['ma_nd'] ?? '';
if (!$ma_nd) {
  echo json_encode(['error' => 'Thiếu mã người dùng']);
  exit;
}

/* 1. Lấy thông tin user */
$stmt = $pdo->prepare("
    SELECT nd.ma_nd, nd.ten_nd, nd.ma_vai_tro
    FROM nguoi_dung nd
    WHERE nd.ma_nd = ?
");
$stmt->execute([$ma_nd]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
  echo json_encode(['error' => 'Không tìm thấy người dùng']);
  exit;
}

$response = ['user' => $user];

/* 2. Nếu là QUẢN LÝ KHO */
if ($user['ma_vai_tro'] === 'VT003') {
  $stmt = $pdo->prepare("
        SELECT pq.ma_vung, vm.ten_vung, pq.ma_loai_kho, lk.ten_loai_kho
        FROM phan_quyen pq
        JOIN vung_mien vm ON pq.ma_vung = vm.ma_vung
        JOIN loai_kho lk ON pq.ma_loai_kho = lk.ma_loai_kho
        WHERE pq.ma_nd = ?
    ");
  $stmt->execute([$ma_nd]);
  $response['phan_quyen'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/* 3. Nếu là THỦ KHO */
if ($user['ma_vai_tro'] === 'VT004') {
  $stmt = $pdo->prepare("
        SELECT k.ma_kho, k.ten_kho, vm.ten_vung, lk.ten_loai_kho
        FROM kho k
        JOIN vung_mien vm ON k.ma_vung = vm.ma_vung
        JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
        WHERE k.ma_nd = ?
    ");
  $stmt->execute([$ma_nd]);
  $response['kho'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

echo json_encode($response);
