<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // cho phép frontend gọi từ domain khác (nếu cần)

// Function tạo dữ liệu chart theo thời gian
function generateChartData($pdo, $warehouse, $startDate, $endDate, $period) {
    $dateFormat = '%Y-%m-%d'; // Format SQL cho ngày
    $displayFormat = 'd/m';  // Format hiển thị

    // Xác định khoảng thời gian group by
    switch ($period) {
        case 'day':
            $groupBy = "DATE_FORMAT(ngay, '%Y-%m-%d')";
            $displayFormat = 'H:i';
            break;
        case 'week':
        case 'month':
            $groupBy = "DATE_FORMAT(ngay, '%Y-%m-%d')";
            $displayFormat = 'd/m';
            break;
        case 'quarter':
        case 'year':
        case 'custom':
            $groupBy = "DATE_FORMAT(ngay, '%Y-%m-%d')";
            $displayFormat = 'd/m/Y';
            break;
        default:
            $groupBy = "DATE_FORMAT(ngay, '%Y-%m-%d')";
            $displayFormat = 'd/m';
    }

    // Query lấy dữ liệu nhập xuất theo thời gian
    $stmt = $pdo->prepare("
        SELECT
            DATE(ngay) as date,
            SUM(CASE WHEN loai = 'nhap' THEN so_luong ELSE 0 END) as import_quantity,
            SUM(CASE WHEN loai = 'xuat' THEN so_luong ELSE 0 END) as export_quantity
        FROM (
            -- Dữ liệu nhập (số lượng)
            SELECT
                pn.ngay_nhap as ngay,
                'nhap' as loai,
                SUM(ctn.so_luong_nhap) as so_luong
            FROM ct_phieu_nhap ctn
            JOIN phieu_nhap pn ON ctn.ma_phieu_nhap = pn.ma_phieu_nhap
            JOIN hang_hoa hh ON ctn.ma_hang = hh.ma_hang
            JOIN kho k ON pn.ma_kho = k.ma_kho
            WHERE k.ma_loai_kho = :warehouse
              AND pn.ngay_nhap BETWEEN :start AND :end
            GROUP BY pn.ngay_nhap

            UNION ALL

            -- Dữ liệu xuất (số lượng)
            SELECT
                px.ngay_xuat as ngay,
                'xuat' as loai,
                SUM(ctx.so_luong_xuat) as so_luong
            FROM ct_phieu_xuat ctx
            JOIN phieu_xuat px ON ctx.ma_phieu_xuat = px.ma_phieu_xuat
            JOIN hang_hoa hh ON ctx.ma_hang = hh.ma_hang
            JOIN kho k ON px.ma_kho = k.ma_kho
            WHERE k.ma_loai_kho = :warehouse
              AND px.ngay_xuat BETWEEN :start AND :end
            GROUP BY px.ngay_xuat
        ) combined
        GROUP BY DATE(ngay)
        ORDER BY date
    ");

    $stmt->execute([
        ':warehouse' => $warehouse,
        ':start' => $startDate,
        ':end' => $endDate
    ]);

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $labels = [];
    $importData = [];
    $exportData = [];

    // Nếu không có dữ liệu, tạo dữ liệu mẫu
    if (empty($data)) {
        $labels = ["Từ " . date('d/m', strtotime($startDate)) . " đến " . date('d/m', strtotime($endDate))];
        $importData = [0];
        $exportData = [0];
    } else {
        // Tạo labels và data từ kết quả query
        $currentDate = strtotime($startDate);
        $endDateTime = strtotime($endDate);

        while ($currentDate <= $endDateTime) {
            $dateStr = date('Y-m-d', $currentDate);
            $displayDate = date($displayFormat, $currentDate);
            $labels[] = $displayDate;

            // Tìm dữ liệu cho ngày này
            $found = false;
            foreach ($data as $row) {
                if ($row['date'] === $dateStr) {
                    $importData[] = (float)$row['import_quantity'];
                    $exportData[] = (float)$row['export_quantity'];
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $importData[] = 0;
                $exportData[] = 0;
            }

            // Tăng ngày (có thể là 1 ngày hoặc 1 tuần tùy period)
            $currentDate = strtotime('+1 day', $currentDate);
        }
    }

    return [
        'labels' => $labels,
        'import' => $importData,
        'export' => $exportData
    ];
}

try {
    include './include/connect.php';

    $action = $_GET['action'] ?? '';
    $warehouse = $_GET['warehouse'] ?? '';
    $period = $_GET['period'] ?? 'month';
    $customStart = $_GET['start_date'] ?? null;
    $customEnd   = $_GET['end_date'] ?? null;

    // Validate period
    $validPeriods = ['day', 'week', 'month', 'quarter', 'year', 'custom'];
    if (!in_array($period, $validPeriods)) {
        $period = 'month';
    }

    // 1. Lấy danh sách loại kho
    if ($action === 'warehouses') {
        $stmt = $pdo->query("
            SELECT ma_loai_kho, ten_loai_kho 
            FROM loai_kho 
            ORDER BY ma_loai_kho
        ");
        $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['warehouses' => $warehouses], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 2. Lấy dữ liệu thống kê theo loại kho + kỳ báo cáo
    if (empty($warehouse)) {
        echo json_encode(['error' => 'Thiếu tham số warehouse']);
        exit;
    }

    // Validate warehouse exists
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM loai_kho WHERE ma_loai_kho = ?");
    $stmt_check->execute([$warehouse]);
    if ($stmt_check->fetchColumn() == 0) {
        echo json_encode(['error' => 'Loại kho không tồn tại']);
        exit;
    }

    // Xác định khoảng thời gian
    $today = new DateTime();
    $startDate = null;
    $endDate = $today->format('Y-m-d');

    // Ưu tiên custom range nếu có
    if ($customStart && $customEnd) {
        $startDate = DateTime::createFromFormat('Y-m-d', $customStart);
        $endDateObj = DateTime::createFromFormat('Y-m-d', $customEnd);
        if (!$startDate || !$endDateObj) {
            echo json_encode(['error' => 'Ngày không hợp lệ']);
            exit;
        }
        if ($startDate > $endDateObj) {
            echo json_encode(['error' => 'start_date phải nhỏ hơn hoặc bằng end_date']);
            exit;
        }
        $endDate = $endDateObj->format('Y-m-d');
        $startDate = $startDate->format('Y-m-d');
        $period = 'custom';
    } else {
        switch ($period) {
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
                $startDate = (clone $today)->modify('-30 days')->format('Y-m-d');
        }
    }

    // Mapping icon theo loại kho (có thể mở rộng sau)
    $iconMap = [
        'L001' => 'category',           // Nguyên liệu
        'L002' => 'local_gas_station',  // Nhiên liệu
        'L003' => 'settings',           // Phụ tùng
        'L004' => 'inventory'           // Thành phẩm
    ];
    $defaultIcon = 'inventory';

    // Lấy tất cả hàng hóa thuộc loại kho này
    $stmt = $pdo->prepare("
        SELECT DISTINCT hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, hh.don_gia,
               lk.ten_loai_kho, k.ten_kho
        FROM hang_hoa hh
        INNER JOIN loai_hang lh ON hh.ma_loai_hang = lh.ma_loai_hang
        INNER JOIN kho k ON k.ma_loai_kho = ?   -- lọc theo ma_loai_kho
        LEFT JOIN loai_kho lk ON lk.ma_loai_kho = k.ma_loai_kho
        WHERE hh.ma_loai_hang IN (
            SELECT ma_loai_hang FROM loai_hang WHERE ma_loai_hang = ? -- tạm thời dùng chung mapping
        )
    ");
    // Lưu ý: Hiện tại mapping ma_loai_kho <-> ma_loai_hang chưa chặt chẽ, bạn cần bổ sung cột liên kết nếu cần chính xác hơn

    // Lấy dữ liệu thống kê với logic tồn đầu kỳ chính xác
    $stmt = $pdo->prepare("
        SELECT
            hh.ma_hang AS code,
            hh.ten_hang AS name,
            hh.don_vi_tinh AS unit,
            hh.don_gia,
            k.ten_kho AS store,
            -- Tồn đầu kỳ: Tổng nhập trước startDate - Tổng xuất trước startDate
            COALESCE(SUM(CASE WHEN pn.ngay_nhap < :start THEN ctn.so_luong_nhap ELSE 0 END), 0) -
            COALESCE(SUM(CASE WHEN px.ngay_xuat < :start THEN ctx.so_luong_xuat ELSE 0 END), 0) AS start_qty,
            -- Tổng nhập trong kỳ
            COALESCE(SUM(CASE WHEN pn.ngay_nhap BETWEEN :start AND :end THEN ctn.so_luong_nhap ELSE 0 END), 0) AS total_in,
            -- Tổng xuất trong kỳ
            COALESCE(SUM(CASE WHEN px.ngay_xuat BETWEEN :start AND :end THEN ctx.so_luong_xuat ELSE 0 END), 0) AS total_out,
            :icon AS icon
        FROM hang_hoa hh
        LEFT JOIN ct_phieu_nhap ctn ON ctn.ma_hang = hh.ma_hang
        LEFT JOIN phieu_nhap pn ON pn.ma_phieu_nhap = ctn.ma_phieu_nhap
        LEFT JOIN ct_phieu_xuat ctx ON ctx.ma_hang = hh.ma_hang
        LEFT JOIN phieu_xuat px ON px.ma_phieu_xuat = ctx.ma_phieu_xuat
        LEFT JOIN kho k ON (pn.ma_kho = k.ma_kho OR px.ma_kho = k.ma_kho)
        WHERE k.ma_loai_kho = :warehouse
          AND (
              pn.ngay_nhap BETWEEN :start AND :end OR
              px.ngay_xuat BETWEEN :start AND :end OR
              pn.ngay_nhap < :start OR
              px.ngay_xuat < :start
          )
        GROUP BY hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, hh.don_gia, k.ten_kho
        HAVING (total_in > 0 OR total_out > 0 OR start_qty != 0)
        ORDER BY total_in DESC
    ");

    $stmt->execute([
        ':warehouse' => $warehouse,
        ':start'     => $startDate,
        ':end'       => $endDate,
        ':icon'      => $iconMap[$warehouse] ?? $defaultIcon
    ]);

    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Tính toán tồn cuối kỳ và chuẩn bị dữ liệu
    $total_import_quantity = 0; // Tổng số lượng nhập
    $total_export_quantity = 0; // Tổng số lượng xuất

    foreach ($items as &$item) {
        $item['start'] = (int)$item['start_qty'];
        $item['in']    = (int)$item['total_in'];
        $item['out']   = (int)$item['total_out'];
        $item['end']   = $item['start'] + $item['in'] - $item['out'];

        // Tính tổng số lượng nhập/xuất
        $total_import_quantity += $item['in'];
        $total_export_quantity += $item['out'];

        // Xóa các trường tạm thời
        unset($item['start_qty'], $item['total_in'], $item['total_out'], $item['don_gia']);
    }

    // Tính số lượng giao dịch thực tế
    $stmt_count = $pdo->prepare("
        SELECT
            (SELECT COUNT(DISTINCT ma_phieu_nhap) FROM phieu_nhap WHERE ngay_nhap BETWEEN :start AND :end AND ma_kho IN (SELECT ma_kho FROM kho WHERE ma_loai_kho = :warehouse)) +
            (SELECT COUNT(DISTINCT ma_phieu_xuat) FROM phieu_xuat WHERE ngay_xuat BETWEEN :start AND :end AND ma_kho IN (SELECT ma_kho FROM kho WHERE ma_loai_kho = :warehouse)) as transaction_count
    ");
    $stmt_count->execute([':start' => $startDate, ':end' => $endDate, ':warehouse' => $warehouse]);
    $transaction_count = (int)$stmt_count->fetchColumn();

    // Chuẩn bị dữ liệu chart theo thời gian
    $chart_data = generateChartData($pdo, $warehouse, $startDate, $endDate, $period);

    // Format số lượng
    function formatQuantity($num) {
        if ($num >= 1000000000) return number_format($num / 1000000000, 1) . ' Tỷ';
        if ($num >= 1000000)    return number_format($num / 1000000, 1) . ' Triệu';
        if ($num >= 1000)       return number_format($num / 1000, 1) . ' K';
        return number_format($num);
    }

    $response = [
        'chart' => $chart_data,
        'summary' => [
            'import' => formatQuantity($total_import_quantity),
            'export' => formatQuantity($total_export_quantity),
            'count'  => $transaction_count
        ],
        'items' => $items,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'period' => $period
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}