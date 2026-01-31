<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Xử lý AJAX requests
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'get_kho') {
    // AJAX: Lấy danh sách kho theo vùng miền và loại kho
    $ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
    $ma_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
    $role = trim($_SESSION['role'] ?? '');
    $ma_nd = $_SESSION['MaND'] ?? null;
    
    $sql = "SELECT k.ma_kho, k.ten_kho FROM kho k WHERE 1=1";
    $params = [];
    
    if ($role === 'Thủ kho' && $ma_nd) {
        $sql .= " AND k.ma_nd = ?";
        $params[] = $ma_nd;
    } elseif ($role === 'Quản lý kho' && $ma_nd) {
        $sql .= " AND k.ma_kho IN (
            SELECT k2.ma_kho 
            FROM kho k2 
            JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = ?
        )";
        $params[] = $ma_nd;
    }
    
    if (!empty($ma_vung)) {
        $sql .= " AND k.ma_vung = ?";
        $params[] = $ma_vung;
    }
    
    if (!empty($ma_loai_kho)) {
        $sql .= " AND k.ma_loai_kho = ?";
        $params[] = $ma_loai_kho;
    }
    
    $sql .= " ORDER BY k.ma_kho";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

if ($action === 'get_donvi') {
    // AJAX: Lấy danh sách đơn vị tính theo kho
    $ma_kho = isset($_GET['chart2_ma_kho']) ? $_GET['chart2_ma_kho'] : '';
    
    $sql = "
        SELECT DISTINCT hh.don_vi_tinh 
        FROM hang_hoa hh
        INNER JOIN ct_phieu_nhap ct ON hh.ma_hang = ct.ma_hang
        INNER JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE pn.ma_kho = ?
        UNION
        SELECT DISTINCT hh.don_vi_tinh 
        FROM hang_hoa hh
        INNER JOIN ct_phieu_xuat ct ON hh.ma_hang = ct.ma_hang
        INNER JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE px.ma_kho = ?
        ORDER BY don_vi_tinh
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$ma_kho, $ma_kho]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$chart2_ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
$chart2_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
$chart2_ma_kho = isset($_GET['chart2_ma_kho']) ? $_GET['chart2_ma_kho'] : '';
$chart2_don_vi_tinh = isset($_GET['chart2_don_vi_tinh']) ? $_GET['chart2_don_vi_tinh'] : '';
$chart2_ngay_bat_dau = isset($_GET['chart2_ngay_bat_dau']) ? $_GET['chart2_ngay_bat_dau'] : date('Y-01-01');
$chart2_ngay_ket_thuc = isset($_GET['chart2_ngay_ket_thuc']) ? $_GET['chart2_ngay_ket_thuc'] : date('Y-m-d');

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

// Lấy danh sách kho (theo quyền và bộ lọc)
$sql_kho = "SELECT k.*, lk.ten_loai_kho FROM kho k JOIN loai_kho lk ON k.ma_loai_kho = lk.ma_loai_kho WHERE 1=1";
$params_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho .= " AND k.ma_nd = ?";
    $params_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho .= " AND k.ma_kho IN (
        SELECT k2.ma_kho 
        FROM kho k2 
        JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
        WHERE pq.ma_nd = ?
    )";
    $params_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) {
    $sql_kho .= " AND k.ma_vung = ?";
    $params_kho[] = $chart2_ma_vung;
}
if (!empty($chart2_loai_kho)) {
    $sql_kho .= " AND k.ma_loai_kho = ?";
    $params_kho[] = $chart2_loai_kho;
}
$sql_kho .= " ORDER BY k.ma_kho";
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$danh_sach_kho = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);
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

// Lấy danh sách đơn vị tính từ kho được chọn
$danh_sach_don_vi = [];
if (!empty($chart2_ma_kho)) {
    $sql_dvt = "
        SELECT DISTINCT hh.don_vi_tinh 
        FROM hang_hoa hh
        INNER JOIN ct_phieu_nhap ct ON hh.ma_hang = ct.ma_hang
        INNER JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE pn.ma_kho = ?
        UNION
        SELECT DISTINCT hh.don_vi_tinh 
        FROM hang_hoa hh
        INNER JOIN ct_phieu_xuat ct ON hh.ma_hang = ct.ma_hang
        INNER JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE px.ma_kho = ?
        ORDER BY don_vi_tinh
    ";
    $stmt_dvt = $pdo->prepare($sql_dvt);
    $stmt_dvt->execute([$chart2_ma_kho, $chart2_ma_kho]);
    $danh_sach_don_vi = $stmt_dvt->fetchAll(PDO::FETCH_ASSOC);
}

// Lấy danh sách hàng hóa
$danh_sach_hang = [];
$stmt_hang = $pdo->query("SELECT * FROM hang_hoa ORDER BY ma_hang");
$danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);

// ============ QUERY: BIỂU ĐỒ KHO THEO NGÀY ============
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

// Lấy dữ liệu nhập theo ngày (chỉ lấy phiếu đã xác nhận)
$sql_daily_nhap = "
    SELECT 
        DATE(pn.ngay_nhap) as ngay,
        SUM(ct.so_luong_nhap) as so_luong
    FROM phieu_nhap pn
    JOIN kho k ON pn.ma_kho = k.ma_kho
    JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap
    JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
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

if (!empty($chart2_ma_kho)) {
    $sql_daily_nhap .= " AND k.ma_kho = ?";
    $params_daily[] = $chart2_ma_kho;
}

if (!empty($chart2_don_vi_tinh)) {
    $sql_daily_nhap .= " AND hh.don_vi_tinh = ?";
    $params_daily[] = $chart2_don_vi_tinh;
}

$sql_daily_nhap .= " GROUP BY DATE(pn.ngay_nhap) ORDER BY pn.ngay_nhap";

$stmt_daily = $pdo->prepare($sql_daily_nhap);
$stmt_daily->execute($params_daily);
$daily_nhap = $stmt_daily->fetchAll(PDO::FETCH_ASSOC);

// Lấy dữ liệu xuất theo ngày (chỉ lấy phiếu đã xác nhận)
$sql_daily_xuat = "
    SELECT 
        DATE(px.ngay_xuat) as ngay,
        SUM(ct.so_luong_xuat) as so_luong
    FROM phieu_xuat px
    JOIN kho k ON px.ma_kho = k.ma_kho
    JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat
    JOIN hang_hoa hh ON ct.ma_hang = hh.ma_hang
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

if (!empty($chart2_ma_kho)) {
    $sql_daily_xuat .= " AND k.ma_kho = ?";
    $params_daily_xuat[] = $chart2_ma_kho;
}

if (!empty($chart2_don_vi_tinh)) {
    $sql_daily_xuat .= " AND hh.don_vi_tinh = ?";
    $params_daily_xuat[] = $chart2_don_vi_tinh;
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
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu Đồ Hàng Hóa - Báo Cáo Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Nếu đã build Tailwind → thay bằng: <link href="/css/output.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.0/dist/chart.min.js"></script>
</head>

<body class="bg-background-light dark:bg-background-dark text-slate-800 dark:text-slate-200 transition-colors duration-200 h-screen flex flex-col overflow-hidden">

    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">

        <?php include '../include/header.php'; ?>

        <main class="flex-1 overflow-y-auto bg-white dark:bg-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 lg:py-8">

                <!-- Nút quay lại -->
                <div class="mb-6">
                    <a href="baocao_bancandoi.php"
                       class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                        ← Quay lại
                    </a>
                </div>

                <!-- Tiêu đề -->
                <div class="text-center mb-10">
                    <h1 class="text-3xl md:text-4xl font-bold text-blue-500 dark:text-blue-400 tracking-tight">
                        📊 BIỂU ĐỒ BIẾN ĐỘNG THEO KHO
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Xem biến động nhập/xuất theo từng kho theo đơn vị tính
                        <?php if (!empty($chart2_don_vi_tinh)): ?>
                            - <strong><?= htmlspecialchars($chart2_don_vi_tinh) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- Bộ lọc -->
                <div class="bg-gray-100 dark:bg-gray-700 rounded-xl shadow-md p-6 mb-10">
                    <h3 class="text-xl font-semibold mb-5 text-gray-800 dark:text-gray-100">
                        Bộ lọc biểu đồ
                    </h3>

                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4" id="filterForm">

                        <!-- Vùng miền -->
                        <div>
                            <label for="chart2_ma_vung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Vùng miền
                            </label>
                            <select name="chart2_ma_vung" id="chart2_ma_vung"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                    <option value="<?= htmlspecialchars($v['ma_vung']) ?>"
                                        <?= $chart2_ma_vung === $v['ma_vung'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['ten_vung']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Loại kho -->
                        <div>
                            <label for="chart2_loai_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Loại kho
                            </label>
                            <select name="chart2_loai_kho" id="chart2_loai_kho" onchange="updateKhoList()"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                    <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"
                                        <?= $chart2_loai_kho === $lk['ma_loai_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lk['ten_loai_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Kho -->
                        <div>
                            <label for="chart2_ma_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Kho
                            </label>
                            <select name="chart2_ma_kho" id="chart2_ma_kho" onchange="updateDonViList()"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_kho as $k): ?>
                                    <option value="<?= htmlspecialchars($k['ma_kho']) ?>"
                                        <?= $chart2_ma_kho === $k['ma_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($k['ma_kho'] . ' - ' . $k['ten_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Đơn vị tính -->
                        <div>
                            <label for="chart2_don_vi_tinh" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Đơn vị tính
                            </label>
                            <select name="chart2_don_vi_tinh" id="chart2_don_vi_tinh"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_don_vi as $dv): ?>
                                    <option value="<?= htmlspecialchars($dv['don_vi_tinh']) ?>"
                                        <?= $chart2_don_vi_tinh === $dv['don_vi_tinh'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dv['don_vi_tinh']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Từ ngày -->
                        <div>
                            <label for="chart2_ngay_bat_dau" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Từ ngày
                            </label>
                            <input type="date" name="chart2_ngay_bat_dau" id="chart2_ngay_bat_dau"
                                   value="<?= htmlspecialchars($chart2_ngay_bat_dau) ?>"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Đến ngày -->
                        <div>
                            <label for="chart2_ngay_ket_thuc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Đến ngày
                            </label>
                            <input type="date" name="chart2_ngay_ket_thuc" id="chart2_ngay_ket_thuc"
                                   value="<?= htmlspecialchars($chart2_ngay_ket_thuc) ?>"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Nút lọc + xuất excel -->
                        <div class="flex flex-col justify-end gap-3 sm:flex-row sm:items-end lg:col-span-2 xl:col-span-1 xl:flex-col xl:items-stretch">
                            <button type="submit"
                                    class="px-6 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                                Lọc dữ liệu
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Khu vực biểu đồ -->
                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6">
                    <div class="h-[450px] md:h-[500px]">
                        <canvas id="chartHangHoa"></canvas>
                    </div>
                </div>

            </div>
        </main>

    </div>

    <script>
    // Cập nhật danh sách kho khi chọn loại kho (dùng AJAX)
    function updateKhoList() {
        const loaiKhoSelect = document.getElementById('chart2_loai_kho');
        const maVungSelect = document.getElementById('chart2_ma_vung');
        const maKhoSelect = document.getElementById('chart2_ma_kho');
        const maLoaiKho = loaiKhoSelect.value;
        const maVung = maVungSelect.value;

        if (!maLoaiKho) {
            // Nếu không chọn loại kho, reload toàn bộ form
            document.getElementById('filterForm').submit();
            return;
        }

        // AJAX call để lấy danh sách kho
        fetch('baocao_chart1_loai_kho.php?action=get_kho&chart2_ma_vung=' + encodeURIComponent(maVung) + '&chart2_loai_kho=' + encodeURIComponent(maLoaiKho))
            .then(response => response.json())
            .then(data => {
                maKhoSelect.innerHTML = '<option value="">-- Tất cả --</option>';
                data.forEach(kho => {
                    const option = document.createElement('option');
                    option.value = kho.ma_kho;
                    option.textContent = kho.ma_kho + ' - ' + kho.ten_kho;
                    maKhoSelect.appendChild(option);
                });
                // Reset đơn vị tính
                const donViSelect = document.getElementById('chart2_don_vi_tinh');
                donViSelect.innerHTML = '<option value="">-- Tất cả --</option>';
            })
            .catch(error => console.error('Error:', error));
    }

    // Cập nhật danh sách đơn vị tính khi chọn kho (dùng AJAX)
    function updateDonViList() {
        const maKhoSelect = document.getElementById('chart2_ma_kho');
        const maKho = maKhoSelect.value;
        const donViSelect = document.getElementById('chart2_don_vi_tinh');

        if (!maKho) {
            donViSelect.innerHTML = '<option value="">-- Tất cả --</option>';
            return;
        }

        // AJAX call để lấy danh sách đơn vị tính
        fetch('baocao_chart1_loai_kho.php?action=get_donvi&chart2_ma_kho=' + encodeURIComponent(maKho))
            .then(response => response.json())
            .then(data => {
                donViSelect.innerHTML = '<option value="">-- Tất cả --</option>';
                data.forEach(dv => {
                    const option = document.createElement('option');
                    option.value = dv.don_vi_tinh;
                    option.textContent = dv.don_vi_tinh;
                    donViSelect.appendChild(option);
                });
            })
            .catch(error => console.error('Error:', error));
    }

    // Dữ liệu biểu đồ
    const dataNgay = <?php echo json_encode($danh_sach_ngay); ?>;
    const labelsNgay = Object.keys(dataNgay);
    const nhapNgay = labelsNgay.map(ngay => dataNgay[ngay].nhap);
    const xuatNgay = labelsNgay.map(ngay => dataNgay[ngay].xuat);
    const tonNgay = labelsNgay.map(ngay => dataNgay[ngay].ton);
    const donViTinh = "<?= htmlspecialchars($chart2_don_vi_tinh) ?>";

    const ctxHang = document.getElementById('chartHangHoa').getContext('2d');
    new Chart(ctxHang, {
        type: 'line',
        data: {
            labels: labelsNgay,
            datasets: [
                {
                    label: 'Tồn cuối kỳ' + (donViTinh ? ' (' + donViTinh + ')' : ''),
                    data: tonNgay,
                    borderColor: '#007bff',
                    backgroundColor: 'rgba(0, 123, 255, 0.1)',
                    borderWidth: 2,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 3,
                    pointBackgroundColor: '#007bff'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Biến động theo ngày' + (donViTinh ? ' (Đơn vị: ' + donViTinh + ')' : '')
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
                x: {
                    title: {
                        display: true,
                        text: 'Ngày'
                    }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value.toLocaleString('vi-VN');
                        }
                    }
                }
            }
        }
    });
    </script>

</body>
</html>