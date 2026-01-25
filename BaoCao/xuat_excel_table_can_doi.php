<?php
// xuat_excel_table_can_doi.php - Xuất Excel báo cáo bảng cân đối kho
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$table_ma_vung = isset($_GET['table_ma_vung']) ? $_GET['table_ma_vung'] : '';
$table_loai_kho = isset($_GET['table_loai_kho']) ? $_GET['table_loai_kho'] : '';
$table_ma_kho = isset($_GET['table_ma_kho']) ? $_GET['table_ma_kho'] : '';
$table_ngay_bat_dau = isset($_GET['table_ngay_bat_dau']) ? $_GET['table_ngay_bat_dau'] : date('Y-01-01');
$table_ngay_ket_thuc = isset($_GET['table_ngay_ket_thuc']) ? $_GET['table_ngay_ket_thuc'] : date('Y-m-d');

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

// ============ QUERY: BẢNG CÂN ĐỐI KHO CHI TIẾT ============
$sql_bang = "
    SELECT 
        hh.ma_hang,
        hh.ten_hang,
        hh.don_vi_tinh,
        lk.ten_loai_kho,
        k.ten_kho,
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

$params_bang = [$table_ngay_bat_dau, $table_ngay_bat_dau, $table_ngay_ket_thuc, $table_ngay_bat_dau, $table_ngay_ket_thuc];

// Thêm điều kiện phân quyền vào đầu WHERE clause
if (!empty($kho_condition)) {
    $sql_bang .= " " . $kho_condition;
    $params_bang = array_merge($params_bang, $kho_params);
}

if (!empty($table_ma_vung)) {
    $sql_bang .= " AND k.ma_vung = ?";
    $params_bang[] = $table_ma_vung;
}

if (!empty($table_loai_kho)) {
    $sql_bang .= " AND k.ma_loai_kho = ?";
    $params_bang[] = $table_loai_kho;
}

if (!empty($table_ma_kho)) {
    $sql_bang .= " AND k.ma_kho = ?";
    $params_bang[] = $table_ma_kho;
}

$sql_bang .= " GROUP BY hh.ma_hang, k.ma_kho ORDER BY k.ma_kho, hh.ma_hang";

$stmt_bang = $pdo->prepare($sql_bang);
$stmt_bang->execute($params_bang);
$ket_qua = $stmt_bang->fetchAll(PDO::FETCH_ASSOC);

// Tính tồn cuối kỳ
foreach ($ket_qua as &$row) {
    $row['ton_cuoi_ky'] = (int)$row['ton_dau_ky'] + (int)$row['luong_nhap'] - (int)$row['luong_xuat'];
}

// Lấy thông tin bộ lọc để hiển thị
$ten_vung = '';
if (!empty($table_ma_vung)) {
    $stmt_vung = $pdo->prepare("SELECT ten_vung FROM vung_mien WHERE ma_vung = ?");
    $stmt_vung->execute([$table_ma_vung]);
    $vung_data = $stmt_vung->fetch(PDO::FETCH_ASSOC);
    $ten_vung = $vung_data['ten_vung'] ?? '';
}

$ten_loai_kho = '';
if (!empty($table_loai_kho)) {
    $stmt_lk = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt_lk->execute([$table_loai_kho]);
    $lk_data = $stmt_lk->fetch(PDO::FETCH_ASSOC);
    $ten_loai_kho = $lk_data['ten_loai_kho'] ?? '';
}

$ten_kho = '';
if (!empty($table_ma_kho)) {
    $stmt_kho = $pdo->prepare("SELECT ten_kho FROM kho WHERE ma_kho = ?");
    $stmt_kho->execute([$table_ma_kho]);
    $kho_data = $stmt_kho->fetch(PDO::FETCH_ASSOC);
    $ten_kho = $kho_data['ten_kho'] ?? '';
}

// Thiết lập header để xuất Excel
$filename = 'bao_cao_bang_can_doi_kho_' . date('Y-m-d_H-i-s') . '.xls';
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
    <title>Báo cáo bảng cân đối kho</title>
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
    <div class="header">BÁO CÁO BẢNG CÂN ĐỐI KHO CHI TIẾT</div>
    
    <div class="sub-header">
        <strong>Khoảng thời gian:</strong> <?php echo date('d/m/Y', strtotime($table_ngay_bat_dau)) . ' - ' . date('d/m/Y', strtotime($table_ngay_ket_thuc)); ?><br>
        <?php if (!empty($ten_vung)): ?>
        <strong>Vùng miền:</strong> <?php echo htmlspecialchars($ten_vung); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_loai_kho)): ?>
        <strong>Loại kho:</strong> <?php echo htmlspecialchars($ten_loai_kho); ?><br>
        <?php endif; ?>
        <?php if (!empty($ten_kho)): ?>
        <strong>Kho:</strong> <?php echo htmlspecialchars($ten_kho); ?><br>
        <?php endif; ?>
        <strong>Ngày xuất báo cáo:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th width="50">STT</th>
                <th width="100">Mã hàng</th>
                <th width="200">Tên hàng</th>
                <th width="80">Đơn vị tính</th>
                <th width="120">Loại kho</th>
                <th width="150">Tên kho</th>
                <th width="100" class="text-right">Tồn đầu kỳ</th>
                <th width="100" class="text-right">Lượng nhập</th>
                <th width="100" class="text-right">Lượng xuất</th>
                <th width="100" class="text-right">Tồn cuối kỳ</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $stt = 1;
            $tong_ton_dau = 0;
            $tong_nhap = 0;
            $tong_xuat = 0;
            $tong_ton_cuoi = 0;
            foreach ($ket_qua as $row): 
                $tong_ton_dau += (int)$row['ton_dau_ky'];
                $tong_nhap += (int)$row['luong_nhap'];
                $tong_xuat += (int)$row['luong_xuat'];
                $tong_ton_cuoi += (int)$row['ton_cuoi_ky'];
            ?>
            <tr>
                <td class="text-center"><?php echo $stt++; ?></td>
                <td><?php echo htmlspecialchars($row['ma_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_hang']); ?></td>
                <td><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_loai_kho']); ?></td>
                <td><?php echo htmlspecialchars($row['ten_kho']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_dau_ky']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_nhap']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['luong_xuat']); ?></td>
                <td class="text-right"><?php echo number_format((int)$row['ton_cuoi_ky']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background-color: #f0f0f0; font-weight: bold;">
                <td colspan="6" class="text-right">TỔNG CỘNG:</td>
                <td class="text-right"><?php echo number_format($tong_ton_dau); ?></td>
                <td class="text-right"><?php echo number_format($tong_nhap); ?></td>
                <td class="text-right"><?php echo number_format($tong_xuat); ?></td>
                <td class="text-right"><?php echo number_format($tong_ton_cuoi); ?></td>
            </tr>
        </tfoot>
    </table>
</body>
</html>
<?php exit; ?>
