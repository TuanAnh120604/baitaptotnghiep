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
$so_luong_nhap = (int)($input['so_luong_nhap'] ?? 0);

if (!$ma_kho || !$ma_hang || $so_luong_nhap <= 0) {
    echo json_encode(['error' => 'Thiếu thông tin kiểm tra']);
    exit;
}

try {
    // Kiểm tra tồn kho hiện tại và mức dự trữ max
    $stmt = $pdo->prepare("
        SELECT tk.so_luong_ton, h.muc_du_tru_max, h.ten_hang
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
    $muc_du_tru_max = (int)$ton_kho['muc_du_tru_max'];
    $ten_hang = $ton_kho['ten_hang'];

    // Tính toán số lượng tối đa có thể nhập
    $so_luong_co_the_nhap_them = $muc_du_tru_max - $so_luong_ton_hien_tai;

    $response = [
        'ma_hang' => $ma_hang,
        'ten_hang' => $ten_hang,
        'so_luong_ton' => $so_luong_ton_hien_tai,
        'so_luong_nhap' => $so_luong_nhap,
        'muc_du_tru_max' => $muc_du_tru_max,
        'so_luong_co_the_nhap' => max(0, $so_luong_co_the_nhap_them)
    ];

    // Kiểm tra số lượng nhập
    $so_luong_sau_nhap = $so_luong_ton_hien_tai + $so_luong_nhap;
    
    if ($muc_du_tru_max > 0) {
        if ($so_luong_sau_nhap > $muc_du_tru_max) {
            // Vượt quá max - warning
            $phan_vuot = $so_luong_sau_nhap - $muc_du_tru_max;
            $response['warning'] = "Mặt hàng '{$ten_hang}' hiện có {$so_luong_ton_hien_tai} cái. Mức dự trữ tối đa là {$muc_du_tru_max} cái. Bạn chỉ có thể nhập thêm tối đa {$so_luong_co_the_nhap_them} cái, nhưng đang nhập {$so_luong_nhap} cái (vượt {$phan_vuot} cái).";
        }
    }

    // Debug: thêm thông tin để check
    error_log("API kiem_tra_muc_du_tru: ma_hang={$ma_hang}, so_luong_nhap={$so_luong_nhap}, so_luong_ton={$so_luong_ton_hien_tai}, muc_du_tru_max={$muc_du_tru_max}, so_luong_sau={$so_luong_sau_nhap}, co_the_nhap_them={$so_luong_co_the_nhap_them}, has_warning=" . (isset($response['warning']) ? 'yes' : 'no'));

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => 'Lỗi kiểm tra mức dự trữ: ' . $e->getMessage()]);
}
?>
