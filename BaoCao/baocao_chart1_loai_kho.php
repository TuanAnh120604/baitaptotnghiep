<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc cho BIỂU ĐỒ LOẠI KHO
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

// Lấy danh sách DVT dựa trên loại kho được chọn
$danh_sach_dvt = [];
if (!empty($chart1_loai_kho) && isset($mapping_loai_kho_hang[$chart1_loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$chart1_loai_kho];
    $stmt_dvt = $pdo->prepare("SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE ma_loai_hang = ? ORDER BY don_vi_tinh");
    $stmt_dvt->execute([$ma_loai_hang]);
    $danh_sach_dvt = $stmt_dvt->fetchAll(PDO::FETCH_ASSOC);
}

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

// ============ QUERY: TỒN CUỐI KỲ THEO LOẠI KHO (dựa trên the_kho) ============
// Lấy bản ghi mới nhất của từng (ma_kho, ma_hang) đến ngày kết thúc, rồi cộng tồn theo loại kho
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

// Khởi tạo theo danh sách loại kho để luôn có đủ label
foreach ($danh_sach_loai_kho as $lk) {
    $ten = $lk['ten_loai_kho'];
    $data_bieu_do[$ten] = ['nhap' => 0, 'xuat' => 0, 'ton' => 0];
}

// Gộp dữ liệu nhập
foreach ($data_bieu_do_loai_kho_temp as $row) {
    $ten = $row['ten_loai_kho'];
    $data_bieu_do[$ten]['nhap'] = (int)$row['tong_nhap'];
}

// Gộp dữ liệu xuất
foreach ($data_bieu_do_xuat_temp as $row) {
    $ten = $row['ten_loai_kho'];
    $data_bieu_do[$ten]['xuat'] = (int)$row['tong_xuat'];
}

// Gộp dữ liệu tồn
foreach ($data_bieu_do_ton_temp as $row) {
    $ten = $row['ten_loai_kho'];
    $data_bieu_do[$ten]['ton'] = (int)$row['ton_cuoi_ky'];
}
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu Đồ Loại Kho - Báo Cáo Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Nếu đã build Tailwind → thay bằng: <link href="/css/output.css" rel="stylesheet"> -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
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
                        📈 BIỂU ĐỒ BIẾN ĐỘNG THEO LOẠI KHO
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Xem tổng lượng nhập, xuất và tồn cuối kỳ theo loại kho trong khoảng thời gian được chọn
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
                            <label for="chart1_ma_vung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Vùng miền
                            </label>
                            <select name="chart1_ma_vung" id="chart1_ma_vung"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                    <option value="<?= htmlspecialchars($v['ma_vung']) ?>"
                                        <?= $chart1_ma_vung === $v['ma_vung'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['ten_vung']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Loại kho -->
                        <div>
                            <label for="chart1_loai_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Loại kho
                            </label>
                            <select name="chart1_loai_kho" id="chart1_loai_kho" onchange="updateDVTList()"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                    <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"
                                        <?= $chart1_loai_kho === $lk['ma_loai_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lk['ten_loai_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Đơn vị tính -->
                        <div>
                            <label for="chart1_don_vi_tinh" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Đơn vị tính
                            </label>
                            <select name="chart1_don_vi_tinh" id="chart1_don_vi_tinh"
                                    class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_dvt as $dvt): ?>
                                    <option value="<?= htmlspecialchars($dvt['don_vi_tinh']) ?>"
                                        <?= $chart1_don_vi_tinh === $dvt['don_vi_tinh'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dvt['don_vi_tinh']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Từ ngày -->
                        <div>
                            <label for="chart1_ngay_bat_dau" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Từ ngày
                            </label>
                            <input type="date" name="chart1_ngay_bat_dau" id="chart1_ngay_bat_dau"
                                   value="<?= htmlspecialchars($chart1_ngay_bat_dau) ?>"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Đến ngày -->
                        <div>
                            <label for="chart1_ngay_ket_thuc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Đến ngày
                            </label>
                            <input type="date" name="chart1_ngay_ket_thuc" id="chart1_ngay_ket_thuc"
                                   value="<?= htmlspecialchars($chart1_ngay_ket_thuc) ?>"
                                   class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <!-- Nút lọc + xuất excel -->
                        <div class="flex flex-col justify-end gap-3 sm:flex-row sm:items-end lg:col-span-2 xl:col-span-1 xl:flex-col xl:items-stretch">
                            <button type="submit"
                                    class="px-6 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                                Lọc dữ liệu
                            </button>

                            <a href="xuat_excel_chart1_loai_kho.php?<?= http_build_query($_GET) ?>"
                               class="px-6 py-2.5 bg-green-600 hover:bg-green-700 text-white font-medium rounded-lg shadow transition-colors text-center">
                                📥 Xuất Excel
                            </a>
                        </div>

                    </form>
                </div>

                <!-- Khu vực biểu đồ -->
                <div class="bg-white dark:bg-gray-700 rounded-xl shadow-lg p-6">
                    <div class="h-[450px] md:h-[500px]">
                        <canvas id="chartLoaiKho"></canvas>
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

    // Cập nhật danh sách DVT
    function updateDVTList() {
        const loaiKhoSelect = document.getElementById('chart1_loai_kho');
        const donViTinhSelect = document.getElementById('chart1_don_vi_tinh');
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

        const uniqueDVTs = [...new Set(filteredHangs.map(h => h.don_vi_tinh))];
        uniqueDVTs.sort();

        const currentValue = donViTinhSelect.value;
        donViTinhSelect.innerHTML = '<option value="">-- Tất cả --</option>';

        uniqueDVTs.forEach(dvt => {
            const option = document.createElement('option');
            option.value = dvt;
            option.textContent = dvt;
            donViTinhSelect.appendChild(option);
        });

        if (uniqueDVTs.includes(currentValue)) {
            donViTinhSelect.value = currentValue;
        } else {
            donViTinhSelect.value = '';
        }
    }

    // Dữ liệu biểu đồ
    const dataLoaiKho = <?php echo json_encode($data_bieu_do); ?>;
    const labelsLoaiKho = Object.keys(dataLoaiKho);
    const nhapLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].nhap);
    const xuatLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].xuat);
    const tonLoaiKho = labelsLoaiKho.map(label => dataLoaiKho[label].ton);

    const ctxLoaiKho = document.getElementById('chartLoaiKho').getContext('2d');
    new Chart(ctxLoaiKho, {
        type: 'bar',
        data: {
            labels: labelsLoaiKho,
            datasets: [{
                    label: 'Lượng nhập',
                    data: nhapLoaiKho,
                    backgroundColor: '#28a745',
                    borderColor: '#20c997',
                    borderWidth: 1
                },
                {
                    label: 'Lượng xuất',
                    data: xuatLoaiKho,
                    backgroundColor: '#dc3545',
                    borderColor: '#e74c3c',
                    borderWidth: 1
                },
                {
                    label: 'Tồn cuối kỳ',
                    data: tonLoaiKho,
                    backgroundColor: '#007bff',
                    borderColor: '#0056b3',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                title: {
                    display: true,
                    text: 'Biến động theo loại kho'
                },
                legend: {
                    display: true,
                    position: 'top'
                }
            },
            scales: {
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