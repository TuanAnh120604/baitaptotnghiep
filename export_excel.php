<?php
include './include/connect.php';
include './include/permissions.php';
checkAccess('thongke');

// Khởi tạo các biến phân trang và bộ lọc
$action = $_GET['action'] ?? '';
$currentWarehouse = $_GET['warehouse'] ?? '';
$currentPeriod = $_GET['period'] ?? 'month';
$customStartDate = $_GET['start_date'] ?? null;
$customEndDate = $_GET['end_date'] ?? null;
$unit = $_GET['unit'] ?? '';

// Xử lý khoảng thời gian
$today = new DateTime();
$startDate = null;
$endDate = $today->format('Y-m-d');

// Ưu tiên custom range nếu có
if ($customStartDate && $customEndDate) {
    $startDate = DateTime::createFromFormat('Y-m-d', $customStartDate);
    $endDateObj = DateTime::createFromFormat('Y-m-d', $customEndDate);
    if ($startDate && $endDateObj && $startDate <= $endDateObj) {
        $endDate = $endDateObj->format('Y-m-d');
        $startDate = $startDate->format('Y-m-d');
        $currentPeriod = 'custom';
    }
} else {
    switch ($currentPeriod) {
        case 'day':
            $startDate = $today->format('Y-m-d');
            break;
        case 'week':
            $startDate = (clone $today)->modify('-6 days')->format('Y-m-d');
            break;
        case 'month':
            $startDate = (clone $today)->modify('first day of this month')->format('Y-m-d');
            break;
        case 'quarter':
            $month = $today->format('n');
            $quarterStartMonth = floor(($month - 1) / 3) * 3 + 1;
            $startDate = (clone $today)->setDate($today->format('Y'), $quarterStartMonth, 1)->format('Y-m-d');
            break;
        case 'year':
            $startDate = (clone $today)->setDate($today->format('Y'), 1, 1)->format('Y-m-d');
            break;
        default:
            $startDate = (clone $today)->modify('first day of this month')->format('Y-m-d');
    }
}

// Lấy danh sách loại kho để lấy tên
$warehouseName = '';
try {
    $stmt = $pdo->prepare("SELECT ten_loai_kho FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt->execute([$currentWarehouse]);
    $warehouseData = $stmt->fetch(PDO::FETCH_ASSOC);
    $warehouseName = $warehouseData['ten_loai_kho'] ?? '';
} catch (Exception $e) {
    $warehouseName = '';
}

// Lấy dữ liệu thống kê (không phân trang - xuất tất cả)
$tableData = [];
$summary = [];
$allWarehousesData = [];

if ($action === 'export_all') {
    // Export tất cả kho
    try {
        $stmt = $pdo->query("SELECT ma_loai_kho, ten_loai_kho FROM loai_kho ORDER BY ma_loai_kho");
        $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($warehouses as $wh) {
            $warehouseCode = $wh['ma_loai_kho'];
            $warehouseData = getWarehouseExportData($pdo, $warehouseCode, $startDate, $endDate, $unit);
            if (!empty($warehouseData['tableData'])) {
                $allWarehousesData[] = [
                    'warehouse' => $wh,
                    'data' => $warehouseData
                ];
            }
        }
    } catch (Exception $e) {
        $allWarehousesData = [];
    }
} elseif (!empty($currentWarehouse)) {
    // Export một kho cụ thể
    try {
        $warehouseData = getWarehouseExportData($pdo, $currentWarehouse, $startDate, $endDate, $unit);
        $tableData = $warehouseData['tableData'];
        $summary = $warehouseData['summary'];
    } catch (Exception $e) {
        $tableData = [];
        $summary = [];
    }
}

function getWarehouseExportData($pdo, $warehouse, $startDate, $endDate, $unit) {
    // Build unit filter condition
    $unitCondition = '';
    $unitParams = [];
    if (!empty($unit)) {
        $unitCondition = ' AND hh.don_vi_tinh = :unit';
        $unitParams[':unit'] = $unit;
    }

    // Mapping đơn vị tính
    $unitMap = [
        'L001' => ' Kg',
        'L002' => ' lít',
        'L003' => ' cái',
        'L004' => ' thùng'
    ];
    $defaultUnit = '';

    // Lấy dữ liệu (không giới hạn số lượng)
    $stmt = $pdo->prepare("
        SELECT
            hh.ma_hang AS code,
            hh.ten_hang AS name,
            hh.don_vi_tinh AS unit,
            k.ten_kho AS store,
            COALESCE(tk.so_luong_ton, 0) AS start_qty,
            COALESCE(SUM(CASE WHEN pn.ngay_nhap BETWEEN :start AND :end THEN ctn.so_luong_nhap ELSE 0 END), 0) AS total_in,
            COALESCE(SUM(CASE WHEN px.ngay_xuat BETWEEN :start AND :end THEN ctx.so_luong_xuat ELSE 0 END), 0) AS total_out
        FROM hang_hoa hh
        LEFT JOIN ct_phieu_nhap ctn ON ctn.ma_hang = hh.ma_hang
        LEFT JOIN phieu_nhap pn ON pn.ma_phieu_nhap = ctn.ma_phieu_nhap
        LEFT JOIN ct_phieu_xuat ctx ON ctx.ma_hang = hh.ma_hang
        LEFT JOIN phieu_xuat px ON px.ma_phieu_xuat = ctx.ma_phieu_xuat
        LEFT JOIN kho k ON (pn.ma_kho = k.ma_kho OR px.ma_kho = k.ma_kho)
        LEFT JOIN the_kho tk ON tk.ma_kho = k.ma_kho AND tk.ma_hang = hh.ma_hang AND tk.ngay = (
            SELECT MAX(tk2.ngay) FROM the_kho tk2 WHERE tk2.ma_kho = k.ma_kho AND tk2.ma_hang = hh.ma_hang AND tk2.ngay < :start
        )
        WHERE hh.ma_loai_hang = (CASE
            WHEN :warehouse = 'L001' THEN 'M001'
            WHEN :warehouse = 'L002' THEN 'M002'
            WHEN :warehouse = 'L003' THEN 'M003'
            WHEN :warehouse = 'L004' THEN 'M004'
            ELSE NULL
        END)
          AND k.ma_loai_kho = :warehouse
          AND (
              pn.ngay_nhap BETWEEN :start AND :end OR
              px.ngay_xuat BETWEEN :start AND :end OR
              pn.ngay_nhap < :start OR
              px.ngay_xuat < :start
          )
          $unitCondition
        GROUP BY hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, k.ten_kho, tk.so_luong_ton
        HAVING (total_in > 0 OR total_out > 0 OR start_qty != 0)
        ORDER BY hh.ten_hang ASC
    ");
    $params = array_merge([
        ':warehouse' => $warehouse,
        ':start' => $startDate,
        ':end' => $endDate
    ], $unitParams);
    $stmt->execute($params);
    $rawData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Xử lý dữ liệu
    $tableData = [];
    $total_import_quantity = 0;
    $total_export_quantity = 0;
    foreach ($rawData as $item) {
        $item['start'] = (int)$item['start_qty'];
        $item['in'] = (int)$item['total_in'];
        $item['out'] = (int)$item['total_out'];
        $item['end'] = $item['start'] + $item['in'] - $item['out'];

        $total_import_quantity += $item['in'];
        $total_export_quantity += $item['out'];

        unset($item['start_qty'], $item['total_in'], $item['total_out']);
        $tableData[] = $item;
    }

    // Tính số lượng giao dịch
    $transaction_count = 0;
    $stmt_counts = $pdo->prepare("
        SELECT COUNT(DISTINCT pn.ma_phieu_nhap) + COUNT(DISTINCT px.ma_phieu_xuat) as total
        FROM phieu_nhap pn
        LEFT JOIN phieu_xuat px ON px.ma_kho = pn.ma_kho
        JOIN kho k ON pn.ma_kho = k.ma_kho
        WHERE k.ma_loai_kho = :warehouse
          AND (pn.ngay_nhap BETWEEN :start AND :end OR px.ngay_xuat BETWEEN :start AND :end)
    ");
    $stmt_counts->execute([':warehouse' => $warehouse, ':start' => $startDate, ':end' => $endDate]);
    $transaction_count = $stmt_counts->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    $unitDisplay = $unitMap[$warehouse] ?? $defaultUnit;

    // Chuẩn bị dữ liệu summary
    $summary = [
        'import' => number_format($total_import_quantity) . $unitDisplay,
        'export' => number_format($total_export_quantity) . $unitDisplay,
        'count' => $transaction_count
    ];

    return [
        'tableData' => $tableData,
        'summary' => $summary
    ];
}

// Thiết lập header để xuất Excel
$filename = $action === 'export_all' ? 'bao_cao_tat_ca_kho_' . date('Y-m-d_H-i-s') . '.xls' : 'bao_cao_can_doi_' . $currentWarehouse . '_' . date('Y-m-d_H-i-s') . '.xls';
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
    <title>Báo cáo cân đối xuất nhập tồn<?php echo $action === 'export_all' ? ' - Tất cả kho' : ' - ' . htmlspecialchars($warehouseName); ?></title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; vertical-align: top; }
        th { background-color: #f0f0f0; font-weight: bold; text-align: center; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .header { font-size: 18px; font-weight: bold; margin-bottom: 20px; text-align: center; }
        .sub-header { font-size: 14px; margin-bottom: 15px; }
        .filters { margin-bottom: 15px; font-size: 12px; }
        .summary { margin-bottom: 20px; }
        .summary-table { width: auto; margin: 0 auto; }
        .summary-table td { border: none; padding: 3px 10px; }
        .no-data { text-align: center; font-style: italic; color: #666; }
        .warehouse-section { margin-bottom: 30px; page-break-after: always; }
        .warehouse-header { background-color: #e0e0e0; padding: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="header">BÁO CÁO CÂN ĐỐI XUẤT NHẬP TỒN<?php echo $action === 'export_all' ? ' - TẤT CẢ KHO' : ''; ?></div>

    <div class="sub-header">
        <?php if ($action !== 'export_all'): ?>
        <strong>Loại kho:</strong> <?php echo htmlspecialchars($warehouseName); ?> (<?php echo htmlspecialchars($currentWarehouse); ?>)<br>
        <?php endif; ?>
        <strong>Kỳ báo cáo:</strong> <?php
            if ($currentPeriod === 'custom') {
                echo 'Từ ' . date('d/m/Y', strtotime($startDate)) . ' đến ' . date('d/m/Y', strtotime($endDate));
            } else {
                switch ($currentPeriod) {
                    case 'day': echo 'Theo ngày: ' . date('d/m/Y', strtotime($startDate)); break;
                    case 'week': echo 'Theo tuần: ' . date('d/m/Y', strtotime($startDate)) . ' - ' . date('d/m/Y', strtotime($endDate)); break;
                    case 'month': echo 'Theo tháng: ' . date('m/Y', strtotime($startDate)); break;
                    case 'quarter': echo 'Theo quý: ' . date('m/Y', strtotime($startDate)) . ' - ' . date('m/Y', strtotime($endDate)); break;
                    case 'year': echo 'Theo năm: ' . date('Y', strtotime($startDate)); break;
                    default: echo 'Theo tháng: ' . date('m/Y', strtotime($startDate));
                }
            }
        ?><br>
        <?php if (!empty($unit)): ?>
        <strong>Đơn vị tính:</strong> <?php echo htmlspecialchars($unit); ?><br>
        <?php endif; ?>
        <strong>Ngày xuất:</strong> <?php echo date('d/m/Y H:i:s'); ?>
    </div>

    <?php if ($action === 'export_all'): ?>
        <!-- Export tất cả kho -->
        <?php foreach ($allWarehousesData as $warehouseInfo): ?>
        <div class="warehouse-section">
            <div class="warehouse-header">
                <strong>Kho: <?php echo htmlspecialchars($warehouseInfo['warehouse']['ten_loai_kho']); ?> (<?php echo htmlspecialchars($warehouseInfo['warehouse']['ma_loai_kho']); ?>)</strong>
            </div>

            <div class="summary">
                <table class="summary-table">
                    <tr>
                        <td><strong>Tổng lượng nhập:</strong></td>
                        <td class="text-right"><?php echo $warehouseInfo['data']['summary']['import'] ?? '—'; ?></td>
                        <td width="50"></td>
                        <td><strong>Tổng lượng xuất:</strong></td>
                        <td class="text-right"><?php echo $warehouseInfo['data']['summary']['export'] ?? '—'; ?></td>
                        <td width="50"></td>
                        <td><strong>Số mặt hàng:</strong></td>
                        <td class="text-right"><?php echo $warehouseInfo['data']['summary']['count'] ?? '—'; ?></td>
                    </tr>
                </table>
            </div>

            <table>
                <thead>
                    <tr>
                        <th width="50">STT</th>
                        <th width="120">Mã hàng</th>
                        <th width="200">Tên hàng</th>
                        <th width="100">Kho</th>
                        <th width="80">ĐVT</th>
                        <th width="100">Tồn đầu</th>
                        <th width="100">Tổng nhập</th>
                        <th width="100">Tổng xuất</th>
                        <th width="100">Tồn cuối</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($warehouseInfo['data']['tableData'])): ?>
                        <?php $stt = 1; foreach ($warehouseInfo['data']['tableData'] as $item): ?>
                        <tr>
                            <td class="text-center"><?php echo $stt++; ?></td>
                            <td><?php echo htmlspecialchars($item['code'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($item['name'] ?? '—'); ?></td>
                            <td><?php echo htmlspecialchars($item['store'] ?? '—'); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? '—'); ?></td>
                            <td class="text-right"><?php echo $item['start'] > 0 ? number_format($item['start']) : '—'; ?></td>
                            <td class="text-right"><?php echo $item['in'] > 0 ? number_format($item['in']) : '0'; ?></td>
                            <td class="text-right"><?php echo $item['out'] > 0 ? number_format($item['out']) : '0'; ?></td>
                            <td class="text-right"><?php echo number_format($item['end']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="no-data">Không có dữ liệu trong kỳ báo cáo</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <!-- Export một kho cụ thể -->
        <div class="summary">
            <table class="summary-table">
                <tr>
                    <td><strong>Tổng lượng nhập:</strong></td>
                    <td class="text-right"><?php echo $summary['import'] ?? '—'; ?></td>
                    <td width="50"></td>
                    <td><strong>Tổng lượng xuất:</strong></td>
                    <td class="text-right"><?php echo $summary['export'] ?? '—'; ?></td>
                    <td width="50"></td>
                    <td><strong>Số mặt hàng:</strong></td>
                    <td class="text-right"><?php echo $summary['count'] ?? '—'; ?></td>
                </tr>
            </table>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="50">STT</th>
                    <th width="120">Mã hàng</th>
                    <th width="200">Tên hàng</th>
                    <th width="100">Kho</th>
                    <th width="80">ĐVT</th>
                    <th width="100">Tồn đầu</th>
                    <th width="100">Tổng nhập</th>
                    <th width="100">Tổng xuất</th>
                    <th width="100">Tồn cuối</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tableData)): ?>
                    <?php $stt = 1; foreach ($tableData as $item): ?>
                    <tr>
                        <td class="text-center"><?php echo $stt++; ?></td>
                        <td><?php echo htmlspecialchars($item['code'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($item['name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($item['store'] ?? '—'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['unit'] ?? '—'); ?></td>
                        <td class="text-right"><?php echo $item['start'] > 0 ? number_format($item['start']) : '—'; ?></td>
                        <td class="text-right"><?php echo $item['in'] > 0 ? number_format($item['in']) : '0'; ?></td>
                        <td class="text-right"><?php echo $item['out'] > 0 ? number_format($item['out']) : '0'; ?></td>
                        <td class="text-right"><?php echo number_format($item['end']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" class="no-data">Không có dữ liệu trong kỳ báo cáo</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</body>
</html>
