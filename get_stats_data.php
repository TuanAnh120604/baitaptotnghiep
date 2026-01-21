<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); // cho phép frontend gọi từ domain khác (nếu cần)

// Function tạo dữ liệu chart theo thời gian
function generateChartData($pdo, $warehouse, $itemType, $startDate, $endDate, $period, $unit, $unitFilter = '', $regionFilter = '')
{
    $dateFormat = '%Y-%m-%d'; // Format SQL cho ngày
    $displayFormat = 'd/m';  // Format hiển thị
    $increment = '+1 day'; // Mặc định tăng 1 ngày
    // Xác định khoảng thời gian group by
    switch ($period) {
        case 'day':
            $groupBy = "DATE_FORMAT(ngay, '%Y-%m-%d %H')";
            $dateFormat = '%Y-%m-%d %H';
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
    // Build WHERE clause for chart filters
    $chartWhere = "hh.ma_loai_hang = :item_type
              AND k.ma_loai_kho = :warehouse";

    $chartParams = [
        ':item_type' => $itemType,
        ':warehouse' => $warehouse,
        ':start' => $startDate,
        ':end' => $endDate
    ];

    if (!empty($unitFilter)) {
        $chartWhere .= " AND hh.don_vi_tinh = :unit_filter";
        $chartParams[':unit_filter'] = $unitFilter;
    }

    if (!empty($regionFilter)) {
        $chartWhere .= " AND k.ma_vung = :region_filter";
        $chartParams[':region_filter'] = $regionFilter;
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
            WHERE {$chartWhere}
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
            WHERE {$chartWhere}
              AND px.ngay_xuat BETWEEN :start AND :end
            GROUP BY px.ngay_xuat
        ) combined
        GROUP BY DATE(ngay)
        ORDER BY date
    ");
    $stmt->execute($chartParams);
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
        'export' => $exportData,
        'unit' => $unit
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
    // 1. Lấy danh sách loại kho (đã lọc theo quyền user)
    if ($action === 'warehouses') {
        $role = trim($_SESSION['role'] ?? '');
        $ma_nd = $_SESSION['MaND'] ?? null;

        if ($role === 'Quản lý kho' && $ma_nd) {
            // Chỉ lấy loại kho được phân quyền
            $stmt = $pdo->prepare("
                SELECT DISTINCT lk.ma_loai_kho, lk.ten_loai_kho
                FROM phan_quyen pq
                JOIN loai_kho lk ON pq.ma_loai_kho = lk.ma_loai_kho
                WHERE pq.ma_nd = ?
                ORDER BY lk.ma_loai_kho
            ");
            $stmt->execute([$ma_nd]);
            $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Admin, Ban giám đốc: thấy tất cả
            $stmt = $pdo->query("
                SELECT ma_loai_kho, ten_loai_kho
                FROM loai_kho
                ORDER BY ma_loai_kho
            ");
            $warehouses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

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

    // Pagination parameters
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = isset($_GET['limit']) ? max(1, intval($_GET['limit'])) : 10;
    $offset = ($page - 1) * $limit;

    // Get filter parameters
    $unitFilter = $_GET['unit'] ?? '';
    $regionFilter = $_GET['region'] ?? '';
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
    // Mapping đơn vị tính theo loại kho
    $unitMap = [
        'L001' => ' Kg',    // Nguyên liệu
        'L002' => ' lít',   // Nhiên liệu
        'L003' => ' cái',   // Phụ tùng
        'L004' => ' thùng'  // Thành phẩm
    ];
    $defaultUnit = ''; // Đơn vị mặc định nếu không khớp
    // Mapping loại kho sang loại hàng
    $itemTypeMap = [
        'L001' => 'M001', // Kho nguyên liệu -> Nguyên liệu
        'L002' => 'M002', // Kho nhiên liệu -> Nhiên liệu
        'L003' => 'M003', // Kho phụ tùng -> Phụ tùng
        'L004' => 'M004'  // Kho thành phẩm -> Thành phẩm
    ];
    $correspondingItemType = $itemTypeMap[$warehouse] ?? null;
    // Build WHERE clause for unit and region filters
    $additionalWhere = "";
    $countParams = [
        ':item_type' => $correspondingItemType,
        ':warehouse' => $warehouse,
        ':start'     => $startDate,
        ':end'       => $endDate
    ];

    if (!empty($unitFilter)) {
        $additionalWhere .= " AND hh.don_vi_tinh = :unit_filter";
        $countParams[':unit_filter'] = $unitFilter;
    }

    if (!empty($regionFilter)) {
        $additionalWhere .= " AND k.ma_vung = :region_filter";
        $countParams[':region_filter'] = $regionFilter;
    }

    // First, get total count for pagination
    $countStmt = $pdo->prepare("
        SELECT COUNT(*) as total
        FROM (
            SELECT hh.ma_hang
            FROM hang_hoa hh
            LEFT JOIN ct_phieu_nhap ctn ON ctn.ma_hang = hh.ma_hang
            LEFT JOIN phieu_nhap pn ON pn.ma_phieu_nhap = ctn.ma_phieu_nhap
            LEFT JOIN ct_phieu_xuat ctx ON ctx.ma_hang = hh.ma_hang
            LEFT JOIN phieu_xuat px ON px.ma_phieu_xuat = ctx.ma_phieu_xuat
            LEFT JOIN kho k ON (pn.ma_kho = k.ma_kho OR px.ma_kho = k.ma_kho)
            WHERE hh.ma_loai_hang = :item_type
              AND k.ma_loai_kho = :warehouse
              {$additionalWhere}
              AND (
                  pn.ngay_nhap BETWEEN :start AND :end OR
                  px.ngay_xuat BETWEEN :start AND :end OR
                  pn.ngay_nhap < :start OR
                  px.ngay_xuat < :start
              )
            GROUP BY hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, hh.don_gia, k.ten_kho
            HAVING (
                COALESCE(SUM(CASE WHEN pn.ngay_nhap BETWEEN :start AND :end THEN ctn.so_luong_nhap ELSE 0 END), 0) > 0 OR
                COALESCE(SUM(CASE WHEN px.ngay_xuat BETWEEN :start AND :end THEN ctx.so_luong_xuat ELSE 0 END), 0) > 0 OR
                (COALESCE(SUM(CASE WHEN pn.ngay_nhap < :start THEN ctn.so_luong_nhap ELSE 0 END), 0) -
                 COALESCE(SUM(CASE WHEN px.ngay_xuat < :start THEN ctx.so_luong_xuat ELSE 0 END), 0)) != 0
            )
        ) as temp
    ");
    $countStmt->execute($countParams);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    $totalPages = ceil($totalItems / $limit);

    // Build WHERE clause for main query
    $mainWhere = "hh.ma_loai_hang = :item_type
          AND k.ma_loai_kho = :warehouse
          {$additionalWhere}
          AND (
              pn.ngay_nhap BETWEEN :start AND :end OR
              px.ngay_xuat BETWEEN :start AND :end OR
              pn.ngay_nhap < :start OR
              px.ngay_xuat < :start
          )";

    // Lấy dữ liệu thống kê với logic tồn đầu kỳ chính xác (với pagination)
    $stmt = $pdo->prepare("
        SELECT
            hh.ma_hang AS code,
            hh.ten_hang AS name,
            hh.don_vi_tinh AS unit,
            hh.don_gia,
            k.ten_kho AS store,
            -- Tồn đầu kỳ từ bảng the_kho
            COALESCE(tk.so_luong_ton, 0) AS start_qty,
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
        LEFT JOIN the_kho tk ON tk.ma_kho = k.ma_kho AND tk.ma_hang = hh.ma_hang AND tk.ngay = (
            SELECT MAX(tk2.ngay) FROM the_kho tk2 WHERE tk2.ma_kho = k.ma_kho AND tk2.ma_hang = hh.ma_hang AND tk2.ngay < :start
        )
        WHERE {$mainWhere}
        GROUP BY hh.ma_hang, hh.ten_hang, hh.don_vi_tinh, hh.don_gia, k.ten_kho, tk.so_luong_ton
        HAVING (total_in > 0 OR total_out > 0 OR start_qty != 0)
        ORDER BY total_in DESC
        LIMIT :limit OFFSET :offset
    ");

    $stmt->bindValue(':item_type', $correspondingItemType);
    $stmt->bindValue(':warehouse', $warehouse);
    $stmt->bindValue(':start', $startDate);
    $stmt->bindValue(':end', $endDate);
    $stmt->bindValue(':icon', $iconMap[$warehouse] ?? $defaultIcon);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    if (!empty($unitFilter)) {
        $stmt->bindValue(':unit_filter', $unitFilter);
    }
    if (!empty($regionFilter)) {
        $stmt->bindValue(':region_filter', $regionFilter);
    }

    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group items by unit for summary calculation
    $itemsByUnit = [];
    foreach ($items as &$item) {
        $item['start'] = (int)$item['start_qty'];
        $item['in']    = (int)$item['total_in'];
        $item['out']   = (int)$item['total_out'];
        $item['end']   = $item['start'] + $item['in'] - $item['out'];

        // Group by unit
        $unit = $item['unit'];
        if (!isset($itemsByUnit[$unit])) {
            $itemsByUnit[$unit] = [
                'import_total' => 0,
                'export_total' => 0,
                'item_count' => 0
            ];
        }
        $itemsByUnit[$unit]['import_total'] += $item['in'];
        $itemsByUnit[$unit]['export_total'] += $item['out'];
        $itemsByUnit[$unit]['item_count']++;

        // Xóa các trường tạm thời
        unset($item['start_qty'], $item['total_in'], $item['total_out'], $item['don_gia']);
    }
    // Tính số lượng giao dịch thực tế theo các loại kho hiện có trong DB
    $typeCodes = $pdo->query("SELECT ma_loai_kho FROM loai_kho")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($typeCodes)) {
        $typeCodes = ['L001', 'L002', 'L003', 'L004'];
    }
    // Quote values for safe embedding into IN-list
    $quoted = array_map(function ($c) use ($pdo) {
        return $pdo->quote($c);
    }, $typeCodes);
    $typesList = implode(',', $quoted);
    $stmt_counts = $pdo->prepare(
        "SELECT ma_loai_kho,
                                SUM(CASE WHEN t.type = 'in' THEN t.cnt ELSE 0 END) AS in_count,
                                SUM(CASE WHEN t.type = 'out' THEN t.cnt ELSE 0 END) AS out_count
                 FROM (
                         SELECT k.ma_loai_kho, 'in' AS type, COUNT(DISTINCT pn.ma_phieu_nhap) AS cnt
                         FROM phieu_nhap pn
                         JOIN kho k ON pn.ma_kho = k.ma_kho
                         WHERE pn.ngay_nhap BETWEEN :start AND :end
                             AND k.ma_loai_kho IN ($typesList)
                         GROUP BY k.ma_loai_kho
                         UNION ALL
                         SELECT k.ma_loai_kho, 'out' AS type, COUNT(DISTINCT px.ma_phieu_xuat) AS cnt
                         FROM phieu_xuat px
                         JOIN kho k ON px.ma_kho = k.ma_kho
                         WHERE px.ngay_xuat BETWEEN :start AND :end
                             AND k.ma_loai_kho IN ($typesList)
                         GROUP BY k.ma_loai_kho
                 ) t
                 GROUP BY ma_loai_kho"
    );
    $stmt_counts->execute([':start' => $startDate, ':end' => $endDate]);
    $counts_rows = $stmt_counts->fetchAll(PDO::FETCH_ASSOC);
    // Map kết quả thành cấu trúc dễ dùng
    $counts_by_type = [];
    $typeLabels = [
        'L001' => 'Nguyên liệu',
        'L002' => 'Nhiên liệu',
        'L003' => 'Phụ tùng',
        'L004' => 'Thành phẩm'
    ];
    foreach ($counts_rows as $r) {
        $code = $r['ma_loai_kho'];
        $inC = (int)$r['in_count'];
        $outC = (int)$r['out_count'];
        $counts_by_type[$code] = [
            'label' => $typeLabels[$code] ?? $code,
            'in' => $inC,
            'out' => $outC,
            'total' => $inC + $outC
        ];
    }
    // Đảm bảo có đầy đủ 4 loại (nếu DB không trả về dòng nào)
    foreach (array_keys($typeLabels) as $tcode) {
        if (!isset($counts_by_type[$tcode])) {
            $counts_by_type[$tcode] = [
                'label' => $typeLabels[$tcode],
                'in' => 0,
                'out' => 0,
                'total' => 0
            ];
        }
    }
    // Số phiếu cho loại kho đang yêu cầu (giữ tương thích)
    $transaction_count = $counts_by_type[$warehouse]['total'] ?? 0;
    // Lấy đơn vị tính dựa trên kho
    $unit = $unitMap[$warehouse] ?? $defaultUnit;
    // Chuẩn bị dữ liệu chart theo thời gian
    $chart_data = generateChartData($pdo, $warehouse, $correspondingItemType, $startDate, $endDate, $period, $unit, $unitFilter, $regionFilter);
    // Format số lượng using PHP number_format
    function formatQuantity($num) {
        if ($num >= 1000000000) return number_format($num / 1000000000, 1);
        if ($num >= 1000000) return number_format($num / 1000000, 1);
        if ($num >= 1000) return number_format($num / 1000, 1);
        return number_format($num);
    }

    // Prepare summary data grouped by unit
    $summaryByUnit = [];
    foreach ($itemsByUnit as $unit => $data) {
        if ($data['import_total'] > 0 || $data['export_total'] > 0) {
            $summaryByUnit[] = [
                'unit' => $unit,
                'import' => formatQuantity($data['import_total']) . ' ' . $unit,
                'export' => formatQuantity($data['export_total']) . ' ' . $unit,
                'item_count' => $data['item_count']
            ];
        }
    }

    // If no unit filter is selected and we have multiple units, show totals by unit
    // If unit filter is selected, show single total for that unit
    if (!empty($unitFilter)) {
        // Single unit filter - show one total
        $importSummary = !empty($itemsByUnit[$unitFilter]['import_total']) ?
            formatQuantity($itemsByUnit[$unitFilter]['import_total']) . ' ' . $unitFilter : '—';
        $exportSummary = !empty($itemsByUnit[$unitFilter]['export_total']) ?
            formatQuantity($itemsByUnit[$unitFilter]['export_total']) . ' ' . $unitFilter : '—';
        $summaryDisplay = [
            'import' => $importSummary,
            'export' => $exportSummary,
            'count'  => $transaction_count,
            'counts_by_type' => $counts_by_type,
            'unit_totals' => $summaryByUnit
        ];
    } else {
        // No unit filter - show totals grouped by unit
        $summaryDisplay = [
            'import' => '—', // Don't show combined total when units differ
            'export' => '—',
            'count'  => $transaction_count,
            'counts_by_type' => $counts_by_type,
            'unit_totals' => $summaryByUnit
        ];
    }

    $response = [
        'chart' => $chart_data,
        'summary' => $summaryDisplay,
        'items' => $items,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_items' => $totalItems,
            'limit' => $limit,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ],
        'start_date' => $startDate,
        'end_date' => $endDate,
        'period' => $period
    ];
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
