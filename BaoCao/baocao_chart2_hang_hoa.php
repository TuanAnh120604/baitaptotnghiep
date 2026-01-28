<?php
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

// Mapping giữa loại kho và loại hàng
$mapping_loai_kho_hang = [
    'L001' => 'M001',
    'L002' => 'M002',
    'L003' => 'M003',
    'L004' => 'M004'
];

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

// Lấy danh sách hàng hóa
$danh_sach_hang = [];
if (!empty($chart2_loai_kho) && isset($mapping_loai_kho_hang[$chart2_loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$chart2_loai_kho];
    $stmt_hang = $pdo->prepare("SELECT * FROM hang_hoa WHERE ma_loai_hang = ? ORDER BY ma_hang");
    $stmt_hang->execute([$ma_loai_hang]);
    $danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_hang = $pdo->query("SELECT * FROM hang_hoa ORDER BY ma_hang");
    $danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);
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

// Lấy dữ liệu nhập theo ngày (chỉ lấy phiếu đã xác nhận)
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

// Lấy dữ liệu xuất theo ngày (chỉ lấy phiếu đã xác nhận)
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
                        📊 BIỂU ĐỒ BIẾN ĐỘNG THEO HÀNG HÓA
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Xem biến động nhập/xuất hàng hóa theo từng ngày
                    </p>
                </div>

                <!-- Bộ lọc -->
                <div class="bg-gray-100 dark:bg-gray-700 rounded-xl shadow-md p-6 mb-10">
                    <h3 class="text-xl font-semibold mb-5 text-gray-800 dark:text-gray-100">
                        Bộ lọc biểu đồ
                    </h3>

                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4" id="filterForm">

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
                            <select name="chart2_loai_kho" id="chart2_loai_kho" onchange="updateHangHoaList()"
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

                        <!-- Hàng hóa -->
                        <div>
                            <label for="chart2_ma_hang" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Hàng hóa
                            </label>
                            <select name="chart2_ma_hang" id="chart2_ma_hang"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_hang as $h): ?>
                                    <option value="<?= htmlspecialchars($h['ma_hang']) ?>"
                                        <?= $chart2_ma_hang === $h['ma_hang'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($h['ma_hang'] . ' - ' . $h['ten_hang']) ?>
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
    // Mapping giữa loại kho và loại hàng
    const mappingLoaiKhoHang = {
        'L001': 'M001',
        'L002': 'M002',
        'L003': 'M003',
        'L004': 'M004'
    };

    // Dữ liệu tất cả hàng hóa
    const allHangHoa = <?php 
    $stmt_all = $pdo->query("SELECT ma_hang, ten_hang, ma_loai_hang, don_vi_tinh FROM hang_hoa ORDER BY ma_hang");
    $all_hangs = $stmt_all->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($all_hangs);
?>;

    // Cập nhật danh sách hàng hóa
    function updateHangHoaList() {
        const loaiKhoSelect = document.getElementById('chart2_loai_kho');
        const maHangSelect = document.getElementById('chart2_ma_hang');
        const selectedLoaiKho = loaiKhoSelect.value;

        let selectedLoaiHang = '';
        if (selectedLoaiKho && mappingLoaiKhoHang[selectedLoaiKho]) {
            selectedLoaiHang = mappingLoaiKhoHang[selectedLoaiKho];
        }

        let filteredHangs;
        if (selectedLoaiHang) {
            filteredHangs = allHangHoa.filter(h => h.ma_loai_hang === selectedLoaiHang);
        } else {
            filteredHangs = allHangHoa;
        }

        const currentValue = maHangSelect.value;
        maHangSelect.innerHTML = '<option value="">-- Tất cả --</option>';

        filteredHangs.forEach(hang => {
            const option = document.createElement('option');
            option.value = hang.ma_hang;
            option.textContent = hang.ma_hang + ' - ' + hang.ten_hang + ' (' + hang.don_vi_tinh + ')';
            maHangSelect.appendChild(option);
        });

        maHangSelect.value = currentValue;
    }

    // Dữ liệu biểu đồ
    const dataNgay = <?php echo json_encode($danh_sach_ngay); ?>;
    const labelsNgay = Object.keys(dataNgay);
    const nhapNgay = labelsNgay.map(ngay => dataNgay[ngay].nhap);
    const xuatNgay = labelsNgay.map(ngay => dataNgay[ngay].xuat);
    const tonNgay = labelsNgay.map(ngay => dataNgay[ngay].ton);

    const ctxHang = document.getElementById('chartHangHoa').getContext('2d');
    new Chart(ctxHang, {
        type: 'line',
        data: {
            labels: labelsNgay,
            datasets: [
                   
                
                
                {
                    label: 'Tồn cuối kỳ',
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
                    text: 'Biến động theo ngày'
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