<?php
include '../include/connect.php';
include '../include/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkAccess('phieunhap');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Không có quyền truy cập']);
    exit;
}

$ma_kho = $_GET['ma_kho'] ?? '';
$ma_hang = $_GET['ma_hang'] ?? '';

if (empty($ma_kho) || empty($ma_hang)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu mã kho hoặc mã hàng']);
    exit;
}

try {
    // Lấy tồn kho hiện tại từ bảng the_kho
    // Tồn kho = giá trị so_luong_ton của dòng cuối cùng
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(so_luong_ton), 0) as so_luong_ton
        FROM the_kho
        WHERE ma_kho = :ma_kho 
          AND ma_hang = :ma_hang
        ORDER BY ngay DESC, so_ct DESC
        LIMIT 1
    ");
    
    $stmt->execute([
        ':ma_kho' => $ma_kho,
        ':ma_hang' => $ma_hang
    ]);
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'ma_kho' => $ma_kho,
        'ma_hang' => $ma_hang,
        'so_luong_ton' => (int)($result['so_luong_ton'] ?? 0)
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
?>
