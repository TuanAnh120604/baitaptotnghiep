<?php
// xuat_excel_chart1_loai_kho.php - Xuất Excel báo cáo biểu đồ loại kho
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$chart1_ma_vung = isset($_GET['chart1_ma_vung']) ? $_GET['chart1_ma_vung'] : '';
$chart1_loai_kho = isset($_GET['chart1_loai_kho']) ? $_GET['chart1_loai_kho'] : '';
$chart1_don_vi_tinh = isset($_GET['chart1_don_vi_tinh']) ? $_GET['chart1_don_vi_tinh'] : '';
$chart1_ngay_bat_dau = isset($_GET['chart1_ngay_bat_dau']) ? $_GET['chart1_ngay_bat_dau'] : date('Y-01-01');
$chart1_ngay_ket_thuc = isset($_GET['chart1_ngay_ket_thuc']) ? $_GET['chart1_ngay_ket_thuc'] : date('Y-m-d');

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001',
    'L002' => 'M002',
    'L003' => 'M003',
    'L004' => 'M004'
];

// Lấy danh sách loại kho
$stmt_loai_kho = $pdo->query("SELECT * FROM loai_kho ORDER BY ma_loai_kho");
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

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

// ============ QUERY: BIỂU ĐỒ BIẾN ĐỘNG THEO LOẠI KHO ============
$sql_bieu_do_loai_kho = "
    SELECT 
        lk.ten_loai_kho,
        COALESCE(SUM(ct.so_luong_nhap), 0) as tong_nhap
    FROM kho k
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    LEFT JOIN phieu_nhap pn ON k.ma_kho = pn.ma_kho 
        AND pn.ngay_nhap BETWEEN ? AND ?
        AND pn.trang_thai = 'da_xac_nhan'
    LEFT JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    LEFT JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
    WHERE 1=1
";

$params_bieu_do_loai_kho = [$chart1_ngay_bat_dau, $chart1_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_bieu_do_loai_kho .= " " . $kho_condition;
    $params_bieu_do_loai_kho = array_merge($params_bieu_do_loai_kho, $kho_params);
}

if (!empty($chart1_ma_vung)) {
    $sql_bieu_do_loai_kho .= " AND k.ma_vung = ?";
    $params_bieu_do_loai_kho[] = $chart1_ma_vung;
}

if (!empty($chart1_loai_kho)) {
    $sql_bieu_do_loai_kho .= " AND k.ma_loai_kho = ?";
    $params_bieu_do_loai_kho[] = $chart1_loai_kho;
}

if (!empty($chart1_don_vi_tinh)) {
    $sql_bieu_do_loai_kho .= " AND hh.don_vi_tinh = ?";
    $params_bieu_do_loai_kho[] = $chart1_don_vi_tinh;
}

$sql_bieu_do_loai_kho .= " GROUP BY lk.ten_loai_kho ORDER BY lk.ma_loai_kho";

$stmt_bieu_do = $pdo->prepare($sql_bieu_do_loai_kho);
$stmt_bieu_do->execute($params_bieu_do_loai_kho);
$data_bieu_do_loai_kho_temp = $stmt_bieu_do->fetchAll(PDO::FETCH_ASSOC);

// ============ QUERY: TỔNG XUẤT THEO LOẠI KHO ============
$sql_bieu_do_xuat_loai_kho = "
    SELECT
        lk.ten_loai_kho,
        COALESCE(SUM(ct.so_luong_xuat), 0) as tong_xuat
    FROM kho k
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    LEFT JOIN phieu_xuat px ON k.ma_kho = px.ma_kho 
        AND px.ngay_xuat BETWEEN ? AND ?
        AND px.trang_thai = 'da_xac_nhan'
    LEFT JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    LEFT JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
    WHERE 1=1
";

$params_bieu_do_xuat = [$chart1_ngay_bat_dau, $chart1_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_bieu_do_xuat_loai_kho .= " " . $kho_condition;
    $params_bieu_do_xuat = array_merge($params_bieu_do_xuat, $kho_params);
}

if (!empty($chart1_ma_vung)) {
    $sql_bieu_do_xuat_loai_kho .= " AND k.ma_vung = ?";
    $params_bieu_do_xuat[] = $chart1_ma_vung;
}

if (!empty($chart1_loai_kho)) {
    $sql_bieu_do_xuat_loai_kho .= " AND k.ma_loai_kho = ?";
    $params_bieu_do_xuat[] = $chart1_loai_kho;
}

if (!empty($chart1_don_vi_tinh)) {
    $sql_bieu_do_xuat_loai_kho .= " AND hh.don_vi_tinh = ?";
    $params_bieu_do_xuat[] = $chart1_don_vi_tinh;
}

$sql_bieu_do_xuat_loai_kho .= " GROUP BY lk.ten_loai_kho ORDER BY lk.ma_loai_kho";

$stmt_bieu_do_xuat = $pdo->prepare($sql_bieu_do_xuat_loai_kho);
$stmt_bieu_do_xuat->execute($params_bieu_do_xuat);
$data_bieu_do_xuat_temp = $stmt_bieu_do_xuat->fetchAll(PDO::FETCH_ASSOC);

// ============ QUERY: TỒN CUỐI KỲ THEO LOẠI KHO ============
$sql_bieu_do_ton_loai_kho = "
    SELECT
        lk.ten_loai_kho,
        COALESCE(SUM(tk.so_luong_ton), 0) as ton_cuoi_ky
    FROM the_kho tk
    JOIN kho k ON tk.ma_kho = k.ma_kho
    JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho
    JOIN hang_hoa hh ON tk.ma_hang = hh.ma_hang
    WHERE tk.ngay <= ?
      AND NOT EXISTS (
        SELECT 1
        FROM the_kho tk2
        WHERE tk2.ma_kho = tk.ma_kho
          AND tk2.ma_hang = tk.ma_hang
          AND tk2.ngay <= ?
          AND (
            tk2.ngay > tk.ngay
            OR (tk2.ngay = tk.ngay AND tk2.ma_the_kho > tk.ma_the_kho)
          )
      )
";

$params_bieu_do_ton = [$chart1_ngay_ket_thuc, $chart1_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_bieu_do_ton_loai_kho .= " " . $kho_condition;
    $params_bieu_do_ton = array_merge($params_bieu_do_ton, $kho_params);
}

if (!empty($chart1_ma_vung)) {
    $sql_bieu_do_ton_loai_kho .= " AND k.ma_vung = ?";
    $params_bieu_do_ton[] = $chart1_ma_vung;
}

if (!empty($chart1_loai_kho)) {
    $sql_bieu_do_ton_loai_kho .= " AND k.ma_loai_kho = ?";
    $params_bieu_do_ton[] = $chart1_loai_kho;
}

if (!empty($chart1_don_vi_tinh)) {
    $sql_bieu_do_ton_loai_kho .= " AND hh.don_vi_tinh = ?";
    $params_bieu_do_ton[] = $chart1_don_vi_tinh;
}

$sql_bieu_do_ton_loai_kho .= " GROUP BY lk.ten_loai_kho ORDER BY lk.ma_loai_kho";

$stmt_bieu_do_ton = $pdo->prepare($sql_bieu_do_ton_loai_kho);
$stmt_bieu_do_ton->execute($params_bieu_do_ton);
$data_bieu_do_ton_temp = $stmt_bieu_do_ton->fetchAll(PDO::FETCH_ASSOC);

// ============ GỘP DỮ LIỆU NHẬP / XUẤT / TỒN ============
$data_bieu_do = [];

// Khởi tạo theo danh sách loại kho
foreach ($danh_sach_loai_kho as $lk) {
    $ten = $lk['ten_loai_kho'];
    $data_bieu_do[$ten] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
}

foreach ($data_bieu_do_loai_kho_temp as $row) {
    $key = $row['ten_loai_kho'];
    if (!isset($data_bieu_do[$key])) {
        $data_bieu_do[$key] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
    }
    $data_bieu_do[$key]['nhap'] += (int)$row['tong_nhap'];
}

foreach ($data_bieu_do_xuat_temp as $row) {
    $key = $row['ten_loai_kho'];
    if (!isset($data_bieu_do[$key])) {
        $data_bieu_do[$key] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
    }
    $data_bieu_do[$key]['xuat'] += (int)$row['tong_xuat'];
}

foreach ($data_bieu_do_ton_temp as $row) {
    $key = $row['ten_loai_kho'];
    if (!isset($data_bieu_do[$key])) {
        $data_bieu_do[$key] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
    }
    $data_bieu_do[$key]['ton'] += (int)$row['ton_cuoi_ky'];
}

// Lấy tên vùng miền nếu có
$ten_vung = '';
if (!empty($chart1_ma_vung)) {
    $stmt_vung = $pdo->prepare("SELECT ten_vung FROM vung_mien WHERE ma_vung = ?");
    $stmt_vung->execute([$chart1_ma_vung]);
    $vung_data = $stmt_vung->fetch(PDO::FETCH_ASSOC);
    $ten_vung = $vung_data['ten_vung'] ?? '';
}

// Lấy tên loại kho nếu có
$ten_loai_kho = '';
if (!empty($chart1_loai_kho)) {
    $stmt_lk = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt_lk->execute([$chart1_loai_kho]);
    $lk_data = $stmt_lk->fetch(PDO::FETCH_ASSOC);
    $ten_loai_kho = $lk_data['ten_loai_kho'] ?? '';
}

// Thiết lập header để xuất Excel
$filename = 'bao_cao_bieu_do_loai_kho_' . date('Y-m-d_H-i-s') . '.xls';
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
    <title>Báo cáo biểu đồ loại kho</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .sub-header { font-size: 14px; margin-bottom: 15px; }
        .filters { margin-bottom: 15px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">BÁO CÁO BIỂU ĐỒ BIẾN ĐỘNG THEO LOẠI KHO</div>
    
    <div class="sub-header">
        <strong>Khoảng thời gian:</strong> <?php echo date('d/m/Y', strtotime($chart1_ngay_bat_dau)) . ' - ' . date('d/m/Y', strtotime($chart1_ngay_ket_thuc)); ?><br>
        <?php if (!empty($ten_vung)): ?>
        <strong>Vùng miền:</strong> <?php echo htmlspecialchars($ten_vung); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_loai_kho)): ?>
        <strong>Loại kho:</strong> <?php echo htmlspecialchars($ten_loai_kho); ?><br>
        <?php endif; ?>
        <?php if (!empty($chart1_don_vi_tinh)): ?>
        <strong>Đơn vị tính:</strong> <?php echo htmlspecialchars($chart1_don_vi_tinh); ?><br>
        <?php endif; ?>
        <strong>Ngày xuất báo cáo:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="200">Loại kho</th>
                <th width="150" class="text-right">Tổng lượng nhập</th>
                <th width="150" class="text-right">Tổng lượng xuất</th>
                <th width="150" class="text-right">Tồn cuối kỳ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_nhap = 0;
            $tong_xuat = 0;
            $tong_ton = 0;
            foreach ($data_bieu_do as $ten_loai_kho => $data): 
                $tong_nhap += $data['nhap'];
                $tong_xuat += $data['xuat'];
                $tong_ton += $data['ton'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo htmlspecialchars($ten_loai_kho); ?></td>
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
                <td class="text-right"><?php echo number_format($tong_ton); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
<?php exit; ?>
