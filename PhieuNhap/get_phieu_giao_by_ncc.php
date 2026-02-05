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

$ma_ncc = $_GET['ma_ncc'] ?? '';

if (empty($ma_ncc)) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng chọn nhà cung cấp']);
    exit;
}

try {
    // Lấy danh sách lệnh kho loại Nhập (phiếu giao hàng) từ nhà cung cấp
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            lk.MaLenh,
            lk.NgayLap,
            lk.LoaiLenh,
            lk.TrangThai,
            ncc.ma_ncc,
            ncc.ten_ncc
        FROM lenhkho lk
        LEFT JOIN nha_cung_cap ncc ON lk.MaNCC = ncc.ma_ncc
        WHERE lk.LoaiLenh = 'Nhap'
            AND lk.MaNCC = :ma_ncc
        ORDER BY lk.NgayLap DESC, lk.MaLenh DESC
        LIMIT 100
    ");
    
    $stmt->execute([':ma_ncc' => $ma_ncc]);
    $phieu_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $phieu_list
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()
    ]);
}
