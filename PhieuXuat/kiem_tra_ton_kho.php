<?php
include '../include/connect.php';
include '../include/permissions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Phương thức không hợp lệ']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['error' => 'Dữ liệu không hợp lệ']);
    exit;
}

$ma_kho = $input['ma_kho'] ?? null;
$ma_hang = $input['ma_hang'] ?? null;
$so_luong_xuat = (int)($input['so_luong_xuat'] ?? 0);

if (!$ma_kho || !$ma_hang || $so_luong_xuat <= 0) {
    echo json_encode(['error' => 'Thiếu thông tin kiểm tra']);
    exit;
}

try {
    // Kiểm tra tồn kho
    $stmt = $pdo->prepare("
        SELECT tk.so_luong_ton, h.muc_du_tru_min, h.ten_hang
        FROM the_kho tk
        JOIN hang_hoa h ON tk.ma_hang = h.ma_hang
        WHERE tk.ma_kho = ? AND tk.ma_hang = ?
        ORDER BY tk.ngay DESC, tk.ma_the_kho DESC
        LIMIT 1
    ");
    $stmt->execute([$ma_kho, $ma_hang]);
    $ton_kho = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ton_kho) {
        echo json_encode(['error' => 'Không tìm thấy thông tin tồn kho']);
        exit;
    }

    $so_luong_ton_hien_tai = (int)$ton_kho['so_luong_ton'];
    $muc_du_tru_min = (int)$ton_kho['muc_du_tru_min'];
    $ten_hang = $ton_kho['ten_hang'];

    $response = [
        'ma_hang' => $ma_hang,
        'ten_hang' => $ten_hang,
        'so_luong_ton' => $so_luong_ton_hien_tai,
        'so_luong_xuat' => $so_luong_xuat,
        'muc_du_tru_min' => $muc_du_tru_min
    ];

    // Kiểm tra số lượng tồn kho
    if ($so_luong_ton_hien_tai <= 0) {
        $response['error'] = "Mặt hàng '{$ten_hang}' hiện không còn tồn kho (còn: {$so_luong_ton_hien_tai}).";
    } elseif ($so_luong_xuat > $so_luong_ton_hien_tai) {
        $response['error'] = "Mặt hàng '{$ten_hang}' chỉ còn {$so_luong_ton_hien_tai} trong kho. Không thể xuất {$so_luong_xuat}.";
    } else {
        // Kiểm tra mức tối thiểu
        $so_luong_sau_xuat = $so_luong_ton_hien_tai - $so_luong_xuat;
        if ($so_luong_sau_xuat < $muc_du_tru_min && $muc_du_tru_min > 0) {
            $response['warning'] = "Sau khi xuất, mặt hàng '{$ten_hang}' sẽ còn {$so_luong_sau_xuat} (dưới mức dự trữ tối thiểu {$muc_du_tru_min}).";
        }
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Lỗi kiểm tra tồn kho: ' . $e->getMessage()]);
}
?>