<?php
include '../include/connect.php';

$loai = $_GET['loai'] ?? ''; // 'nhap' hoặc 'xuat'
$ma_phieu = $_GET['ma'] ?? '';

if (empty($loai) || empty($ma_phieu)) {
    die(json_encode(['error' => 'Thiếu tham số']));
}

try {
    if ($loai === 'nhap') {
        $stmt_phieu = $pdo->prepare("
            SELECT 
                pn.ma_phieu_nhap AS ma_phieu,
                pn.ngay_nhap AS ngay,
                pn.nguoi_giao,
                pn.don_vi_giao,
                pn.loai_nhap,
                k.ten_kho,
                ncc.ten_ncc
            FROM phieu_nhap pn
            LEFT JOIN kho k ON pn.ma_kho = k.ma_kho
            LEFT JOIN nha_cung_cap ncc ON pn.ma_ncc = ncc.ma_ncc
            WHERE pn.ma_phieu_nhap = ?
        ");
        $table_ct = 'ct_phieu_nhap';
        $col_ma_phieu_ct = 'ma_phieu_nhap';
        $col_sl = 'so_luong_nhap';
        $col_dg = 'don_gia';
        $col_tt = 'thanh_tien';
    } else if ($loai === 'xuat') {
        $stmt_phieu = $pdo->prepare("
            SELECT 
                px.ma_phieu_xuat AS ma_phieu,
                px.ngay_xuat AS ngay,
                px.nguoi_nhan,
                px.don_vi_nhan,
                px.loai_xuat AS loai_nhap,
                k.ten_kho,
                dl.ten_dai_ly AS ten_ncc
            FROM phieu_xuat px
            LEFT JOIN kho k ON px.ma_kho = k.ma_kho
            LEFT JOIN dai_ly dl ON px.ma_dai_ly = dl.ma_dai_ly
            WHERE px.ma_phieu_xuat = ?
        ");
        $table_ct = 'ct_phieu_xuat';
        $col_ma_phieu_ct = 'ma_phieu_xuat';
        $col_sl = 'so_luong_xuat';
        $col_dg = 'don_gia_xuat';
        $col_tt = 'thanh_tien';
    } else {
        die(json_encode(['error' => 'Loại phiếu không hợp lệ']));
    }

    $stmt_phieu->execute([$ma_phieu]);
    $phieu = $stmt_phieu->fetch(PDO::FETCH_ASSOC);

    if (!$phieu) {
        die(json_encode(['error' => 'Không tìm thấy phiếu']));
    }

    $stmt_ct = $pdo->prepare("
        SELECT 
            ct.ma_hang,
            hh.ten_hang,
            ct.$col_sl AS so_luong,
            ct.$col_dg AS don_gia,
            ct.$col_tt AS thanh_tien
        FROM $table_ct ct
        LEFT JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
        WHERE ct.$col_ma_phieu_ct = ?
    ");
    $stmt_ct->execute([$ma_phieu]);
    $chi_tiet = $stmt_ct->fetchAll(PDO::FETCH_ASSOC);

    $tong_thanh_tien = array_sum(array_column($chi_tiet, 'thanh_tien'));

    $phieu['ngay'] = (new DateTime($phieu['ngay']))->format('d/m/Y');

    echo json_encode([
        'phieu' => $phieu,
        'chi_tiet' => $chi_tiet,
        'tong_thanh_tien' => $tong_thanh_tien
    ]);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}