<?php
// thekho.php - Giao diện mới với danh sách mặt hàng và chi tiết
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thekho');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Bộ lọc loại kho
$ma_loai_kho_filter = $_GET['ma_loai_kho'] ?? '';

// Mapping loại kho → loại hàng
$loai_kho_to_hang = [
    'L001' => 'M001', // Kho nguyên liệu → Nguyên liệu
    'L002' => 'M002', // Kho nhiên liệu → Nhiên liệu
    'L003' => 'M003', // Kho phụ tùng → Phụ tùng
    'L004' => 'M004'  // Kho thành phẩm → Thành phẩm
];

// Lấy danh sách loại kho (chỉ các loại kho mà user có quyền)
$sql_loai_kho = "
    SELECT DISTINCT lk.ma_loai_kho, lk.ten_loai_kho
    FROM loai_kho lk
    JOIN kho k ON lk.ma_loai_kho = k.ma_loai_kho
    WHERE 1=1
";
$loai_kho_params = [];

if ($role === 'Thủ kho' && $ma_nd) {
    $sql_loai_kho .= ' AND k.ma_nd = :ma_nd';
    $loai_kho_params[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_loai_kho .= ' AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = :ma_nd
    )';
    $loai_kho_params[':ma_nd'] = $ma_nd;
}
// Admin và Ban giám đốc thấy hết

$sql_loai_kho .= " ORDER BY lk.ten_loai_kho";
$stmt_loai_kho = $pdo->prepare($sql_loai_kho);
foreach ($loai_kho_params as $key => $value) {
    $stmt_loai_kho->bindValue($key, $value);
}
$stmt_loai_kho->execute();
$loai_kho_list = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// Xây dựng điều kiện lọc kho theo quyền
$kho_condition = '';
$kho_params = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_nd = :ma_nd';
    $kho_params[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $kho_condition = 'AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = :ma_nd
    )';
    $kho_params[':ma_nd'] = $ma_nd;
}
// Admin và Ban giám đốc không có điều kiện

// Lấy danh sách mặt hàng theo loại kho được chọn (chỉ mặt hàng có trong các kho mà user có quyền)
$hang_hoa_list = [];
if ($ma_loai_kho_filter && isset($loai_kho_to_hang[$ma_loai_kho_filter])) {
    $ma_loai_hang = $loai_kho_to_hang[$ma_loai_kho_filter];
    
    $sql_hang = "
        SELECT DISTINCT
            h.ma_hang,
            h.ten_hang,
            h.muc_du_tru_min,
            h.muc_du_tru_max,
            h.don_vi_tinh
        FROM hang_hoa h
        JOIN the_kho tk ON h.ma_hang = tk.ma_hang
        JOIN kho k ON tk.ma_kho = k.ma_kho
        WHERE h.ma_loai_hang = :ma_loai_hang AND k.ma_loai_kho = :ma_loai_kho
        $kho_condition
        ORDER BY h.ten_hang
    ";
    $stmt_hang = $pdo->prepare($sql_hang);
    $stmt_hang->bindValue(':ma_loai_hang', $ma_loai_hang);
    $stmt_hang->bindValue(':ma_loai_kho', $ma_loai_kho_filter);
    foreach ($kho_params as $key => $value) {
        $stmt_hang->bindValue($key, $value);
    }
    $stmt_hang->execute();
    $hang_hoa_list = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy thông tin chi tiết mặt hàng được chọn (nếu có)
$ma_hang_selected = $_GET['ma_hang'] ?? '';
$hang_selected = null;
$tong_ton = 0;
$trang_thai = '';

if ($ma_hang_selected) {
    // Lấy thông tin mặt hàng
    $sql_hang_info = "SELECT * FROM hang_hoa WHERE ma_hang = :ma_hang_info";
    $stmt_hang_info = $pdo->prepare($sql_hang_info);
    $stmt_hang_info->bindValue(':ma_hang_info', $ma_hang_selected);
    $stmt_hang_info->execute();
    $hang_selected = $stmt_hang_info->fetch(PDO::FETCH_ASSOC);
    
    if ($hang_selected) {
        // Tính tổng số lượng tồn từ các kho mà user có quyền
        $sql_tong_ton = "
            SELECT 
                tk.ma_kho,
                k.ten_kho,
                tk.so_luong_ton
            FROM the_kho tk
            JOIN (
                SELECT ma_kho, ma_hang, MAX(ngay) as max_ngay, MAX(ma_the_kho) as max_ma
                FROM the_kho
                WHERE ma_hang = :ma_hang_ton1
                GROUP BY ma_kho, ma_hang
            ) latest ON tk.ma_kho = latest.ma_kho 
                    AND tk.ma_hang = latest.ma_hang
                    AND tk.ngay = latest.max_ngay
                    AND tk.ma_the_kho = latest.max_ma
            JOIN kho k ON tk.ma_kho = k.ma_kho
            WHERE tk.ma_hang = :ma_hang_ton2
            $kho_condition
        ";
        $stmt_tong_ton = $pdo->prepare($sql_tong_ton);
        $stmt_tong_ton->bindValue(':ma_hang_ton1', $ma_hang_selected);
        $stmt_tong_ton->bindValue(':ma_hang_ton2', $ma_hang_selected);
        foreach ($kho_params as $key => $value) {
            $stmt_tong_ton->bindValue($key, $value);
        }
        $stmt_tong_ton->execute();
        $ton_kho_list = $stmt_tong_ton->fetchAll(PDO::FETCH_ASSOC);
        
        // Tính tổng
        $tong_ton = array_sum(array_column($ton_kho_list, 'so_luong_ton'));
        
        // Xác định trạng thái
        $min = (int)($hang_selected['muc_du_tru_min'] ?? 0);
        $max = (int)($hang_selected['muc_du_tru_max'] ?? 0);
        
        if ($tong_ton < $min) {
            $trang_thai = 'duoi_min'; // Dưới min
        } elseif ($tong_ton > $max) {
            $trang_thai = 'vuot_max'; // Vượt max
        } else {
            $trang_thai = 'an_toan'; // An toàn
        }
    }
}

// Lấy log biến động nếu có mặt hàng được chọn (chỉ từ các kho mà user có quyền)
$log_bien_dong = [];
if ($ma_hang_selected) {
    // Xây dựng điều kiện lọc kho cho log
    $log_kho_condition_nhap = '';
    $log_kho_condition_xuat = '';
    $log_kho_params = [];
    
    if ($role === 'Thủ kho' && $ma_nd) {
        $log_kho_condition_nhap = 'AND pn.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = :log_ma_nd)';
        $log_kho_condition_xuat = 'AND px.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = :log_ma_nd)';
        $log_kho_params[':log_ma_nd'] = $ma_nd;
    } elseif ($role === 'Quản lý kho' && $ma_nd) {
        $log_kho_condition_nhap = 'AND pn.ma_kho IN (
            SELECT k2.ma_kho 
            FROM kho k2 
            JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = :log_ma_nd
        )';
        $log_kho_condition_xuat = 'AND px.ma_kho IN (
            SELECT k2.ma_kho 
            FROM kho k2 
            JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = :log_ma_nd
        )';
        $log_kho_params[':log_ma_nd'] = $ma_nd;
    }
    $sql_log = "
    SELECT
        e.ngay,
        DATE_FORMAT(e.ngay, '%d/%m/%Y') AS ngay_format,
        e.loai_phat_sinh,
        e.so_ct,
        e.so_luong_nhap,
        e.so_luong_xuat,
        e.ma_kho,
        k.ten_kho,
        tk.so_luong_ton AS ton_sau
    FROM (
        SELECT
            pn.ngay_nhap AS ngay,
            pn.ma_phieu_nhap AS so_ct,
            'Nhập' AS loai_phat_sinh,
            ctpn.so_luong_nhap AS so_luong_nhap,
            0 AS so_luong_xuat,
            pn.ma_kho,
            ctpn.ma_hang
        FROM ct_phieu_nhap ctpn
        JOIN phieu_nhap pn ON ctpn.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE ctpn.ma_hang = :ma_hang_nhap AND pn.trang_thai = 'da_xac_nhan'
        $log_kho_condition_nhap

        UNION ALL

        SELECT
            px.ngay_xuat AS ngay,
            px.ma_phieu_xuat AS so_ct,
            'Xuất' AS loai_phat_sinh,
            0 AS so_luong_nhap,
            ctpx.so_luong_xuat AS so_luong_xuat,
            px.ma_kho,
            ctpx.ma_hang
        FROM ct_phieu_xuat ctpx
        JOIN phieu_xuat px ON ctpx.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE ctpx.ma_hang = :ma_hang_xuat AND px.trang_thai = 'da_xac_nhan'
        $log_kho_condition_xuat
    ) e
    LEFT JOIN kho k ON e.ma_kho = k.ma_kho
    LEFT JOIN the_kho tk ON tk.so_ct = e.so_ct 
        AND tk.ma_hang = e.ma_hang 
        AND tk.ma_kho = e.ma_kho
        -- Bỏ hoặc comment dòng dưới nếu loai_phat_sinh không khớp
        -- AND tk.loai_phat_sinh = CASE WHEN e.loai_phat_sinh = 'Nhập' THEN 'Nhập kho' ELSE 'Xuất kho' END
    ORDER BY e.ngay DESC, e.so_ct DESC
    LIMIT 100
";
    $stmt_log = $pdo->prepare($sql_log);
    $stmt_log->bindValue(':ma_hang_nhap', $ma_hang_selected);
    $stmt_log->bindValue(':ma_hang_xuat', $ma_hang_selected);
    foreach ($log_kho_params as $key => $value) {
        $stmt_log->bindValue($key, $value);
    }
    $stmt_log->execute();
    $log_bien_dong = $stmt_log->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Quản lý Thẻ kho</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: #475569;
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 transition-colors duration-200 h-screen flex flex-col overflow-hidden">
    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include '../include/header.php'; ?>

        <main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-background-light dark:bg-background-dark">
            <!-- Header + bộ lọc -->
            <div class="px-6 py-5 border-b border-border-light dark:border-border-dark bg-surface-light dark:bg-surface-dark flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Thẻ kho</h2>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Bộ lọc loại kho -->
                    <div class="relative">
                        <select id="filter-loai-kho" onchange="filterByLoaiKho()"
                            class="pl-3 pr-10 py-2 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white appearance-none cursor-pointer focus:ring-2 focus:ring-primary">
                            <option value="">-- Chọn loại kho --</option>
                            <?php foreach ($loai_kho_list as $lk): ?>
                                <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>" 
                                    <?= $ma_loai_kho_filter === $lk['ma_loai_kho'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lk['ten_loai_kho']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Nội dung chính: 2 cột -->
            <div class="flex-1 flex overflow-hidden">
                <!-- Cột trái: Danh sách mặt hàng -->
                <div class="w-1/5 border-r border-border-light dark:border-border-dark bg-surface-light dark:bg-surface-dark overflow-hidden flex flex-col">
                    <div class="px-6 py-4 border-b border-border-light dark:border-border-dark bg-gray-50 dark:bg-slate-800/50">
                        <h3 class="font-semibold text-slate-800 dark:text-white">Danh sách mặt hàng</h3>
                        <?php if ($ma_loai_kho_filter): ?>
                            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                Loại kho: <?= htmlspecialchars($loai_kho_list[array_search($ma_loai_kho_filter, array_column($loai_kho_list, 'ma_loai_kho'))]['ten_loai_kho'] ?? '') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex-1 overflow-y-auto">
                        <?php if (empty($hang_hoa_list)): ?>
                            <div class="px-6 py-12 text-center text-slate-500">
                                <?php if ($ma_loai_kho_filter): ?>
                                    Chưa có mặt hàng nào thuộc loại kho này
                                <?php else: ?>
                                    Vui lòng chọn loại kho để xem danh sách mặt hàng
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="divide-y divide-border-light dark:divide-border-dark">
                                <?php foreach ($hang_hoa_list as $hang): ?>
                                    <?php
                                    // Tính tổng tồn cho mặt hàng này (chỉ từ các kho mà user có quyền)
                                    $sql_ton_hang = "
                                        SELECT tk.so_luong_ton
                                        FROM the_kho tk
                                        JOIN (
                                            SELECT ma_kho, ma_hang, MAX(ngay) as max_ngay, MAX(ma_the_kho) as max_ma
                                            FROM the_kho
                                            WHERE ma_hang = :ma_hang_list1
                                            GROUP BY ma_kho, ma_hang
                                        ) latest ON tk.ma_kho = latest.ma_kho 
                                                AND tk.ma_hang = latest.ma_hang
                                                AND tk.ngay = latest.max_ngay
                                                AND tk.ma_the_kho = latest.max_ma
                                        JOIN kho k ON tk.ma_kho = k.ma_kho
                                        WHERE tk.ma_hang = :ma_hang_list2
                                        $kho_condition
                                    ";
                                    $stmt_ton_hang = $pdo->prepare($sql_ton_hang);
                                    $stmt_ton_hang->bindValue(':ma_hang_list1', $hang['ma_hang']);
                                    $stmt_ton_hang->bindValue(':ma_hang_list2', $hang['ma_hang']);
                                    foreach ($kho_params as $key => $value) {
                                        $stmt_ton_hang->bindValue($key, $value);
                                    }
                                    $stmt_ton_hang->execute();
                                    $ton_list = $stmt_ton_hang->fetchAll(PDO::FETCH_COLUMN);
                                    $tong_ton_hang = array_sum($ton_list);
                                    
                                    // Xác định trạng thái
                                    $min_hang = (int)($hang['muc_du_tru_min'] ?? 0);
                                    $max_hang = (int)($hang['muc_du_tru_max'] ?? 0);
                                    $trang_thai_hang = '';
                                    if ($tong_ton_hang < $min_hang) {
                                        $trang_thai_hang = 'duoi_min';
                                    } elseif ($tong_ton_hang > $max_hang) {
                                        $trang_thai_hang = 'vuot_max';
                                    } else {
                                        $trang_thai_hang = 'an_toan';
                                    }
                                    ?>
                                    <a href="?ma_loai_kho=<?= urlencode($ma_loai_kho_filter) ?>&ma_hang=<?= urlencode($hang['ma_hang']) ?>"
                                       class="block px-6 py-4 hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors <?= $ma_hang_selected === $hang['ma_hang'] ? 'bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500' : '' ?>">
                                        <div class="flex items-center justify-between">
                                            <div class="flex-1">
                                                <h4 class="font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($hang['ten_hang']) ?></h4>
                                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Mã: <?= htmlspecialchars($hang['ma_hang']) ?></p>
                                            </div>
                                            <div class="text-right ml-4">
                                                <div class="text-sm font-semibold <?= $tong_ton_hang <= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                                    <?= number_format($tong_ton_hang) ?>
                                                </div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                                    <?php if ($trang_thai_hang === 'an_toan'): ?>
                                                        <span class="inline-block w-2 h-2 rounded-full bg-green-500"></span>
                                                    <?php elseif ($trang_thai_hang === 'vuot_max'): ?>
                                                        <span class="text-red-600 font-bold">+</span>
                                                    <?php else: ?>
                                                        <span class="text-red-600 font-bold">-</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Cột phải: Chi tiết mặt hàng và log biến động -->
                <div class="flex-1 overflow-hidden flex flex-col">
                    <?php if ($hang_selected): ?>
                        <!-- Thông tin chi tiết mặt hàng -->
                        <div class="px-6 py-5 border-b border-border-light dark:border-border-dark bg-surface-light dark:bg-surface-dark">
                            <h3 class="text-xl font-bold text-slate-800 dark:text-white mb-4">Thông tin mặt hàng</h3>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Mã hàng</label>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($hang_selected['ma_hang']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Tên hàng</label>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white"><?= htmlspecialchars($hang_selected['ten_hang']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Mức dự trữ Min</label>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white"><?= number_format($hang_selected['muc_du_tru_min']) ?> <?= htmlspecialchars($hang_selected['don_vi_tinh']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Mức dự trữ Max</label>
                                    <p class="text-sm font-medium text-slate-900 dark:text-white"><?= number_format($hang_selected['muc_du_tru_max']) ?> <?= htmlspecialchars($hang_selected['don_vi_tinh']) ?></p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Số lượng tồn tổng</label>
                                    <p class="text-lg font-bold <?= $tong_ton <= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                        <?= number_format($tong_ton) ?> <?= htmlspecialchars($hang_selected['don_vi_tinh']) ?>
                                    </p>
                                </div>
                                <div>
                                    <label class="text-xs text-slate-500 dark:text-slate-400">Trạng thái</label>
                                    <div class="mt-1">
                                        <?php if ($trang_thai === 'an_toan'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                                <span class="w-2 h-2 rounded-full bg-green-500 mr-2"></span>
                                                An toàn
                                            </span>
                                        <?php elseif ($trang_thai === 'vuot_max'): ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                <span class="font-bold mr-1">+</span>
                                                Nguy hiểm (vượt max)
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                                <span class="font-bold mr-1">-</span>
                                                Nguy hiểm (dưới min)
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Log biến động -->
                       <!-- Log biến động -->
                        <div class="flex-1 overflow-y-auto px-6 py-5">
                            <h3 class="text-lg font-semibold text-slate-800 dark:text-white mb-4">Lịch sử biến động</h3>
                            <?php if (empty($log_bien_dong)): ?>
                                <div class="text-center py-10 text-slate-500">Chưa có biến động nào</div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="w-full text-sm text-left min-w-max bg-surface-light">
                                        <thead class="text-xs uppercase bg-gray-50 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                            <tr>
                                                <th class="px-4 py-3">Ngày</th>
                                                <th class="px-4 py-3">Số CT</th> <!-- Cột mới: Số chứng từ -->
                                                <th class="px-4 py-3">Mã kho</th>
                                                <th class="px-4 py-3 text-right">Số lượng nhập</th>
                                                <th class="px-4 py-3 text-right">Số lượng xuất</th>
                                                <th class="px-4 py-3 text-right">Tồn sau</th> <!-- Số lượng tồn sau log, lấy từ the_kho -->
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-border-light dark:divide-border-dark">
                                            <?php foreach ($log_bien_dong as $log): ?>
                                                <tr class="hover:bg-gray-50 dark:hover:bg-slate-800/50 transition-colors">
                                                    <td class="px-4 py-3 text-slate-900 dark:text-white"><?= htmlspecialchars($log['ngay_format']) ?></td>
                                                    <td class="px-4 py-3 text-slate-900 dark:text-white">
                                                        <?= htmlspecialchars($log['so_ct'] ?? 'N/A') ?> <!-- Hiển thị số CT (ví dụ: PN-VT-001 hoặc PX-VT-001) -->
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="text-slate-900 dark:text-white"><?= htmlspecialchars($log['ma_kho']) ?></span>
                                                        <?php if (!empty($log['ten_kho'])): ?>
                                                            <span class="block text-xs text-slate-500 dark:text-slate-400"><?= htmlspecialchars($log['ten_kho']) ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <?php if ($log['so_luong_nhap'] > 0): ?>
                                                            <span class="text-green-600 font-medium">+<?= number_format($log['so_luong_nhap']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-right">
                                                        <?php if ($log['so_luong_xuat'] > 0): ?>
                                                            <span class="text-red-600 font-medium">-<?= number_format($log['so_luong_xuat']) ?></span>
                                                        <?php else: ?>
                                                            <span class="text-slate-400">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-4 py-3 text-right font-semibold <?= ($log['ton_sau'] ?? 0) <= 0 ? 'text-red-600' : 'text-green-600' ?>">
                                                        <?= number_format($log['ton_sau'] ?? 0) ?> <!-- Số lượng tồn sau log, lấy từ the_kho.so_luong_ton -->
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="flex-1 flex items-center justify-center">
                            <div class="text-center text-slate-500">
                                <span class="material-icons text-6xl mb-4 opacity-50">inventory_2</span>
                                <p class="text-lg">Chọn một mặt hàng để xem chi tiết</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        function filterByLoaiKho() {
            const maLoaiKho = document.getElementById('filter-loai-kho').value;
            if (maLoaiKho) {
                window.location.href = '?ma_loai_kho=' + encodeURIComponent(maLoaiKho);
            } else {
                window.location.href = 'thekho.php';
            }
        }
    </script>
</body>
</html>
