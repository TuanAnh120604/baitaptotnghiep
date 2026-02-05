<?php
include '../include/connect.php';
include '../include/permissions.php';

header('Content-Type: application/json; charset=utf-8');

try {
    checkAccess('phieuxuat');
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Không có quyền truy cập']);
    exit;
}

$ma_daily = $_GET['ma_dai_ly'] ?? '';

if (empty($ma_daily)) {
    http_response_code(400);
    echo json_encode(['error' => 'Vui lòng chọn đại lý']);
    exit;
}

try {
    // Lấy danh sách lệnh kho loại Xuất (lệnh xuất cho đại lý)
    $stmt = $pdo->prepare("SELECT DISTINCT lk.MaLenh, lk.NgayLap, lk.LoaiLenh, lk.TrangThai, lk.MaDaily, dl.ten_dai_ly
        FROM lenhkho lk
        LEFT JOIN dai_ly dl ON lk.MaDaily = dl.ma_dai_ly
        WHERE lk.LoaiLenh = 'Xuat' AND lk.MaDaily = :ma_daily
        ORDER BY lk.NgayLap DESC, lk.MaLenh DESC
        LIMIT 100");

    $stmt->execute([':ma_daily' => $ma_daily]);
    $phieu_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $phieu_list
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
