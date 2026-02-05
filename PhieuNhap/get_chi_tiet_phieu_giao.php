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

$ma_lenh = $_GET['ma_phieu'] ?? '';

if (empty($ma_lenh)) {
    http_response_code(400);
    echo json_encode(['error' => 'Thiếu mã lệnh kho']);
    exit;
}

try {
    // Lấy thông tin lệnh kho (phiếu giao hàng)
    $stmt_phieu = $pdo->prepare("
        SELECT 
            lk.MaLenh,
            lk.NgayLap,
            lk.LoaiLenh,
            lk.TrangThai,
            lk.MaNCC,
            lk.MaDaily,
            ncc.ten_ncc,
            dl.ten_dai_ly
        FROM lenhkho lk
        LEFT JOIN nha_cung_cap ncc ON lk.MaNCC = ncc.ma_ncc
        LEFT JOIN dai_ly dl ON lk.MaDaily = dl.ma_dai_ly
        WHERE lk.MaLenh = ?
    ");
    
    $stmt_phieu->execute([$ma_lenh]);
    $phieu = $stmt_phieu->fetch(PDO::FETCH_ASSOC);

    if (!$phieu) {
        http_response_code(404);
        echo json_encode(['error' => 'Không tìm thấy lệnh kho']);
        exit;
    }

    // Lấy chi tiết lệnh kho (chi tiết hàng hóa)
    $stmt_ct = $pdo->prepare("
        SELECT 
            ct.MaHang,
            hh.ten_hang,
            ct.SoLuong,
            hh.don_gia
        FROM ct_lenhkho ct
        LEFT JOIN hang_hoa hh ON ct.MaHang = hh.ma_hang
        WHERE ct.MaLenh = ?
        ORDER BY hh.ten_hang
    ");
    
    $stmt_ct->execute([$ma_lenh]);
    $chi_tiet = $stmt_ct->fetchAll(PDO::FETCH_ASSOC);

    // Tính tổng tiền
    $tong_thanh_tien = 0;
    foreach ($chi_tiet as $ct) {
        $tong_thanh_tien += ($ct['SoLuong'] * $ct['don_gia']);
    }

    echo json_encode([
        'success' => true,
        'phieu' => $phieu,
        'chi_tiet' => $chi_tiet,
        'tong_thanh_tien' => $tong_thanh_tien
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Lỗi cơ sở dữ liệu: ' . $e->getMessage()]);
}
