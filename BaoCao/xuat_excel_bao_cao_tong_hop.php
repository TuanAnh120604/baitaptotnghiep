<?php
// xuat_excel_bao_cao_tong_hop.php - Xuất Excel báo cáo tổng hợp 3 bảng
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$ma_vung = isset($_GET['ma_vung']) ? $_GET['ma_vung'] : '';
$loai_kho = isset($_GET['loai_kho']) ? $_GET['loai_kho'] : '';
$don_vi_tinh = isset($_GET['don_vi_tinh']) ? $_GET['don_vi_tinh'] : '';
$ngay_bat_dau = isset($_GET['ngay_bat_dau']) ? $_GET['ngay_bat_dau'] : date('Y-01-01');
$ngay_ket_thuc = isset($_GET['ngay_ket_thuc']) ? $_GET['ngay_ket_thuc'] : date('Y-m-d');

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001',
    'L002' => 'M002',
    'L003' => 'M003',
    'L004' => 'M004'
];

// Xây dựng điều kiện lọc kho theo quyền
$kho_condition = '';
$kho_params = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_nd = ?';
    $kho_params[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )';
    $kho_params[] = $ma_nd;
}

// ============ BẢNG 1: BIẾN ĐỘNG KHO - NHẬP XUẤT THEO NGÀY THEO ĐVT ============
$ngay_hien_tai = strtotime($ngay_bat_dau);
$ngay_ket_thuc_ts = strtotime($ngay_ket_thuc);
$bang1_data = [];

while ($ngay_hien_tai <= $ngay_ket_thuc_ts) {
    $ngay_str = date('Y-m-d', $ngay_hien_tai);
    $bang1_data[$ngay_str] = [];
    $ngay_hien_tai = strtotime('+1 day', $ngay_hien_tai);
}

// Lấy dữ liệu nhập theo ngày và đơn vị tính
$sql_bang1_nhap = "
    SELECT 
        DATE(pn.ngay_nhap) as ngay,
        hh.don_vi_tinh,
        SUM(ct.so_luong_nhap) as so_luong
    FROM phieu_nhap pn
    JOIN kho k ON pn.ma_kho = k.ma_kho
    JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
    WHERE pn.ngay_nhap BETWEEN ? AND ?
      AND pn.trang_thai = 'da_xac_nhan'
";

$params_bang1_nhap = [$ngay_bat_dau, $ngay_ket_thuc];

if (!empty($ma_vung)) {
    $sql_bang1_nhap .= " AND k.ma_vung = ?";
    $params_bang1_nhap[] = $ma_vung;
}

if (!empty($loai_kho)) {
    $sql_bang1_nhap .= " AND k.ma_loai_kho = ?";
    $params_bang1_nhap[] = $loai_kho;
}

if (!empty($don_vi_tinh)) {
    $sql_bang1_nhap .= " AND hh.don_vi_tinh = ?";
    $params_bang1_nhap[] = $don_vi_tinh;
}

if (!empty($kho_condition)) {
    $sql_bang1_nhap .= " " . $kho_condition;
    $params_bang1_nhap = array_merge($params_bang1_nhap, $kho_params);
}

$sql_bang1_nhap .= " GROUP BY DATE(pn.ngay_nhap), hh.don_vi_tinh ORDER BY DATE(pn.ngay_nhap), hh.don_vi_tinh";

$stmt_bang1_nhap = $pdo->prepare($sql_bang1_nhap);
$stmt_bang1_nhap->execute($params_bang1_nhap);
$data_bang1_nhap = $stmt_bang1_nhap->fetchAll(PDO::FETCH_ASSOC);

// Lấy dữ liệu xuất theo ngày và đơn vị tính
$sql_bang1_xuat = "
    SELECT 
        DATE(px.ngay_xuat) as ngay,
        hh.don_vi_tinh,
        SUM(ct.so_luong_xuat) as so_luong
    FROM phieu_xuat px
    JOIN kho k ON px.ma_kho = k.ma_kho
    JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
    WHERE px.ngay_xuat BETWEEN ? AND ?
      AND px.trang_thai = 'da_xac_nhan'
";

$params_bang1_xuat = [$ngay_bat_dau, $ngay_ket_thuc];

if (!empty($ma_vung)) {
    $sql_bang1_xuat .= " AND k.ma_vung = ?";
    $params_bang1_xuat[] = $ma_vung;
}

if (!empty($loai_kho)) {
    $sql_bang1_xuat .= " AND k.ma_loai_kho = ?";
    $params_bang1_xuat[] = $loai_kho;
}

if (!empty($don_vi_tinh)) {
    $sql_bang1_xuat .= " AND hh.don_vi_tinh = ?";
    $params_bang1_xuat[] = $don_vi_tinh;
}

if (!empty($kho_condition)) {
    $sql_bang1_xuat .= " " . $kho_condition;
    $params_bang1_xuat = array_merge($params_bang1_xuat, $kho_params);
}

$sql_bang1_xuat .= " GROUP BY DATE(px.ngay_xuat), hh.don_vi_tinh ORDER BY DATE(px.ngay_xuat), hh.don_vi_tinh";

$stmt_bang1_xuat = $pdo->prepare($sql_bang1_xuat);
$stmt_bang1_xuat->execute($params_bang1_xuat);
$data_bang1_xuat = $stmt_bang1_xuat->fetchAll(PDO::FETCH_ASSOC);

// Gộp dữ liệu bảng 1
$bang1_final = [];
foreach ($data_bang1_nhap as $row) {
    $key = $row['ngay'] . '|' . $row['don_vi_tinh'];
    if (!isset($bang1_final[$key])) {
        $bang1_final[$key] = [
            'ngay' => $row['ngay'],
            'don_vi_tinh' => $row['don_vi_tinh'],
            'nhap' => 0,
            'xuat' => 0
        ];
    }
    $bang1_final[$key]['nhap'] += (int)$row['so_luong'];
}

foreach ($data_bang1_xuat as $row) {
    $key = $row['ngay'] . '|' . $row['don_vi_tinh'];
    if (!isset($bang1_final[$key])) {
        $bang1_final[$key] = [
            'ngay' => $row['ngay'],
            'don_vi_tinh' => $row['don_vi_tinh'],
            'nhap' => 0,
            'xuat' => 0
        ];
    }
    $bang1_final[$key]['xuat'] += (int)$row['so_luong'];
}

// Sắp xếp bảng 1 theo ngày
ksort($bang1_final);

// ============ BẢNG 2: BIẾN ĐỘNG HÀNG HÓA - NHẬP XUẤT, TỒN ĐẦU, TỒN CUỐI ============
$sql_bang2 = "
    SELECT 
        hh.ma_hang,
        hh.ten_hang,
        hh.don_vi_tinh,
        lk.ten_loai_kho,
        COALESCE((
            SELECT COALESCE(so_luong_ton, 0) 
            FROM the_kho 
            WHERE ma_hang = hh.ma_hang 
            AND ma_kho = k.ma_kho
            AND ngay < ? 
            ORDER BY ngay DESC, so_ct DESC 
            LIMIT 1
        ), 0) as ton_dau_ky,
        COALESCE((
            SELECT SUM(ct.so_luong_nhap) 
            FROM ct_phieu_nhap ct
            JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
            WHERE ct.ma_hang = hh.ma_hang 
            AND pn.ma_kho = k.ma_kho
            AND pn.ngay_nhap >= ? 
            AND pn.ngay_nhap <= ?
                AND pn.trang_thai = 'da_xac_nhan'
        ), 0) as luong_nhap,
        COALESCE((
            SELECT SUM(ct.so_luong_xuat) 
            FROM ct_phieu_xuat ct
            JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
            WHERE ct.ma_hang = hh.ma_hang 
            AND px.ma_kho = k.ma_kho
            AND px.ngay_xuat >= ? 
            AND px.ngay_xuat <= ?
                AND px.trang_thai = 'da_xac_nhan'
        ), 0) as luong_xuat
    FROM (
        SELECT DISTINCT ma_hang, ma_kho FROM the_kho
    ) tk
    JOIN hang_hoa hh ON tk.ma_hang = hh.ma_hang
    JOIN kho k ON tk.ma_kho = k.ma_kho
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    WHERE 1=1
";

$params_bang2 = array_merge([$ngay_bat_dau, $ngay_bat_dau, $ngay_ket_thuc, $ngay_bat_dau, $ngay_ket_thuc], $kho_params);

if (!empty($ma_vung)) {
    $sql_bang2 .= " AND k.ma_vung = ?";
    $params_bang2[] = $ma_vung;
}

if (!empty($loai_kho)) {
    $sql_bang2 .= " AND k.ma_loai_kho = ?";
    $params_bang2[] = $loai_kho;
}

if (!empty($don_vi_tinh)) {
    $sql_bang2 .= " AND hh.don_vi_tinh = ?";
    $params_bang2[] = $don_vi_tinh;
}

if (!empty($kho_condition)) {
    $sql_bang2 .= " " . $kho_condition;
}

$sql_bang2 .= " GROUP BY hh.ma_hang, k.ma_kho ORDER BY lk.ten_loai_kho, hh.ten_hang";

$stmt_bang2 = $pdo->prepare($sql_bang2);
$stmt_bang2->execute($params_bang2);
$bang2_data = $stmt_bang2->fetchAll(PDO::FETCH_ASSOC);

// Tính tồn cuối kỳ cho bảng 2
foreach ($bang2_data as &$row) {
    $row['ton_cuoi_ky'] = (int)$row['ton_dau_ky'] + (int)$row['luong_nhap'] - (int)$row['luong_xuat'];
}

// ============ BẢNG 3: BẢNG CÂN ĐỐI KHO - CHI TIẾT THEO TỪNG KHO VÀ TỪNG MẶT HÀNG ============
$sql_bang3 = "
    SELECT 
        k.ma_kho,
        k.ten_kho,
        lk.ten_loai_kho,
        hh.ma_hang,
        hh.ten_hang,
        hh.don_vi_tinh,
        COALESCE((
            SELECT COALESCE(so_luong_ton, 0) 
            FROM the_kho 
            WHERE ma_hang = hh.ma_hang 
            AND ma_kho = k.ma_kho
            AND ngay < ? 
            ORDER BY ngay DESC, so_ct DESC 
            LIMIT 1
        ), 0) as ton_dau_ky,
        COALESCE((
            SELECT SUM(ct.so_luong_nhap) 
            FROM ct_phieu_nhap ct
            JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
            WHERE ct.ma_hang = hh.ma_hang 
            AND pn.ma_kho = k.ma_kho
            AND pn.ngay_nhap >= ? 
            AND pn.ngay_nhap <= ?
                AND pn.trang_thai = 'da_xac_nhan'
        ), 0) as luong_nhap,
        COALESCE((
            SELECT SUM(ct.so_luong_xuat) 
            FROM ct_phieu_xuat ct
            JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
            WHERE ct.ma_hang = hh.ma_hang 
            AND px.ma_kho = k.ma_kho
            AND px.ngay_xuat >= ? 
            AND px.ngay_xuat <= ?
                AND px.trang_thai = 'da_xac_nhan'
        ), 0) as luong_xuat
    FROM (
        SELECT DISTINCT ma_hang, ma_kho FROM the_kho
    ) tk
    JOIN hang_hoa hh ON tk.ma_hang = hh.ma_hang
    JOIN kho k ON tk.ma_kho = k.ma_kho
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    WHERE 1=1
";

$params_bang3 = array_merge([$ngay_bat_dau, $ngay_bat_dau, $ngay_ket_thuc, $ngay_bat_dau, $ngay_ket_thuc], $kho_params);

if (!empty($ma_vung)) {
    $sql_bang3 .= " AND k.ma_vung = ?";
    $params_bang3[] = $ma_vung;
}

if (!empty($loai_kho)) {
    $sql_bang3 .= " AND k.ma_loai_kho = ?";
    $params_bang3[] = $loai_kho;
}

if (!empty($don_vi_tinh)) {
    $sql_bang3 .= " AND hh.don_vi_tinh = ?";
    $params_bang3[] = $don_vi_tinh;
}

if (!empty($kho_condition)) {
    $sql_bang3 .= " " . $kho_condition;
}

$sql_bang3 .= " GROUP BY hh.ma_hang, k.ma_kho ORDER BY k.ma_kho, hh.ten_hang";

$stmt_bang3 = $pdo->prepare($sql_bang3);
$stmt_bang3->execute($params_bang3);
$bang3_data = $stmt_bang3->fetchAll(PDO::FETCH_ASSOC);

// Tính tồn cuối kỳ cho bảng 3
foreach ($bang3_data as &$row) {
    $row['ton_cuoi_ky'] = (int)$row['ton_dau_ky'] + (int)$row['luong_nhap'] - (int)$row['luong_xuat'];
}

// Lấy thông tin bộ lọc để hiển thị
$ten_vung = '';
if (!empty($ma_vung)) {
    $stmt_vung = $pdo->prepare("SELECT ten_vung FROM vung_mien WHERE ma_vung = ?");
    $stmt_vung->execute([$ma_vung]);
    $vung_data = $stmt_vung->fetch(PDO::FETCH_ASSOC);
    $ten_vung = $vung_data['ten_vung'] ?? '';
}

$ten_loai_kho = '';
if (!empty($loai_kho)) {
    $stmt_lk = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt_lk->execute([$loai_kho]);
    $lk_data = $stmt_lk->fetch(PDO::FETCH_ASSOC);
    $ten_loai_kho = $lk_data['ten_loai_kho'] ?? '';
}

// Thiết lập header để xuất Excel
$filename = 'bao_cao_tong_hop_' . date('Y-m-d_H-i-s') . '.xls';
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');
header('Content-Transfer-Encoding: binary');

// Đảm bảo encoding UTF-8 với BOM
echo "\xEF\xBB\xBF";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Báo cáo tổng hợp</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; margin-bottom: 30px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .sub-header { font-size: 14px; margin-bottom: 15px; }
        .table-title { font-size: 16px; font-weight: bold; margin-top: 30px; margin-bottom: 15px; color: #0066cc; }
        .page-break { page-break-after: always; }
    </style>
</head>
<body>
    <div class="header">BÁO CÁO TỔNG HỢP BIẾN ĐỘNG KHO HÀNG</div>
    
    <div class="sub-header">
        <strong>Khoảng thời gian:</strong> <?php echo date('d/m/Y', strtotime($ngay_bat_dau)) . ' - ' . date('d/m/Y', strtotime($ngay_ket_thuc)); ?><br>
        <?php if (!empty($ten_vung)): ?>
        <strong>Vùng miền:</strong> <?php echo htmlspecialchars($ten_vung); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_loai_kho)): ?>
        <strong>Loại kho:</strong> <?php echo htmlspecialchars($ten_loai_kho); ?><br>
        <?php endif; ?>
        <?php if (!empty($don_vi_tinh)): ?>
        <strong>Đơn vị tính:</strong> <?php echo htmlspecialchars($don_vi_tinh); ?><br>
        <?php endif; ?>
        <strong>Ngày xuất báo cáo:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>

    <!-- BẢNG 1: BIẾN ĐỘNG KHO - NHẬP XUẤT THEO NGÀY THEO ĐVT -->
    <div class="table-title">BẢNG 1: BIẾN ĐỘNG KHO - TÌNH HÌNH NHẬP XUẤT THEO NGÀY THEO ĐƠN VỊ TÍNH</div>
    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="120">Ngày</th>
                <th width="100">Đơn vị tính</th>
                <th width="150" class="text-right">Tổng lượng nhập</th>
                <th width="150" class="text-right">Tổng lượng xuất</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_nhap_bang1 = 0;
            $tong_xuat_bang1 = 0;
            foreach ($bang1_final as $row): 
                $tong_nhap_bang1 += $row['nhap'];
                $tong_xuat_bang1 += $row['xuat'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo date('d/m/Y', strtotime($row['ngay'])); ?></td>
                <td><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                <td class="text-right"><?php echo number_format($row['nhap']); ?></td>
                <td class="text-right"><?php echo number_format($row['xuat']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bang1_final)): ?>
            <tr>
                <td colspan="5" class="text-center">Không có dữ liệu</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="3" class="text-right">TỔNG CỘNG:</td>
                <td class="text-right"><?php echo number_format($tong_nhap_bang1); ?></td>
                <td class="text-right"><?php echo number_format($tong_xuat_bang1); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- BẢNG 2: BIẾN ĐỘNG HÀNG HÓA -->
    <div class="table-title">BẢNG 2: BIẾN ĐỘNG HÀNG HÓA CỦA LOẠI KHO - TÌNH HÌNH NHẬP XUẤT, TỒN ĐẦU, TỒN CUỐI</div>
    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="100">Mã hàng</th>
                <th width="200">Tên hàng</th>
                <th width="80">Đơn vị tính</th>
                <th width="120">Loại kho</th>
                <th width="100" class="text-right">Tồn đầu kỳ</th>
                <th width="100" class="text-right">Lượng nhập</th>
                <th width="100" class="text-right">Lượng xuất</th>
                <th width="100" class="text-right">Tồn cuối kỳ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_ton_dau_bang2 = 0;
            $tong_nhap_bang2 = 0;
            $tong_xuat_bang2 = 0;
            $tong_ton_cuoi_bang2 = 0;
            foreach ($bang2_data as $row): 
                $tong_ton_dau_bang2 += (int)$row['ton_dau_ky'];
                $tong_nhap_bang2 += (int)$row['luong_nhap'];
                $tong_xuat_bang2 += (int)$row['luong_xuat'];
                $tong_ton_cuoi_bang2 += (int)$row['ton_cuoi_ky'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo htmlspecialchars($row['ma_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_loai_kho']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_dau_ky']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_nhap']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_xuat']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_cuoi_ky']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bang2_data)): ?>
            <tr>
                <td colspan="9" class="text-center">Không có dữ liệu</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="5" class="text-right">TỔNG CỘNG:</td>
                <td class="text-right"><?php echo number_format($tong_ton_dau_bang2); ?></td>
                <td class="text-right"><?php echo number_format($tong_nhap_bang2); ?></td>
                <td class="text-right"><?php echo number_format($tong_xuat_bang2); ?></td>
                <td class="text-right"><?php echo number_format($tong_ton_cuoi_bang2); ?></td>
            </tr>
        </tfoot>
    </table>

    <!-- BẢNG 3: BẢNG CÂN ĐỐI KHO - CHI TIẾT THEO TỪNG KHO VÀ TỪNG MẶT HÀNG -->
    <div class="table-title">BẢNG 3: BẢNG CÂN ĐỐI KHO - CHI TIẾT THEO TỪNG KHO VÀ TỪNG MẶT HÀNG</div>
    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="100">Mã kho</th>
                <th width="150">Tên kho</th>
                <th width="120">Loại kho</th>
                <th width="100">Mã hàng</th>
                <th width="200">Tên hàng</th>
                <th width="80">Đơn vị tính</th>
                <th width="100" class="text-right">Tồn đầu kỳ</th>
                <th width="100" class="text-right">Lượng nhập</th>
                <th width="100" class="text-right">Lượng xuất</th>
                <th width="100" class="text-right">Tồn cuối kỳ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_ton_dau_bang3 = 0;
            $tong_nhap_bang3 = 0;
            $tong_xuat_bang3 = 0;
            $tong_ton_cuoi_bang3 = 0;
            foreach ($bang3_data as $row): 
                $tong_ton_dau_bang3 += (int)$row['ton_dau_ky'];
                $tong_nhap_bang3 += (int)$row['luong_nhap'];
                $tong_xuat_bang3 += (int)$row['luong_xuat'];
                $tong_ton_cuoi_bang3 += (int)$row['ton_cuoi_ky'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo htmlspecialchars($row['ma_kho']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_kho']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_loai_kho']); ?></td>
                <td><?php echo htmlspecialchars($row['ma_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_dau_ky']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_nhap']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_xuat']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_cuoi_ky']); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($bang3_data)): ?>
            <tr>
                <td colspan="11" class="text-center">Không có dữ liệu</td>
            </tr>
            <?php endif; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="7" class="text-right">TỔNG CỘNG:</td>
                <td class="text-right"><?php echo number_format($tong_ton_dau_bang3); ?></td>
                <td class="text-right"><?php echo number_format($tong_nhap_bang3); ?></td>
                <td class="text-right"><?php echo number_format($tong_xuat_bang3); ?></td>
                <td class="text-right"><?php echo number_format($tong_ton_cuoi_bang3); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
<?php exit; ?>
