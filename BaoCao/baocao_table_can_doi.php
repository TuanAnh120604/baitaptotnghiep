<?php
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

// Lấy danh sách vùng miền (theo quyền)
$sql_vung = "SELECT * FROM vung_mien WHERE 1=1";
$params_vung = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM kho WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM phan_quyen WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
}
$sql_vung .= " ORDER BY ten_vung";
$stmt_vung = $pdo->prepare($sql_vung);
$stmt_vung->execute($params_vung);
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách loại kho (theo quyền)
$sql_loai_kho = "SELECT DISTINCT lk.* FROM loai_kho lk JOIN kho k ON lk.ma_loai_kho = k.ma_loai_kho WHERE 1=1";
$params_loai_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_nd = ?";
    $params_loai_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )";
    $params_loai_kho[] = $ma_nd;
}
$sql_loai_kho .= " ORDER BY lk.ma_loai_kho";
$stmt_loai_kho = $pdo->prepare($sql_loai_kho);
$stmt_loai_kho->execute($params_loai_kho);
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// Lấy danh sách kho dựa trên vùng miền và loại kho được chọn
$danh_sach_kho = [];
$sql_kho = "SELECT k.* FROM kho k JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho WHERE 1=1";
$params_kho = [];

if (!empty($table_ma_vung)) {
    $sql_kho .= " AND k.ma_vung = ?";
    $params_kho[] = $table_ma_vung;
}

if (!empty($table_loai_kho)) {
    $sql_kho .= " AND k.ma_loai_kho = ?";
    $params_kho[] = $table_loai_kho;
}

if (!empty($kho_condition)) {
    $sql_kho .= " " . $kho_condition;
    $params_kho = array_merge($params_kho, $kho_params);
}

$sql_kho .= " ORDER BY k.ma_kho";
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$danh_sach_kho = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);

// ============ QUERY: BẢNG CÂN ĐỐI KHO CHI TIẾT ============
// Lấy dữ liệu từ bảng the_kho (thẻ kho) - bảng ghi chép chính xác tồn kho
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
        ), 0) as luong_nhap,
        COALESCE((
            SELECT SUM(ct.so_luong_xuat) 
            FROM ct_phieu_xuat ct
            JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
            WHERE ct.ma_hang = hh.ma_hang 
            AND px.ma_kho = k.ma_kho
            AND px.ngay_xuat >= ? 
            AND px.ngay_xuat <= ?
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


?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bảng Cân Đối - Báo Cáo Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Nếu bạn đã build Tailwind → thay bằng link file output.css -->
    <!-- <link href="/css/output.css" rel="stylesheet"> -->
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 transition-colors duration-200 h-screen flex flex-col overflow-hidden">

    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">

        <?php include '../include/header.php'; ?>

        <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">

                <!-- Nút quay lại -->
                <div class="mb-6">
                    <a href="baocao_bancandoi.php"
                       class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-200">
                        ← Quay lại 
                    </a>
                </div>

                <!-- Tiêu đề -->
                <div class="text-center mb-10">
                    <h1 class="text-3xl sm:text-4xl font-bold text-blue-500 dark:text-blue-400 tracking-tight">
                        📋 BẢNG CÂN ĐỐI KHO CHI TIẾT
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Xem chi tiết tồn kho, nhập, xuất cho từng hàng hóa
                    </p>
                </div>

                <!-- Bộ lọc -->
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl shadow-md p-6 mb-10 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800 dark:text-gray-100">
                        Bộ lọc bảng
                    </h3>

                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5" id="filterForm">

                        <div>
                            <label for="table_ma_vung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Vùng miền
                            </label>
                            <select name="table_ma_vung" id="table_ma_vung"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                    <option value="<?= htmlspecialchars($v['ma_vung']) ?>"
                                        <?= $table_ma_vung == $v['ma_vung'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['ten_vung']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="table_loai_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Loại kho
                            </label>
                            <select name="table_loai_kho" id="table_loai_kho"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                    <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"
                                        <?= $table_loai_kho == $lk['ma_loai_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lk['ten_loai_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="table_ma_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Kho
                            </label>
                            <select name="table_ma_kho" id="table_ma_kho"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_kho as $kho): ?>
                                    <option value="<?= htmlspecialchars($kho['ma_kho']) ?>"
                                        <?= $table_ma_kho == $kho['ma_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($kho['ten_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="table_ngay_bat_dau" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Từ ngày
                            </label>
                            <input type="date" name="table_ngay_bat_dau" id="table_ngay_bat_dau"
                                   value="<?= htmlspecialchars($table_ngay_bat_dau) ?>"
                                   class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                        </div>

                        <div>
                            <label for="table_ngay_ket_thuc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Đến ngày
                            </label>
                            <input type="date" name="table_ngay_ket_thuc" id="table_ngay_ket_thuc"
                                   value="<?= htmlspecialchars($table_ngay_ket_thuc) ?>"
                                   class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row sm:items-end lg:col-span-2 xl:col-span-1 xl:flex-col xl:items-stretch">
                            <button type="submit"
                                    class="px-6 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                                Lọc dữ liệu
                            </button>

                            <a href="xuat_excel_table_can_doi.php?<?= http_build_query($_GET) ?>"
                               class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow transition-colors text-center">
                                📥 Xuất Excel
                            </a>
                        </div>

                    </form>
                </div>

                <!-- Bảng cân đối kho -->
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700 shadow-md">
                    <div class="max-h-[calc(100vh-420px)] overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-800 sticky top-0 z-10">
                                <tr>
                                    <th style="width: 8%;" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Mã hàng</th>
                                    <th style="width: 20%;" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Tên hàng</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Đơn vị tính</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Loại kho</th>
                                    <th style="width: 12%;" class="px-4 py-3 text-left text-sm font-semibold text-gray-700 dark:text-gray-300">Tên kho</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Tồn đầu kỳ</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Lượng nhập</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Lượng xuất</th>
                                    <th style="width: 10%;" class="px-4 py-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-300">Tồn cuối kỳ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-900">
                                <?php 
                        $tong_ton_dau = 0;
                        $tong_nhap = 0;
                        $tong_xuat = 0;
                        $tong_ton_cuoi = 0;
                        
                        foreach ($ket_qua as $row): 
                            $ton_cuoi_ky = (int)$row['ton_dau_ky'] + (int)$row['luong_nhap'] - (int)$row['luong_xuat'];
                            $tong_ton_dau += (int)$row['ton_dau_ky'];
                            $tong_nhap += (int)$row['luong_nhap'];
                            $tong_xuat += (int)$row['luong_xuat'];
                            $tong_ton_cuoi += $ton_cuoi_ky;
                        ?>
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['ma_hang']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['ten_hang']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['don_vi_tinh']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['ten_loai_kho']); ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200"><?php echo htmlspecialchars($row['ten_kho']); ?></td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-gray-200"><strong><?php echo (int)$row['ton_dau_ky']; ?></strong></td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-gray-200"><strong><?php echo (int)$row['luong_nhap']; ?></strong></td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-gray-200"><strong><?php echo (int)$row['luong_xuat']; ?></strong></td>
                                    <td class="px-4 py-3 text-right text-sm font-medium text-gray-900 dark:text-gray-200"><strong><?php echo $ton_cuoi_ky; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-100 dark:bg-gray-800 sticky bottom-0 z-10 font-bold">
                                <tr style="background-color: #f0f0f0; font-weight: bold;">
                                    <td colspan="5" class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-200">TỔNG CỘNG:</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-200"><?php echo $tong_ton_dau; ?></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-200"><?php echo $tong_nhap; ?></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-200"><?php echo $tong_xuat; ?></td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-900 dark:text-gray-200"><?php echo $tong_ton_cuoi; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

            </div>
        </main>

    </div>

</body>
</html>