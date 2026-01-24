<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy các tham số lọc
$ma_vung = isset($_GET['ma_vung']) ? $_GET['ma_vung'] : '';
$loai_kho = isset($_GET['loai_kho']) ? $_GET['loai_kho'] : '';
$don_vi_tinh = isset($_GET['don_vi_tinh']) ? $_GET['don_vi_tinh'] : '';
$ngay_bat_dau = isset($_GET['ngay_bat_dau']) ? $_GET['ngay_bat_dau'] : date('Y-01-01');
$ngay_ket_thuc = isset($_GET['ngay_ket_thuc']) ? $_GET['ngay_ket_thuc'] : date('Y-m-d');

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

// Lấy danh sách DVT dựa trên loại kho được chọn
$danh_sach_dvt = [];
if (!empty($loai_kho) && isset($mapping_loai_kho_hang[$loai_kho])) {
    $ma_loai_hang = $mapping_loai_kho_hang[$loai_kho];
    $sql_dvt = "SELECT DISTINCT don_vi_tinh FROM hang_hoa WHERE ma_loai_hang = ?";
    $params_dvt = [$ma_loai_hang];
    
    // Thêm điều kiện phân quyền nếu cần
    if (!empty($kho_condition)) {
        // Lấy DVT từ các kho có quyền
        $sql_dvt = "
            SELECT DISTINCT hh.don_vi_tinh 
            FROM hang_hoa hh
            JOIN the_kho tk ON hh.ma_hang = tk.ma_hang
            JOIN kho k ON tk.ma_kho = k.ma_kho
            WHERE hh.ma_loai_hang = ?
        ";
        if ($role === 'Thủ kho' && $ma_nd) {
            $sql_dvt .= " AND k.ma_nd = ?";
            $params_dvt[] = $ma_nd;
        } elseif ($role === 'Quản lý kho' && $ma_nd) {
            $sql_dvt .= " AND k.ma_kho IN (
                SELECT k2.ma_kho 
                FROM kho k2 
                JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho 
                WHERE pq.ma_nd = ?
            )";
            $params_dvt[] = $ma_nd;
        }
    }
    $sql_dvt .= " ORDER BY don_vi_tinh";
    $stmt_dvt = $pdo->prepare($sql_dvt);
    $stmt_dvt->execute($params_dvt);
    $danh_sach_dvt = $stmt_dvt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Báo Cáo Tổng Hợp - Báo Cáo Kho</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Nếu đã build Tailwind → thay bằng: <link href="/css/output.css" rel="stylesheet"> -->
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
                        📑 BÁO CÁO TỔNG HỢP
                    </h1>
                    <p class="mt-3 text-lg text-gray-600 dark:text-gray-400">
                        Xuất Excel báo cáo tổng hợp 3 bảng: biến động kho, biến động hàng hóa, bảng cân đối
                    </p>
                </div>

                <!-- Bộ lọc -->
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl shadow-md p-6 mb-10 border border-gray-200 dark:border-gray-700">
                    <h3 class="text-xl font-semibold mb-6 text-gray-800 dark:text-gray-100">
                        Bộ lọc báo cáo
                    </h3>

                    <form method="GET" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-5" id="filterForm" action="xuat_excel_bao_cao_tong_hop.php">

                        <!-- Vùng miền -->
                        <div>
                            <label for="ma_vung" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Vùng miền
                            </label>
                            <select name="ma_vung" id="ma_vung"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_vung as $v): ?>
                                    <option value="<?= htmlspecialchars($v['ma_vung']) ?>"
                                        <?= $ma_vung == $v['ma_vung'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($v['ten_vung']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Loại kho -->
                        <div>
                            <label for="loai_kho" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Loại kho
                            </label>
                            <select name="loai_kho" id="loai_kho" onchange="updateDVTList()"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_loai_kho as $lk): ?>
                                    <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"
                                        <?= $loai_kho == $lk['ma_loai_kho'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($lk['ten_loai_kho']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Đơn vị tính -->
                        <div>
                            <label for="don_vi_tinh" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Đơn vị tính
                            </label>
                            <select name="don_vi_tinh" id="don_vi_tinh"
                                    class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                                <option value="">-- Tất cả --</option>
                                <?php foreach ($danh_sach_dvt as $dvt): ?>
                                    <option value="<?= htmlspecialchars($dvt['don_vi_tinh']) ?>"
                                        <?= $don_vi_tinh == $dvt['don_vi_tinh'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dvt['don_vi_tinh']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Từ ngày -->
                        <div>
                            <label for="ngay_bat_dau" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Từ ngày
                            </label>
                            <input type="date" name="ngay_bat_dau" id="ngay_bat_dau"
                                   value="<?= htmlspecialchars($ngay_bat_dau) ?>"
                                   class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                        </div>

                        <!-- Đến ngày -->
                        <div>
                            <label for="ngay_ket_thuc" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                                Đến ngày
                            </label>
                            <input type="date" name="ngay_ket_thuc" id="ngay_ket_thuc"
                                   value="<?= htmlspecialchars($ngay_ket_thuc) ?>"
                                   class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none transition">
                        </div>

                        <!-- Nút xuất -->
                        <div class="flex items-end gap-4 sm:gap-3 xl:flex-col xl:items-stretch xl:gap-3">
                            <button type="submit"
                                    class="flex-1 h-10 px-4 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow-sm transition-colors duration-200">
                                📥 Xuất Excel Báo Cáo Tổng Hợp
                            </button>
                        </div>

                    </form>
                </div>

                <!-- Nội dung báo cáo tổng hợp -->
                <div class="bg-gray-100 dark:bg-gray-800 rounded-xl shadow-md p-6 border border-gray-200 dark:border-gray-700">
                    <h5 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-100">📋 Nội dung báo cáo tổng hợp:</h5>
                    <ul class="list-disc pl-5 space-y-2 text-gray-700 dark:text-gray-300">
                        <li><strong>Bảng 1:</strong> Biến động kho - Tình hình nhập xuất theo ngày theo đơn vị tính</li>
                        <li><strong>Bảng 2:</strong> Biến động hàng hóa của loại kho - Tình hình nhập xuất, tồn đầu, tồn cuối</li>
                        <li><strong>Bảng 3:</strong> Bảng cân đối kho - Tổng hợp theo loại kho và đơn vị tính</li>
                    </ul>
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
        const loaiKhoSelect = document.getElementById('loai_kho');
        const donViTinhSelect = document.getElementById('don_vi_tinh');
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
    </script>

</body>
</html>