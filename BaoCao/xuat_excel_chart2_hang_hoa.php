<?php
// xuat_excel_chart2_hang_hoa.php - Xuất Excel báo cáo biểu đồ hàng hóa
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$chart2_ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
$chart2_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
$chart2_ma_hang = isset($_GET['chart2_ma_hang']) ? $_GET['chart2_ma_hang'] : '';
$chart2_ngay_bat_dau = isset($_GET['chart2_ngay_bat_dau']) ? $_GET['chart2_ngay_bat_dau'] : date('Y-01-01');
$chart2_ngay_ket_thuc = isset($_GET['chart2_ngay_ket_thuc']) ? $_GET['chart2_ngay_ket_thuc'] : date('Y-m-d');

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

// ============ QUERY: BIỂU ĐỒ HÀNG HÓA THEO NGÀY ============
$ngay_hien_tai = strtotime($chart2_ngay_bat_dau);
$ngay_ket_thuc_ts = strtotime($chart2_ngay_ket_thuc);
$danh_sach_ngay = [];

while ($ngay_hien_tai <= $ngay_ket_thuc_ts) {
    $danh_sach_ngay[date('Y-m-d', $ngay_hien_tai)] = [
        'nhap' => 0,
        'xuat' => 0,
        'ton' => 0
    ];
    $ngay_hien_tai = strtotime('+1 day', $ngay_hien_tai);
}

// Lấy dữ liệu nhập theo ngày
$sql_daily_nhap = "
    SELECT 
        DATE(pn.ngay_nhap) as ngay,
        SUM(ct.so_luong_nhap) as so_luong
    FROM phieu_nhap pn
    JOIN kho k ON pn.ma_kho = k.ma_kho
    JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    WHERE pn.ngay_nhap BETWEEN ? AND ?
      AND pn.trang_thai = 'da_xac_nhan'
";

$params_daily = [$chart2_ngay_bat_dau, $chart2_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_daily_nhap .= " " . $kho_condition;
    $params_daily = array_merge($params_daily, $kho_params);
}

if (!empty($chart2_ma_vung)) {
    $sql_daily_nhap .= " AND k.ma_vung = ?";
    $params_daily[] = $chart2_ma_vung;
}

if (!empty($chart2_loai_kho)) {
    $sql_daily_nhap .= " AND k.ma_loai_kho = ?";
    $params_daily[] = $chart2_loai_kho;
}

if (!empty($chart2_ma_hang)) {
    $sql_daily_nhap .= " AND ct.ma_hang = ?";
    $params_daily[] = $chart2_ma_hang;
}

$sql_daily_nhap .= " GROUP BY DATE(pn.ngay_nhap) ORDER BY pn.ngay_nhap";

$stmt_daily = $pdo->prepare($sql_daily_nhap);
$stmt_daily->execute($params_daily);
$daily_nhap = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

// Lấy dữ liệu xuất theo ngày
$sql_daily_xuat = "
    SELECT 
        DATE(px.ngay_xuat) as ngay,
        SUM(ct.so_luong_xuat) as so_luong
    FROM phieu_xuat px
    JOIN kho k ON px.ma_kho = k.ma_kho
    JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    WHERE px.ngay_xuat BETWEEN ? AND ?
      AND px.trang_thai = 'da_xac_nhan'
";

$params_daily_xuat = [$chart2_ngay_bat_dau, $chart2_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_daily_xuat .= " " . $kho_condition;
    $params_daily_xuat = array_merge($params_daily_xuat, $kho_params);
}

if (!empty($chart2_ma_vung)) {
    $sql_daily_xuat .= " AND k.ma_vung = ?";
    $params_daily_xuat[] = $chart2_ma_vung;
}

if (!empty($chart2_loai_kho)) {
    $sql_daily_xuat .= " AND k.ma_loai_kho = ?";
    $params_daily_xuat[] = $chart2_loai_kho;
}

if (!empty($chart2_ma_hang)) {
    $sql_daily_xuat .= " AND ct.ma_hang = ?";
    $params_daily_xuat[] = $chart2_ma_hang;
}

$sql_daily_xuat .= " GROUP BY DATE(px.ngay_xuat) ORDER BY px.ngay_xuat";

$stmt_daily_xuat = $pdo->prepare($sql_daily_xuat);
$stmt_daily_xuat->execute($params_daily_xuat);
$daily_xuat = $stmt_daily_xuat->fetchAll(PDO::FETCH_ASSOC);

// Gộp dữ liệu theo ngày
foreach ($daily_nhap as $row) {
    $ngay = $row['ngay'];
    if (isset($danh_sach_ngay[$ngay])) {
        $danh_sach_ngay[$ngay]['nhap'] = (int)$row['so_luong'];
    }
}

foreach ($daily_xuat as $row) {
    $ngay = $row['ngay'];
    if (isset($danh_sach_ngay[$ngay])) {
        $danh_sach_ngay[$ngay]['xuat'] = (int)$row['so_luong'];
    }
}

// Tính tồn theo ngày
$ton_hien_tai = 0;
foreach ($danh_sach_ngay as &$data) {
    $ton_hien_tai = $ton_hien_tai + $data['nhap'] - $data['xuat'];
    $data['ton'] = $ton_hien_tai;
}

// Lấy thông tin bộ lọc để hiển thị
$ten_vung = '';
if (!empty($chart2_ma_vung)) {
    $stmt_vung = $pdo->prepare("SELECT ten_vung FROM vung_mien WHERE ma_vung = ?");
    $stmt_vung->execute([$chart2_ma_vung]);
    $vung_data = $stmt_vung->fetch(PDO::FETCH_ASSOC);
    $ten_vung = $vung_data['ten_vung'] ?? '';
}

$ten_loai_kho = '';
if (!empty($chart2_loai_kho)) {
    $stmt_lk = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt_lk->execute([$chart2_loai_kho]);
    $lk_data = $stmt_lk->fetch(PDO::FETCH_ASSOC);
    $ten_loai_kho = $lk_data['ten_loai_kho'] ?? '';
}

$ten_hang = '';
if (!empty($chart2_ma_hang)) {
    $stmt_hang = $pdo->prepare("SELECT ten_hang FROM hang_hoa WHERE ma_hang = ?");
    $stmt_hang->execute([$chart2_ma_hang]);
    $hang_data = $stmt_hang->fetch(PDO::FETCH_ASSOC);
    $ten_hang = $hang_data['ten_hang'] ?? '';
}

// Thiết lập header để xuất Excel
$filename = 'bao_cao_bieu_do_hang_hoa_' . date('Y-m-d_H-i-s') . '.xls';
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
    <title>Báo cáo biểu đồ hàng hóa</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .sub-header { font-size: 14px; margin-bottom: 15px; }
    </style>
</head>
<body>
    <div class="header">BÁO CÁO BIỂU ĐỒ BIẾN ĐỘNG THEO HÀNG HÓA</div>
    
    <div class="sub-header">
        <strong>Khoảng thời gian:</strong> <?php echo date('d/m/Y', strtotime($chart2_ngay_bat_dau)) . ' - ' . date('d/m/Y', strtotime($chart2_ngay_ket_thuc)); ?><br>
        <?php if (!empty($ten_vung)): ?>
        <strong>Vùng miền:</strong> <?php echo htmlspecialchars($ten_vung); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_loai_kho)): ?>
        <strong>Loại kho:</strong> <?php echo htmlspecialchars($ten_loai_kho); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_hang)): ?>
        <strong>Hàng hóa:</strong> <?php echo htmlspecialchars($chart2_ma_hang . ' - ' . $ten_hang); ?><br>
        <?php endif; ?>
        <strong>Ngày xuất báo cáo:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="150">Ngày</th>
                <th width="150" class="text-right">Lượng nhập</th>
                <th width="150" class="text-right">Lượng xuất</th>
                <th width="150" class="text-right">Tồn cuối ngày</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_nhap = 0;
            $tong_xuat = 0;
            foreach ($danh_sach_ngay as $ngay => $data): 
                $tong_nhap += $data['nhap'];
                $tong_xuat += $data['xuat'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo date('d/m/Y', strtotime($ngay)); ?></td>
                <td class="text-right"><?php echo number_format($data['nhap']); ?></td>
                <td class="text-right"><?php echo number_format($data['xuat']); ?></td>
                <td class="text-right"><?php echo number_format($data['ton']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="2" class="text-right">TỔNG CỘNG:</td>
                <td class="text-right"><?php echo number_format($tong_nhap); ?></td>
                <td class="text-right"><?php echo number_format($tong_xuat); ?></td>
                <td class="text-right"><?php echo number_format(end($danh_sach_ngay)['ton'] ?? 0); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
<?php exit; ?>
