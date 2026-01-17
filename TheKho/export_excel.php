<?php
// export_excel.php - Xuất dữ liệu thẻ kho ra Excel
include '../include/connect.php';

// Bộ lọc kho
$ma_kho_filter = $_GET['ma_kho'] ?? '';

// Bộ lọc loại hàng
$ma_loai_hang_filter = $_GET['ma_loai_hang'] ?? '';

// Xây dựng điều kiện lọc
$conditions = [];
$params = [];

if ($ma_kho_filter) {
    $conditions[] = "tk.ma_kho = ?";
    $params[] = $ma_kho_filter;
}
if ($ma_loai_hang_filter) {
    $conditions[] = "h.ma_loai_hang = ?";
    $params[] = $ma_loai_hang_filter;
}

$where_clause = !empty($conditions) ? " WHERE " . implode(" AND ", $conditions) : "";

// Lấy dữ liệu để xuất Excel (không giới hạn số lượng)
$sql = "
    SELECT DISTINCT
        tk.ma_the_kho,
        tk.ma_kho,
        k.ten_kho,
        tk.ma_hang,
        h.ten_hang,
        tk.so_luong_ton
    FROM the_kho tk
    LEFT JOIN kho k ON tk.ma_kho = k.ma_kho
    LEFT JOIN hang_hoa h ON tk.ma_hang = h.ma_hang
    {$where_clause}
    ORDER BY tk.ma_the_kho DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$the_kho_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Thiết lập header để xuất Excel
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="danh_sach_the_kho_' . date('Y-m-d_H-i-s') . '.xls"');
header('Cache-Control: max-age=0');

// Tạo nội dung HTML cho Excel
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Danh sách Thẻ kho</title>
    <style>
        body { font-family: Arial, sans-serif; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; }
        .filters { margin-bottom: 15px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">DANH SÁCH THẺ KHO</div>

    <div class="filters">
        <?php if ($ma_kho_filter || $ma_loai_hang_filter): ?>
            <strong>Bộ lọc áp dụng:</strong><br>
            <?php if ($ma_kho_filter): ?>
                - Kho: <?php
                $kho_stmt = $pdo->prepare("SELECT ten_kho FROM kho WHERE ma_kho = ?");
                $kho_stmt->execute([$ma_kho_filter]);
                $kho_info = $kho_stmt->fetch(PDO::FETCH_ASSOC);
                echo htmlspecialchars($kho_info['ten_kho'] ?? 'N/A') . " ({$ma_kho_filter})";
                ?><br>
            <?php endif; ?>
            <?php if ($ma_loai_hang_filter): ?>
                - Loại hàng: <?php
                $loai_stmt = $pdo->prepare("SELECT ten_loai_hang FROM loai_hang WHERE ma_loai_hang = ?");
                $loai_stmt->execute([$ma_loai_hang_filter]);
                $loai_info = $loai_stmt->fetch(PDO::FETCH_ASSOC);
                echo htmlspecialchars($loai_info['ten_loai_hang'] ?? 'N/A');
                ?><br>
            <?php endif; ?>
        <?php else: ?>
            <strong>Không có bộ lọc - Hiển thị tất cả</strong>
        <?php endif; ?>
    </div>

    <div style="margin-bottom: 10px;">
        <strong>Ngày xuất:</strong> <?php echo date('d/m/Y H:i:s'); ?><br>
        <strong>Tổng số bản ghi:</strong> <?php echo count($the_kho_list); ?>
    </div>

    <table>
        <thead>
            <tr>
                <th>STT</th>
                <th>Mã Thẻ Kho</th>
                <th>Mã Kho</th>
                <th>Tên Kho</th>
                <th>Mã Hàng</th>
                <th>Tên Hàng</th>
                <th class="text-right">Số lượng tồn</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($the_kho_list)): ?>
                <tr>
                    <td colspan="7" class="text-center">Không có dữ liệu thẻ kho</td>
                </tr>
            <?php else: ?>
                <?php $stt = 1; foreach ($the_kho_list as $tk): ?>
                <tr>
                    <td class="text-center"><?php echo $stt++; ?></td>
                    <td><?php echo htmlspecialchars($tk['ma_the_kho']); ?></td>
                    <td><?php echo htmlspecialchars($tk['ma_kho']); ?></td>
                    <td><?php echo htmlspecialchars($tk['ten_kho'] ?? 'Kho không xác định'); ?></td>
                    <td><?php echo htmlspecialchars($tk['ma_hang']); ?></td>
                    <td><?php echo htmlspecialchars($tk['ten_hang'] ?? $tk['ma_hang']); ?></td>
                    <td class="text-right"><?php echo number_format($tk['so_luong_ton']); ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>
<?php exit; ?>