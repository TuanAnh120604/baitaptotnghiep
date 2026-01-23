<?php
include '../include/connect.php';

// Lấy các tham số lọc
$ma_vung = isset($_GET['ma_vung']) ? $_GET['ma_vung'] : '';
$loai_kho = isset($_GET['loai_kho']) ? $_GET['loai_kho'] : '';
$don_vi_tinh = isset($_GET['don_vi_tinh']) ? $_GET['don_vi_tinh'] : '';
$ma_hang = isset($_GET['ma_hang']) ? $_GET['ma_hang'] : '';
$ngay_bat_dau = isset($_GET['ngay_bat_dau']) ? $_GET['ngay_bat_dau'] : date('Y-01-01');
$ngay_ket_thuc = isset($_GET['ngay_ket_thuc']) ? $_GET['ngay_ket_thuc'] : date('Y-m-d');

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001', // Kho nguyên liệu -> Nguyên liệu
    'L002' => 'M002', // Kho nhiên liệu -> Nhiên liệu
    'L003' => 'M003', // Kho phụ tùng -> Phụ tùng
    'L004' => 'M004'  // Kho thành phẩm -> Thành phẩm
];

// Xây dựng query để lấy dữ liệu bảng cân đối kho
$sql = "
    SELECT 
        hh.ma_hang,
        hh.ten_hang,
        hh.don_vi_tinh,
        lk.ten_loai_kho,
        k.ten_kho,
        k.ma_kho,
        COALESCE((
            SELECT COALESCE(so_luong_ton, 0) 
            FROM the_kho 
            WHERE ma_hang = hh.ma_hang 
            AND ngay < ? 
            AND ma_kho = k.ma_kho 
            ORDER BY ngay DESC, so_ct DESC 
            LIMIT 1
        ), 0) as ton_dau_ky,
        COALESCE((
            SELECT SUM(so_luong_nhap) 
            FROM ct_phieu_nhap 
            JOIN phieu_nhap ON ct_phieu_nhap.ma_phieu_nhap = phieu_nhap.ma_phieu_nhap
            WHERE ct_phieu_nhap.ma_hang = hh.ma_hang 
            AND phieu_nhap.ma_kho = k.ma_kho
            AND phieu_nhap.ngay_nhap >= ? 
            AND phieu_nhap.ngay_nhap <= ?
        ), 0) as tong_nhap,
        COALESCE((
            SELECT SUM(so_luong_xuat) 
            FROM ct_phieu_xuat 
            JOIN phieu_xuat ON ct_phieu_xuat.ma_phieu_xuat = phieu_xuat.ma_phieu_xuat
            WHERE ct_phieu_xuat.ma_hang = hh.ma_hang 
            AND phieu_xuat.ma_kho = k.ma_kho
            AND phieu_xuat.ngay_xuat >= ? 
            AND phieu_xuat.ngay_xuat <= ?
        ), 0) as tong_xuat
    FROM hang_hoa hh
    CROSS JOIN kho k
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    WHERE 1=1
";

$params = [$ngay_bat_dau, $ngay_bat_dau, $ngay_ket_thuc, $ngay_bat_dau, $ngay_ket_thuc];

if (!empty($ma_vung)) {
    $sql .= " AND k.ma_vung = ?";
    $params[] = $ma_vung;
}

if (!empty($loai_kho)) {
    $sql .= " AND k.ma_loai_kho = ?";
    $params[] = $loai_kho;
    
    // Nếu chọn loại kho, chỉ lấy hàng hóa tương ứng
    if (isset($mapping_loai_kho_hang[$loai_kho])) {
        $sql .= " AND hh.ma_loai_hang = ?";
        $params[] = $mapping_loai_kho_hang[$loai_kho];
    }
}

if (!empty($don_vi_tinh)) {
    $sql .= " AND hh.don_vi_tinh = ?";
    $params[] = $don_vi_tinh;
}

if (!empty($ma_hang)) {
    $sql .= " AND hh.ma_hang = ?";
    $params[] = $ma_hang;
}

$sql .= " ORDER BY k.ma_kho, hh.ma_hang";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ket_qua = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Tính toán dữ liệu tồn cuối kỳ
foreach ($ket_qua as &$row) {
    $row['ton_cuoi_ky'] = (int)$row['ton_dau_ky'] + (int)$row['tong_nhap'] - (int)$row['tong_xuat'];
}

// Tạo file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="baocao_bancandoi_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');

// BOM cho UTF-8
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Tiêu đề báo cáo
fputcsv($output, ['BÁO CÁO BẢNG CÂN ĐỐI KHO'], ';');
fputcsv($output, ['Khoảng thời gian: ' . date('d/m/Y', strtotime($ngay_bat_dau)) . ' - ' . date('d/m/Y', strtotime($ngay_ket_thuc))], ';');
fputcsv($output, [], ';');

// Headers
fputcsv($output, [
    'Mã hàng',
    'Tên hàng',
    'Đơn vị tính',
    'Loại kho',
    'Tên kho',
    'Tồn đầu kỳ',
    'Lượng nhập',
    'Lượng xuất',
    'Tồn cuối kỳ'
], ';');

// Dữ liệu
$tong_ton_dau = 0;
$tong_nhap = 0;
$tong_xuat = 0;
$tong_ton_cuoi = 0;

foreach ($ket_qua as $row) {
    $ton_dau = (int)$row['ton_dau_ky'];
    $nhap = (int)$row['tong_nhap'];
    $xuat = (int)$row['tong_xuat'];
    $ton_cuoi = (int)$row['ton_cuoi_ky'];
    
    $tong_ton_dau += $ton_dau;
    $tong_nhap += $nhap;
    $tong_xuat += $xuat;
    $tong_ton_cuoi += $ton_cuoi;
    
    fputcsv($output, [
        $row['ma_hang'],
        $row['ten_hang'],
        $row['don_vi_tinh'],
        $row['ten_loai_kho'],
        $row['ten_kho'],
        $ton_dau,
        $nhap,
        $xuat,
        $ton_cuoi
    ], ';');
}

// Tổng cộng
fputcsv($output, [], ';');
fputcsv($output, [
    '',
    '',
    '',
    '',
    'TỔNG CỘNG:',
    $tong_ton_dau,
    $tong_nhap,
    $tong_xuat,
    $tong_ton_cuoi
], ';');

fclose($output);
exit;
?>
