<?php
include '../include/connect.php';
include '../include/permissions.php';
include '../include/update_the_kho.php';

checkAccess('phieunhap');

$error_message = '';
$success_message = '';

// Hàm tự động tạo mã phiếu nhập
function taoMaPhieuNhapTuDong($pdo, $loai)
{
    $prefix = ($loai === 'vat_tu') ? 'PN-VT-' : 'PN-TP-';

    // Lấy mã phiếu lớn nhất có prefix tương ứng
    $stmt = $pdo->prepare("
        SELECT ma_phieu_nhap 
        FROM phieu_nhap 
        WHERE ma_phieu_nhap LIKE ? 
        ORDER BY ma_phieu_nhap DESC 
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $lastMa = $stmt->fetchColumn();

    if ($lastMa) {
        // Tách số thứ tự từ mã cuối cùng
        $lastNumber = intval(substr($lastMa, strlen($prefix)));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }

    // Format số thành 3 chữ số: 001, 002, ...
    return $prefix . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
}

// Lấy danh sách nhà cung cấp
$ncc_list = $pdo->query("SELECT ma_ncc, ten_ncc FROM nha_cung_cap ORDER BY ten_ncc")->fetchAll(PDO::FETCH_ASSOC);

// Tạo mã phiếu tự động cho cả hai loại
$ma_phieu_vat_tu = taoMaPhieuNhapTuDong($pdo, 'vat_tu');
$ma_phieu_thanh_pham = taoMaPhieuNhapTuDong($pdo, 'thanh_pham');

// Lấy danh sách LOẠI KHO
$loai_kho_list = $pdo->query("
    SELECT ma_loai_kho, ten_loai_kho 
    FROM loai_kho 
    ORDER BY ten_loai_kho
")->fetchAll(PDO::FETCH_ASSOC);

// Lấy kho theo quyền user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;
$sql_kho = "
    SELECT k.ma_kho, k.ten_kho, k.ma_loai_kho 
    FROM kho k
    WHERE 1=1
";
$params_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho .= ' AND k.ma_nd = :ma_nd';
    $params_kho[':ma_nd'] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho .= ' AND EXISTS (
        SELECT 1 FROM phan_quyen pq 
        WHERE pq.ma_nd = :ma_nd 
        AND pq.ma_vung = k.ma_vung 
        AND pq.ma_loai_kho = k.ma_loai_kho
    )';
    $params_kho[':ma_nd'] = $ma_nd;
}
// Admin và Ban giám đốc thấy hết
$sql_kho .= ' ORDER BY k.ten_kho';
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$kho_list = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);

// Xác định quyền theo loại kho từ $kho_list (đã lọc theo quyền user)
$has_vat_tu     = false;
$has_thanh_pham = false;

$vat_tu_loai = ['L001', 'L002', 'L003']; // Nguyên liệu, Nhiên liệu, Phụ tùng

foreach ($kho_list as $kho) {
    if (in_array($kho['ma_loai_kho'], $vat_tu_loai)) {
        $has_vat_tu = true;
    }
    if ($kho['ma_loai_kho'] === 'L004') {
        $has_thanh_pham = true;
    }
}

if (!$has_vat_tu && !$has_thanh_pham) {
    $error_message = 'Bạn chưa được phân quyền quản lý kho nào để thêm phiếu nhập.';
}

// Lấy loại kho mặc định cho quản lý kho
$default_loai_kho = '';
if ($role === 'Quản lý kho' && $ma_nd && count($kho_list) > 0) {
    // Lấy loại kho đầu tiên từ danh sách kho mà user quản lý
    $default_loai_kho = $kho_list[0]['ma_loai_kho'];
}

$hang_hoa_list = $pdo->query("
    SELECT ma_hang, ten_hang, don_gia, ma_loai_hang 
    FROM hang_hoa 
    ORDER BY ten_hang
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['ajax_get_ma_phieu'])) {
    $loai = $_GET['loai'] ?? 'vat_tu';
    $ma_phieu = taoMaPhieuNhapTuDong($pdo, $loai);
    echo json_encode(['ma_phieu' => $ma_phieu]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loai_phieu    = $_POST['loai_phieu'] ?? 'vat_tu'; 
    $ma_phieu_nhap = trim($_POST['ma_phieu_nhap'] ?? '');
    $ngay_nhap     = $_POST['ngay_nhap'] ?? '';
    $ma_kho        = $_POST['ma_kho'] ?? null;
    $nguoi_giao    = trim($_POST['nguoi_giao'] ?? '');
    $don_vi_giao   = trim($_POST['don_vi_giao'] ?? '');

    $ma_ncc = ($loai_phieu === 'vat_tu') ? ($_POST['ma_ncc'] ?? null) : null;

    $hang_hoa = $_POST['hang_hoa'] ?? [];

    if (empty($ma_phieu_nhap) || empty($ngay_nhap) || empty($ma_kho) || empty($hang_hoa)) {
        $error_message = 'Vui lòng điền đầy đủ thông tin bắt buộc.';
    } elseif ($loai_phieu === 'vat_tu' && empty($ma_ncc)) {
        $error_message = 'Vui lòng chọn nhà cung cấp cho phiếu nhập vật tư.';
    } else {
        try {
            $pdo->beginTransaction();
            $check = $pdo->prepare("SELECT ma_phieu_nhap FROM phieu_nhap WHERE ma_phieu_nhap = ?");
            $check->execute([$ma_phieu_nhap]);
            if ($check->fetch()) {
                throw new Exception('Mã phiếu nhập đã tồn tại.');
            }

 
            $sql_pn = "INSERT INTO phieu_nhap 
                       (ma_phieu_nhap, ma_nd, ngay_nhap, nguoi_giao, don_vi_giao, loai_nhap, ma_kho, ma_ncc, trang_thai)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cho_xac_nhan')";
            $stmt_pn = $pdo->prepare($sql_pn);
            $stmt_pn->execute([$ma_phieu_nhap, $_SESSION['MaND'], $ngay_nhap, $nguoi_giao, $don_vi_giao, $loai_phieu, $ma_kho, $ma_ncc]);

            // Thêm chi tiết phiếu
            $sql_ct = "INSERT INTO ct_phieu_nhap 
                       (ma_ctpn, ma_phieu_nhap, ma_hang, so_luong_nhap, don_gia, thanh_tien)
                       VALUES (?, ?, ?, ?, ?, ?)";
            $stmt_ct = $pdo->prepare($sql_ct);

            $ds_ma_hang = [];

            foreach ($hang_hoa as $item) {
                if (empty($item['ma_hang']) || empty($item['so_luong']) || $item['so_luong'] <= 0) continue;

                $ma_hang    = $item['ma_hang'];
                $so_luong   = (int)$item['so_luong'];
                $don_gia    = (float)($item['don_gia'] ?? 0);
                $thanh_tien = $so_luong * $don_gia;

                $ma_ctpn = $ma_phieu_nhap . '-' . $ma_hang;

                $stmt_ct->execute([$ma_ctpn, $ma_phieu_nhap, $ma_hang, $so_luong, $don_gia, $thanh_tien]);

                $ds_ma_hang[] = $ma_hang;
            }

            $pdo->commit();

            // KHÔNG tự động cập nhật thẻ kho - chỉ cập nhật khi thủ kho xác nhận
            // cap_nhat_the_kho_theo_phieu($pdo, $ma_kho, $ds_ma_hang);

            $success_message = 'Thêm phiếu nhập thành công! Phiếu đang chờ thủ kho xác nhận.';
        } catch (Exception $e) {
            // $pdo->rollBack();
            $error_message = 'Lỗi: ' . $e->getMessage();
        }
    }
}
?>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Thêm Phiếu Nhập Kho</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#135bec",
                        "primary-hover": "#0e4bce",
                        "background-light": "#f6f6f8",
                        "background-dark": "#101622",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1a2230",
                        "border-light": "#e7ebf3",
                        "border-dark": "#2d3748",
                        "text-primary": "#0d121b",
                        "text-secondary": "#4c669a",
                    },
                },
            },
        }
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark text-text-primary dark:text-gray-100 min-h-screen font-display">

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="h-16 bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark flex items-center justify-between px-6">
            <div class="flex items-center gap-4">
                <a href="phieunhap.php" class="text-text-secondary hover:text-primary">
                    <span class="material-symbols-outlined">arrow_back</span>
                </a>
                <h1 class="text-xl font-bold">Phiếu nhập kho</h1>
            </div>
        </header>

        <main class="flex-1 overflow-y-auto p-6">
            <?php if ($has_vat_tu): ?>
            <div class="h-full flex gap-6">
                <!-- Left Panel: Phiếu giao hàng của nhà cung cấp (chỉ hiển thị cho quản lý kho Nguyên liệu/Nhiên liệu/Phụ tùng) -->
                <div class="w-1/2 flex flex-col">
                    <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden h-full flex flex-col">
                        <div class="bg-primary text-white px-6 py-4 border-b border-border-light dark:border-border-dark">
                            <h2 class="text-lg font-bold flex items-center gap-2">
                                <span class="material-symbols-outlined">description</span>
                                Phiếu giao hàng từ nhà cung cấp
                            </h2>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6 flex flex-col space-y-4">
                            <!-- Chọn nhà cung cấp -->
                            <div>
                                <label class="block text-sm font-medium mb-2">Chọn nhà cung cấp</label>
                                <select id="ncc_filter" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" onchange="loadPhieuGiao()">
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($ncc_list as $ncc): ?>
                                        <option value="<?= htmlspecialchars($ncc['ma_ncc']) ?>">
                                            <?= htmlspecialchars($ncc['ten_ncc']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Danh sách phiếu giao hàng -->
                            <div class="flex-1 border rounded-lg overflow-hidden flex flex-col">
                                <div id="phieu_giao_list" class="flex-1 overflow-y-auto">
                                    <div class="text-center text-text-secondary py-12">
                                        <span class="material-symbols-outlined text-5xl block mb-4 opacity-20">folder_open</span>
                                        <p class="text-sm">Chọn nhà cung cấp để xem phiếu giao hàng</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Panel: Form tạo phiếu nhập -->
                <div class="w-1/2 flex flex-col">
                    <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden h-full flex flex-col">
                        <div class="bg-primary text-white px-6 py-4 border-b border-border-light dark:border-border-dark">
                            <h2 class="text-lg font-bold flex items-center gap-2">
                                <span class="material-symbols-outlined">add_circle</span>
                                Tạo phiếu nhập kho
                            </h2>
                        </div>
                        <div class="flex-1 overflow-y-auto p-6">
            <?php else: ?>
            <div class="h-full">
                <!-- <div class="mb-4 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg text-sm text-yellow-800 dark:text-yellow-200">
                    ⚠️ Phiếu giao hàng từ nhà cung cấp chỉ hiển thị với quản lý kho <strong>Nguyên liệu, Nhiên liệu, Phụ tùng</strong>. Nếu bạn cần, hãy liên hệ quản trị để cấp quyền.
                </div> -->

                <!-- Right Panel (full width): Form tạo phiếu nhập -->
                <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark overflow-hidden h-full flex flex-col">
                    <!-- <div class="bg-primary text-white px-6 py-4 border-b border-border-light dark:border-border-dark">
                        <h2 class="text-lg font-bold flex items-center gap-2">
                            <span class="material-symbols-outlined">add_circle</span>
                            Tạo phiếu nhập kho
                        </h2>
                    </div> -->
                    <div class="flex-1 overflow-y-auto p-6">
            <?php endif; ?>
                            <!-- Tab Navigation -->
                            <!-- Tab Navigation -->
                        <?php if ($has_vat_tu || $has_thanh_pham): ?>
                            <div class="mb-6 bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark p-2 flex gap-2">
                    <?php if ($has_vat_tu): ?>
                        <button type="button" onclick="switchTab('vat_tu')" id="tab-vat-tu"
                            class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm">
                            <span class="flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">input</span>
                                Phiếu nhập vật tư
                            </span>
                        </button>
                    <?php endif; ?>

                    <?php if ($has_thanh_pham): ?>
                        <button type="button" onclick="switchTab('thanh_pham')" id="tab-thanh-pham"
                            class="flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800">
                            <span class="flex items-center justify-center gap-2">
                                <span class="material-symbols-outlined">output</span>
                                Phiếu nhập thành phẩm
                            </span>
                        </button>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="mb-6 p-6 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-xl text-center text-red-700 dark:text-red-300">
                    Bạn không có quyền thêm phiếu nhập kho nào.
                </div>
            <?php endif; ?>

                <div class="bg-surface-light dark:bg-surface-dark rounded-xl border border-border-light dark:border-border-dark shadow-sm p-6 md:p-8">

                    <?php if ($error_message): ?>
                        <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg flex gap-3">
                            <span class="material-symbols-outlined text-red-600">error</span>
                            <div class="text-sm text-red-800 dark:text-red-300"><?= htmlspecialchars($error_message) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($success_message): ?>
                        <div id="successModal" class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4">
                            <div class="bg-white dark:bg-surface-dark rounded-xl shadow-2xl max-w-md w-full p-8 text-center">
                                <div class="mb-4 flex justify-center">
                                    <span class="material-symbols-outlined text-6xl text-green-500">check_circle</span>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">Thành công!</h3>
                                <p class="text-gray-600 dark:text-gray-300 mb-6"><?= htmlspecialchars($success_message) ?></p>
                                <div class="flex gap-3">
                                    <a href="phieunhap.php" class="flex-1 px-4 py-3 bg-primary hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                                        Xem danh sách
                                    </a>
                                    <button onclick="resetFormAndCloseModal()" class="flex-1 px-4 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-lg font-medium transition-colors">
                                        Thêm tiếp
                                    </button>
                                </div>
                            </div>
                        </div>
                        <script>
                            // Tự động hide modal sau 10 giây nếu user không bấm
                            setTimeout(() => {
                                const modal = document.getElementById('successModal');
                                if (modal) {
                                    modal.style.opacity = '0';
                                    modal.style.transition = 'opacity 0.3s ease-out';
                                    setTimeout(() => {
                                        window.location.href = 'phieunhap.php';
                                    }, 300);
                                }
                            }, 10000);
                        </script>
                    <?php endif; ?>

                    <form method="POST" id="phieuNhapForm">
                        <input type="hidden" name="loai_phieu" id="loai_phieu" value="vat_tu">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                            <div>
                                <label class="block text-sm font-medium mb-2">Mã phiếu nhập <span class="text-red-500">*</span></label>
                                <div class="flex gap-2">
                                    <input type="text"
                                        name="ma_phieu_nhap"
                                        id="ma_phieu_nhap"
                                        required
                                        value="<?= htmlspecialchars($ma_phieu_vat_tu) ?>"
                                        class="flex-1 px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary"
                                        placeholder="VD: PN-VT-001" />
                                    <button type="button"
                                        onclick="generateMaPhieu()"
                                        class="px-4 py-2.5 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 border rounded-lg transition-colors"
                                        title="Tạo mã tự động">
                                        <span class="material-symbols-outlined text-[20px]">refresh</span>
                                    </button>
                                </div>
                                <p class="text-xs text-text-secondary mt-1">Mã tự động theo loại phiếu</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-2">Ngày nhập <span class="text-red-500">*</span></label>
                                <input type="date" name="ngay_nhap" required value="<?= date('Y-m-d') ?>" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" />
                            </div>

                            <!-- Nhà cung cấp - chỉ hiện với vật tư -->
                            <div id="ncc-field">
                                <label class="block text-sm font-medium mb-2">Nhà cung cấp <span class="text-red-500">*</span></label>
                                <select name="ma_ncc" id="ma_ncc" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                                    <option value="">-- Chọn nhà cung cấp --</option>
                                    <?php foreach ($ncc_list as $ncc): ?>
                                        <option value="<?= htmlspecialchars($ncc['ma_ncc']) ?>"><?= htmlspecialchars($ncc['ten_ncc']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                             <div>
                                <label class="block text-sm font-medium mb-2">Đơn vị giao</label>
                                <input id="don_vi_giao" type="text" name="don_vi_giao" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" placeholder="VD: Phòng sản xuất" />
                            </div>


                            <div>
                                <label class="block text-sm font-medium mb-2">Loại kho <span class="text-red-500">*</span></label>
                                <select name="ma_loai_kho" id="ma_loai_kho" required class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" onchange="filterKhoAndHang()" <?= $default_loai_kho ? 'disabled' : '' ?>>
                                    <option value="">-- Chọn loại kho --</option>
                                    <?php foreach ($loai_kho_list as $lk): ?>
                                        <option value="<?= htmlspecialchars($lk['ma_loai_kho']) ?>"><?= htmlspecialchars($lk['ten_loai_kho']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($default_loai_kho): ?>
                                    <input type="hidden" name="ma_loai_kho" value="<?= htmlspecialchars($default_loai_kho) ?>">
                            
                                <?php endif; ?>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Kho nhập <span class="text-red-500">*</span></label>
                                <div class="flex gap-2 items-center">
                                    <select name="ma_kho" id="ma_kho" required class="flex-1 px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" onchange="reloadTonKhoAllRows()">
                                        <option value="">-- Chọn kho --</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium mb-2">Người giao</label>
                                <input id="nguoi_giao" type="text" name="nguoi_giao" class="w-full px-4 py-2.5 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary" placeholder="Tên người giao hàng" />
                            </div>
                        </div>

                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-semibold">Chi tiết mặt hàng nhập</h3>
                                <button type="button" onclick="addRow()" class="flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary-hover text-white rounded-lg text-sm font-medium">
                                    <span class="material-symbols-outlined text-[18px]">add</span> Thêm hàng
                                </button>
                            </div>

                            <div class="overflow-x-auto border rounded-lg">
                                <table class="w-full" id="chiTietTable">
                                    <thead class="bg-gray-50 dark:bg-gray-800">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-text-secondary uppercase">Mặt hàng</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-32">Số lượng</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-32">Tồn kho</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-40">Đơn giá (VNĐ)</th>
                                            <th class="px-4 py-3 text-right text-xs font-medium text-text-secondary uppercase w-40">Thành tiền</th>
                                            <th class="px-4 py-3 text-center text-xs font-medium text-text-secondary uppercase w-20">Xóa</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="6" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4" class="px-4 py-3 text-right font-semibold">Tổng tiền:</td>
                                            <td class="px-4 py-3 text-right font-bold text-lg" id="tongTien">0 đ</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="flex justify-end gap-4">
                            <a href="phieunhap.php" class="px-6 py-3 border rounded-lg bg-surface-light dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 transition">
                                Hủy
                            </a>
                            <button type="submit" class="px-6 py-3 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium flex items-center gap-2 shadow-sm">
                                <span class="material-symbols-outlined">save</span>
                                Lưu phiếu nhập
                            </button>
                        </div>
                    </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Modal cảnh báo mức dự trữ -->
        <div id="warningModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-fade-in">
                    <div class="w-full max-w-md bg-white dark:bg-surface-dark rounded-xl shadow-2xl">
                        <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                                <span class="material-symbols-outlined text-yellow-600">warning</span>
                                Cảnh báo mức dự trữ
                            </h3>
                        </div>
                        <div class="p-6">
                            <div id="warningContent" class="space-y-4">
                                <!-- Nội dung cảnh báo sẽ được thêm bằng JS -->
                            </div>
                            <div class="flex justify-end gap-3 mt-6">
                                <button onclick="cancelImport()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                                    Hủy
                                </button>
                                <button onclick="proceedImport()" class="px-4 py-2 bg-yellow-600 hover:bg-yellow-700 text-white rounded-lg font-medium transition-colors">
                                    Tiếp tục nhập
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
                // Dữ liệu từ PHP
        const loaiKhoToHangMap = {
            'L001': 'M001',
            'L002': 'M002',
            'L003': 'M003',
            'L004': 'M004'
        };
        const hangHoaList = <?= json_encode($hang_hoa_list) ?>;
        const khoList = <?= json_encode($kho_list) ?>;

        // Mã phiếu tự động
        const maPhieuVatTu = '<?= $ma_phieu_vat_tu ?>';
        const maPhieuThanhPham = '<?= $ma_phieu_thanh_pham ?>';

        let rowIndex = 0;
        let currentTab = 'vat_tu';

        // Quyền từ PHP
        const hasVatTu = <?= $has_vat_tu ? 'true' : 'false' ?>;
        const hasThanhPham = <?= $has_thanh_pham ? 'true' : 'false' ?>;
        const defaultLoaiKho = '<?= $default_loai_kho ?>';

        // Tự động chọn tab đầu tiên có quyền khi load trang
        document.addEventListener('DOMContentLoaded', () => {
            if (hasVatTu) {
                switchTab('vat_tu');
            } else if (hasThanhPham) {
                switchTab('thanh_pham');
            }
            
            // Nếu là quản lý kho, tự động set loại kho mặc định
            if (defaultLoaiKho) {
                setTimeout(() => {
                    document.getElementById('ma_loai_kho').value = defaultLoaiKho;
                    filterKhoAndHang();
                }, 300);
            }
        });

        // Hàm chuyển tab
        function switchTab(tabName) {
            if ((tabName === 'vat_tu' && !hasVatTu) || (tabName === 'thanh_pham' && !hasThanhPham)) {
                return; // Không cho chuyển nếu không có quyền
            }

            currentTab = tabName;
            document.getElementById('loai_phieu').value = tabName;

            // Reset style các tab
            const tabVatTu = document.getElementById('tab-vat-tu');
            const tabThanhPham = document.getElementById('tab-thanh-pham');

            if (tabVatTu) {
                tabVatTu.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';
            }
            if (tabThanhPham) {
                tabThanhPham.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-transparent text-text-secondary hover:bg-gray-100 dark:hover:bg-gray-800';
            }

            // Active tab hiện tại
            const activeTab = document.getElementById(`tab-${tabName}`);
            if (activeTab) {
                activeTab.className = 'flex-1 px-6 py-3 rounded-lg font-medium transition-all duration-200 bg-primary text-white shadow-sm';
            }

            // Hiện/ẩn trường NCC (chỉ vật tư cần NCC)
            document.getElementById('ncc-field').style.display = (tabName === 'vat_tu') ? 'block' : 'none';
            document.getElementById('ma_ncc').required = (tabName === 'vat_tu');

            // Cập nhật mã phiếu
            document.getElementById('ma_phieu_nhap').value = (tabName === 'vat_tu') ? maPhieuVatTu : maPhieuThanhPham;
            document.getElementById('ma_phieu_nhap').placeholder = (tabName === 'vat_tu') ? 'VD: PN-VT-001' : 'VD: PN-TP-001';

            // Lọc loại kho theo tab
            const allowedCodes = (tabName === 'vat_tu') ? ['L001','L002','L003'] : ['L004'];
            filterLoaiKhoOptions(allowedCodes);

            // Reset form chi tiết
            document.getElementById('ma_loai_kho').value = '';
            filterKhoAndHang();
        }

// Các hàm còn lại (filterLoaiKhoOptions, filterKhoAndHang, addRow, updateDonGia, tinhThanhTien, tinhTong) giữ nguyên
        // Lọc options loại kho
        function filterLoaiKhoOptions(allowedCodes) {
            const select = document.getElementById('ma_loai_kho');
            const options = select.querySelectorAll('option');

            options.forEach(opt => {
                if (opt.value === '') return; // Giữ option "-- Chọn loại kho --"
                if (allowedCodes.includes(opt.value)) {
                    opt.style.display = 'block';
                } else {
                    opt.style.display = 'none';
                }
            });
        }

        function filterKhoAndHang() {
            const maLoaiKho = document.getElementById('ma_loai_kho').value;
            const khoSelect = document.getElementById('ma_kho');

            khoSelect.innerHTML = '<option value="">-- Chọn kho --</option>';
            if (maLoaiKho) {
                const filteredKho = khoList.filter(k => k.ma_loai_kho === maLoaiKho);
                filteredKho.forEach(k => {
                    const opt = document.createElement('option');
                    opt.value = k.ma_kho;
                    opt.textContent = k.ten_kho;
                    khoSelect.appendChild(opt);
                });
            }

            document.querySelector('#chiTietTable tbody').innerHTML = '<tr><td colspan="6" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td></tr>';
            rowIndex = 0;
        }

        function getFilteredHangHoa(maLoaiKho) {
            const maLoaiHang = loaiKhoToHangMap[maLoaiKho];
            if (!maLoaiHang) return [];
            return hangHoaList.filter(h => h.ma_loai_hang === maLoaiHang);
        }

       function addRow(ma_hang = '', so_luong = '', don_gia = '', skipLoaiKhoCheck = false) {
    const tbody = document.querySelector('#chiTietTable tbody');
    const maLoaiKho = document.getElementById('ma_loai_kho').value;

    let filteredHang = [];
    if (skipLoaiKhoCheck) {
        filteredHang = hangHoaList;        // Lấy tất cả
    } else if (maLoaiKho) {
        filteredHang = getFilteredHangHoa(maLoaiKho);
    } else {
        alert('Vui lòng chọn loại kho trước!');
        return;
    }

    if (filteredHang.length === 0 && !skipLoaiKhoCheck) {
        alert('Không có mặt hàng nào thuộc loại kho này!');
        return;
    }

    if (tbody.querySelector('tr td[colspan]')) {
        tbody.innerHTML = '';
    }

    const row = document.createElement('tr');
    // đánh dấu nếu dòng được thêm tự động từ phiếu giao
    if (ma_hang) row.dataset.autoFromPhieu = '1';

    row.innerHTML = `
        <td class="px-4 py-3">
            <select name="hang_hoa[${rowIndex}][ma_hang]" required class="w-full px-3 py-2 border rounded-lg bg-background-light dark:bg-gray-800 focus:ring-2 focus:ring-primary">
                <option value="">-- Chọn mặt hàng --</option>
                ${filteredHang.map(h => `
                    <option value="${h.ma_hang}">
                        ${h.ten_hang}
                    </option>
                `).join('')}
            </select>
        </td>
        <td class="px-4 py-3 text-right w-32">
            <input type="number" name="hang_hoa[${rowIndex}][so_luong]" value="${so_luong || ''}" min="1" required class="w-full px-3 py-2 border rounded-lg text-right" onchange="checkTonKhoWarning(this); tinhThanhTien(this)" />
        </td>
        <td class="px-4 py-3 text-right w-32">
            <div data-ton-kho="0">0</div>
        </td>
        <td class="px-4 py-3 text-right w-40">
            <input type="text" name="hang_hoa[${rowIndex}][don_gia]" value="${don_gia ? Number(don_gia).toLocaleString('vi-VN') : ''}" readonly class="w-full px-3 py-2 border rounded-lg bg-gray-50 dark:bg-gray-700 text-right" />
        </td>
        <td class="px-4 py-3 text-right w-40 font-medium" data-thanh-tien="0">0 đ</td>
        <td class="px-4 py-3 text-center w-20">
            <button type="button" onclick="this.closest('tr').remove(); normalizeRowIndexes(); tinhTong()" class="text-red-600 hover:text-red-800">
                <span class="material-symbols-outlined text-[20px]">delete</span>
            </button>
        </td>
    `;

    tbody.appendChild(row);

    const select = row.querySelector('select');
    // Nếu có ma_hang - set giá trị rõ ràng và kích hoạt onchange
    if (select) {
        // luôn đăng ký handler onchange để hỗ trợ chỉnh tay sau này
        select.addEventListener('change', function(){
            updateDonGiaAndTonKho(this).catch(e => console.error(e));
        });
    }

    if (ma_hang && select) {
        try {
            // đảm bảo so sánh bằng chuỗi để tránh mismatch type
            select.value = String(ma_hang);
        } catch (err) {
            // bỏ qua nếu không set được
        }

        // trực tiếp gọi update để đảm bảo don_gia được set sớm
        updateDonGiaAndTonKho(select).catch(e => console.error(e));

        // nếu muốn hiển thị label nhỏ cho dòng tự động (chèn vào cột đầu tiên để không làm lệch layout)
        const firstTd = row.querySelector('td');
        if (firstTd) {
            firstTd.insertAdjacentHTML('beforeend', '<div class="mt-2"></div>');
        }
    }

    // Set số lượng (nếu có) - đảm bảo set cả property và attribute
    if (so_luong) {
        const qtyInput = row.querySelector('input[name$="[so_luong]"]');
        if (qtyInput) {
            qtyInput.value = so_luong;
            qtyInput.setAttribute('value', so_luong);
            checkTonKhoWarning(qtyInput);
        }
    }

    rowIndex++;
    // đảm bảo tên input theo thứ tự
    normalizeRowIndexes();
    tinhTong();
}

// Chuẩn hóa lại tên các input/select trong bảng theo chỉ số (đảm bảo gửi form đúng chỉ số liên tục)
function normalizeRowIndexes() {
    const rows = Array.from(document.querySelectorAll('#chiTietTable tbody tr'));
    rows.forEach((tr, idx) => {
        // select ma_hang
        const sel = tr.querySelector('select[name*="[ma_hang]"]');
        if (sel) sel.name = `hang_hoa[${idx}][ma_hang]`;

        // input so_luong
        const qty = tr.querySelector('input[name*="[so_luong]"]');
        if (qty) qty.name = `hang_hoa[${idx}][so_luong]`;

        // input don_gia
        const dg = tr.querySelector('input[name*="[don_gia]"]');
        if (dg) dg.name = `hang_hoa[${idx}][don_gia]`;

        // input thanh_tien
        const tt = tr.querySelector('input[name*="[thanh_tien]"]');
        if (tt) tt.name = `hang_hoa[${idx}][thanh_tien]`;
    });
    rowIndex = rows.length;
}

        async function updateDonGiaAndTonKho(select) {
            const row = select.closest('tr');
            const ma_hang = select.value;
            const ma_kho = document.getElementById('ma_kho').value;
            
            const hang = hangHoaList.find(h => h.ma_hang === ma_hang);
            const donGiaInput = row.querySelector('input[name$="[don_gia]"]');
            donGiaInput.value = hang ? Number(hang.don_gia).toLocaleString('vi-VN') : '';
            
            // Lấy tồn kho nếu có kho được chọn
            if (ma_kho && ma_hang) {
                try {
                    const response = await fetch(`get_ton_kho.php?ma_kho=${encodeURIComponent(ma_kho)}&ma_hang=${encodeURIComponent(ma_hang)}`);
                    const result = await response.json();
                    if (result.success) {
                        const tonKhoEl = row.querySelector('[data-ton-kho]');
                        tonKhoEl.textContent = result.so_luong_ton;
                        tonKhoEl.dataset.tonKho = result.so_luong_ton;
                    }
                } catch (error) {
                    console.error('Lỗi lấy tồn kho:', error);
                }
            }
            
            tinhThanhTien(row.querySelector('input[name$="[so_luong]"]'));
        }

        function updateDonGia(select) {
            const row = select.closest('tr');
            const ma_hang = select.value;
            const hang = hangHoaList.find(h => h.ma_hang === ma_hang);
            const donGiaInput = row.querySelector('input[name$="[don_gia]"]');
            donGiaInput.value = hang ? Number(hang.don_gia).toLocaleString('vi-VN') : '';
            tinhThanhTien(row.querySelector('input[name$="[so_luong]"]'));
        }

        function checkTonKhoWarning(input) {
            const row = input.closest('tr');
            const so_luong = parseInt(input.value) || 0;
            const tonKhoEl = row.querySelector('[data-ton-kho]');
            const tonKho = parseInt(tonKhoEl?.dataset.tonKho || 0);
            
            // Hiển thị cảnh báo đỏ nếu nhập quá tồn kho
            if (so_luong > tonKho && tonKho > 0) {
                row.style.backgroundColor = '#fee2e2'; // Màu nền đỏ nhạt
                row.style.borderLeft = '4px solid #dc2626'; // Viền đỏ
                
                // Hiển thị tooltip cảnh báo
                input.style.borderColor = '#dc2626';
                input.title = `Cảnh báo: Nhập (${so_luong}) vượt quá tồn kho (${tonKho})`;
            } else {
                row.style.backgroundColor = '';
                row.style.borderLeft = '';
                input.style.borderColor = '';
                input.title = '';
            }
            
            tinhThanhTien(input);
        }

        function tinhThanhTien(input) {
            const row = input.closest('tr');
            const so_luong = parseFloat(input.value) || 0;
            const don_gia_str = row.querySelector('input[name$="[don_gia]"]').value.replace(/\./g, '');
            const don_gia = parseFloat(don_gia_str) || 0;
            const thanh_tien = so_luong * don_gia;

            row.querySelector('[data-thanh-tien]').textContent = thanh_tien.toLocaleString('vi-VN') + ' đ';
            row.querySelector('[data-thanh-tien]').dataset.thanhTien = thanh_tien;
            tinhTong();
        }

        function tinhTong() {
            let tong = 0;
            document.querySelectorAll('[data-thanh-tien]').forEach(el => {
                tong += parseFloat(el.dataset.thanhTien || 0);
            });
            document.getElementById('tongTien').textContent = tong.toLocaleString('vi-VN') + ' đ';
        }

        // Reload tồn kho cho tất cả các hàng khi kho được thay đổi
        async function reloadTonKhoAllRows() {
            const ma_kho = document.getElementById('ma_kho').value;
            if (!ma_kho) return;
            
            const rows = document.querySelectorAll('#chiTietTable tbody tr');
            for (const row of rows) {
                const maHangSelect = row.querySelector('select[name$="[ma_hang]"]');
                if (maHangSelect && maHangSelect.value) {
                    await updateTonKho(row, ma_kho, maHangSelect.value);
                    checkTonKhoWarning(row.querySelector('input[name$="[so_luong]"]'));
                }
            }
        }

        // Lấy tồn kho từ server và cập nhật UI
        async function updateTonKho(row, ma_kho, ma_hang) {
            try {
                const response = await fetch(`get_ton_kho.php?ma_kho=${encodeURIComponent(ma_kho)}&ma_hang=${encodeURIComponent(ma_hang)}`);
                const result = await response.json();
                if (result.success) {
                    const tonKhoEl = row.querySelector('[data-ton-kho]');
                    tonKhoEl.textContent = result.so_luong_ton;
                    tonKhoEl.dataset.tonKho = result.so_luong_ton;
                }
            } catch (error) {
                console.error('Lỗi lấy tồn kho:', error);
            }
        }

        // Tính tổng tồn kho của tất cả mặt hàng trong bảng cho một kho cụ thể
        async function getTotalStockForKho(ma_kho) {
            const rows = Array.from(document.querySelectorAll('#chiTietTable tbody tr'));
            const hangMas = rows.map(r => r.querySelector('select[name$="[ma_hang]"]')?.value).filter(Boolean);
            if (hangMas.length === 0) return 0;

            // Tạo một promise cho từng (ma_hang) để lấy tồn kho
            const fetches = hangMas.map(ma_hang => {
                return fetch(`get_ton_kho.php?ma_kho=${encodeURIComponent(ma_kho)}&ma_hang=${encodeURIComponent(ma_hang)}`)
                    .then(res => res.json())
                    .then(js => (js && js.success) ? Number(js.so_luong_ton || 0) : 0)
                    .catch(() => 0);
            });

            const results = await Promise.all(fetches);
            return results.reduce((s, v) => s + v, 0);
                                }

        // Tạo mã phiếu mới tự động
        async function generateMaPhieu() {
            const loaiPhieu = document.getElementById('loai_phieu').value;
            try {
                const response = await fetch(`?ajax_get_ma_phieu=1&loai=${encodeURIComponent(loaiPhieu)}`);
                const data = await response.json();
                if (data.ma_phieu) {
                    document.getElementById('ma_phieu_nhap').value = data.ma_phieu;
                }
            } catch (error) {
                console.error('Lỗi tạo mã phiếu:', error);
            }
        }

        // Kiểm tra mức dự trữ max trước khi submit
        function setupFormValidation() {
            const phieuNhapForm = document.getElementById('phieuNhapForm');
            if (!phieuNhapForm) {
                console.error('Không tìm thấy form phieuNhapForm');
                return;
            }

            phieuNhapForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const form = e.target;
                const maKho = document.getElementById('ma_kho').value;

                console.log('Form submit, ma_kho:', maKho);

                // Nếu không chọn kho, không kiểm tra
                if (!maKho) {
                    console.warn('Chưa chọn kho');
                    form.submit();
                    return;
                }

                const warnings = [];

                // Kiểm tra từng hàng
                const rows = document.querySelectorAll('#chiTietTable tbody tr');
                console.log('Số hàng kiểm tra:', rows.length);

                for (const row of rows) {
                    const select = row.querySelector('select[name$="[ma_hang]"]');
                    const soLuongInput = row.querySelector('input[name$="[so_luong]"]');

                    if (!select || !soLuongInput) continue;

                    const maHang = select.value;
                    const soLuong = parseInt(soLuongInput.value) || 0;

                    if (!maHang || soLuong <= 0) continue;

                    console.log('Kiểm tra:', maHang, soLuong);

                    try {
                        // Kiểm tra mức dự trữ qua AJAX
                        const response = await fetch('kiem_tra_muc_du_tru.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                ma_kho: maKho,
                                ma_hang: maHang,
                                so_luong_nhap: soLuong
                            })
                        });

                        const result = await response.json();
                        
                        console.log('Kết quả kiểm tra:', result);

                        if (result.error) {
                            console.error('Lỗi API:', result.error);
                            continue;
                        }

                        if (result.warning) {
                            warnings.push(result.warning);
                            console.log('Có cảnh báo:', result.warning);
                        }
                    } catch (error) {
                        console.error('Lỗi kiểm tra mức dự trữ:', error);
                    }
                }

                console.log('Tổng cảnh báo:', warnings.length);

                // Nếu có cảnh báo, hiển thị modal
                if (warnings.length > 0) {
                    console.log('Hiển thị modal cảnh báo với', warnings.length, 'cảnh báo');
                    showWarningModal(warnings);
                    return;
                }

                // Nếu không có cảnh báo, submit ngay
                console.log('Không có cảnh báo, submit form');
                form.submit();
            });
        }

        // Hiển thị modal cảnh báo
        function showWarningModal(warnings) {
            const modal = document.getElementById('warningModal');
            const content = document.getElementById('warningContent');

            // Tạo nội dung cảnh báo
            let html = `
                <div class="flex items-start gap-3 mb-4">
                    <span class="material-symbols-outlined text-yellow-600 text-2xl mt-1">warning</span>
                    <div>
                        <p class="font-medium text-gray-900 dark:text-white mb-2">Phát hiện mặt hàng sẽ vượt quá mức dự trữ tối đa sau khi nhập:</p>
                        <ul class="space-y-2">
                            ${warnings.map(warning => `<li class="text-sm text-gray-700 dark:text-gray-300">• ${warning}</li>`).join('')}
                        </ul>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-3">
                            Bạn có muốn tiếp tục nhập không? Hệ thống sẽ vẫn cho phép tạo phiếu nhập.
                        </p>
                    </div>
                </div>
            `;

            content.innerHTML = html;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }

        // Hủy nhập
        function cancelImport() {
            const modal = document.getElementById('warningModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        // Tiếp tục nhập
        function proceedImport() {
            const modal = document.getElementById('warningModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');

            // Submit form
            const form = document.getElementById('phieuNhapForm');
            form.submit();
        }

        // Reset form và cập nhật mã phiếu khi click "Thêm tiếp"
        async function resetFormAndCloseModal() {
            const modal = document.getElementById('successModal');
            const loaiPhieu = document.getElementById('loai_phieu').value;
            
            try {
                // Lấy mã phiếu mới từ server
                const response = await fetch(`?ajax_get_ma_phieu=1&loai=${loaiPhieu}`);
                const data = await response.json();
                
                if (data.ma_phieu) {
                    // Cập nhật mã phiếu trong form
                    document.getElementById('ma_phieu_nhap').value = data.ma_phieu;
                }
            } catch (error) {
                console.error('Lỗi khi lấy mã phiếu mới:', error);
            }
            
            // Ẩn modal
            modal.style.display = 'none';
            
            // Reset form
            const form = document.getElementById('phieuNhapForm');
            
            // Reset các field nhập liệu (giữ lại ngày hiện tại)
            document.getElementById('ma_ncc').value = '';
            document.getElementById('ma_loai_kho').value = '';
            document.getElementById('ma_kho').value = '';
            document.getElementById('don_vi_giao').value = '';
            document.getElementById('nguoi_giao').value = '';
            
            // Reset table chi tiết
            const tbody = document.querySelector('#chiTietTable tbody');
            tbody.innerHTML = '<tr><td colspan="5" class="py-8 text-center text-text-secondary">Chọn loại kho để hiển thị danh sách mặt hàng...</td></tr>';
            
            // Reset tổng tiền
            document.getElementById('tongTien').textContent = '0 đ';
            rowIndex = 0;
        }

        // Khởi tạo
        document.addEventListener('DOMContentLoaded', () => {
            console.log('DOM loaded, setup form validation');
            switchTab('vat_tu'); // Mặc định tab vật tư
            setupFormValidation(); // Setup form validation
        });

        // ====== FUNCTIONS FOR LEFT PANEL (Phiếu giao hàng) ======
        let selectedPhieu = null;

        // Tải danh sách phiếu giao hàng theo nhà cung cấp
        async function loadPhieuGiao() {
            const maNcc = document.getElementById('ncc_filter').value;
            const listContainer = document.getElementById('phieu_giao_list');

            if (!maNcc) {
                listContainer.innerHTML = `
                    <div class="text-center text-text-secondary py-12">
                        <span class="material-symbols-outlined text-5xl block mb-4 opacity-20">folder_open</span>
                        <p class="text-sm">Chọn nhà cung cấp để xem phiếu giao hàng</p>
                    </div>
                `;
                return;
            }

            try {
                const response = await fetch(`get_phieu_giao_by_ncc.php?ma_ncc=${encodeURIComponent(maNcc)}`);
                const result = await response.json();

                if (result.error) {
                    listContainer.innerHTML = `<div class="p-4 text-red-600">${result.error}</div>`;
                    return;
                }

                if (!result.data || result.data.length === 0) {
                    listContainer.innerHTML = `
                        <div class="text-center text-text-secondary py-12">
                            <span class="material-symbols-outlined text-5xl block mb-4 opacity-20">inbox</span>
                            <p class="text-sm">Không có phiếu giao hàng</p>
                        </div>
                    `;
                    return;
                }

                // Tạo danh sách phiếu giao hàng
                let html = '<div class="divide-y">';
                result.data.forEach(phieu => {
                    const isSelected = selectedPhieu && selectedPhieu.MaLenh === phieu.MaLenh ? 'ring-2 ring-primary' : '';
                    html += `
                        <div class="p-4 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors ${isSelected}" onclick="selectPhieu('${phieu.MaLenh}')">
                            <div class="flex justify-between items-start mb-2">
                                <span class="font-semibold text-sm">${phieu.MaLenh}</span>
                                <span class="text-xs px-2 py-1 rounded-full ${getStatusColor(phieu.TrangThai)}">
                                    ${phieu.TrangThai}
                                </span>
                            </div>
                            <div class="text-xs text-text-secondary space-y-1">
                                <p><strong>Ngày lập:</strong> ${phieu.NgayLap}</p>
                                <p><strong>Nhà cung cấp:</strong> ${phieu.ten_ncc || '-'}</p>
                            </div>
                        </div>
                    `;
                });
                html += '</div>';
                listContainer.innerHTML = html;
            } catch (error) {
                console.error('Lỗi tải danh sách phiếu:', error);
                listContainer.innerHTML = `<div class="p-4 text-red-600">Lỗi: ${error.message}</div>`;
            }
        }

        // Chọn phiếu giao hàng
        async function selectPhieu(maPhieu) {
            try {
                const response = await fetch(`get_chi_tiet_phieu_giao.php?ma_phieu=${encodeURIComponent(maPhieu)}`);
                const result = await response.json();

                if (result.error) {
                    alert('Lỗi: ' + result.error);
                    return;
                }

                selectedPhieu = result.phieu;
                console.log('Selected phieu:', selectedPhieu);

                // Reload danh sách để highlight phiếu được chọn
                const maNcc = document.getElementById('ncc_filter').value;
                await loadPhieuGiao();

                // Hiển thị chi tiết phiếu giao hàng
                displayPhieuGiaoDetail(result);
            } catch (error) {
                console.error('Lỗi chọn phiếu:', error);
                alert('Lỗi: ' + error.message);
            }
        }

        // Hiển thị chi tiết phiếu giao hàng
        function displayPhieuGiaoDetail(result) {
            const phieu = result.phieu;
            const chiTiet = result.chi_tiet;
            const tongTien = result.tong_thanh_tien;

            let detailHtml = `
                <div class="space-y-4 text-sm">
                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg space-y-2">
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Mã lệnh kho:</span>
                            <span class="font-semibold">${phieu.MaLenh}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Ngày lập:</span>
                            <span>${phieu.NgayLap}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Nhà cung cấp:</span>
                            <span>${phieu.ten_ncc || '-'}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-text-secondary">Trạng thái:</span>
                            <span class="px-2 py-1 rounded-full text-xs ${phieu.TrangThai === 'Xuat' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}">${phieu.TrangThai || '-'}</span>
                        </div>
                    </div>

                    <!-- Chi tiết hàng hóa -->
                    <div class="border rounded-lg overflow-hidden">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-left">Mặt hàng</th>
                                    <th class="px-3 py-2 text-right">SL</th>
                                    <th class="px-3 py-2 text-right">Đơn giá</th>
                                    <th class="px-3 py-2 text-right">Thành tiền</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                ${chiTiet.map(ct => `
                                    <tr>
                                        <td class="px-3 py-2">${ct.ten_hang}</td>
                                        <td class="px-3 py-2 text-right">${ct.SoLuong}</td>
                                        <td class="px-3 py-2 text-right">${Number(ct.don_gia).toLocaleString('vi-VN')}</td>
                                        <td class="px-3 py-2 text-right">${Number(ct.SoLuong * ct.don_gia).toLocaleString('vi-VN')}</td>
                                    </tr>
                                `).join('')}
                            </tbody>
                        </table>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg">
                        <div class="flex justify-between font-bold">
                            <span>Tổng tiền:</span>
                            <span>${Number(tongTien).toLocaleString('vi-VN')} đ</span>
                        </div>
                    </div>

                    <!-- Nút xác nhận -->
                    ${phieu.TrangThai === 'da_xac_nhan' ? `
                        <div class="w-full px-4 py-3 bg-green-100 text-green-800 rounded-lg text-center font-medium">Đã xác nhận</div>
                    ` : `
                        <button type="button" onclick="confirmPhieuGiao()" class="w-full px-4 py-3 bg-primary hover:bg-primary-hover text-white rounded-lg font-medium transition-colors flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined">check_circle</span>
                            Xác nhận & Điền vào phiếu
                        </button>
                    `}
                </div>
            `;

            // Thêm chi tiết vào list container
            const listContainer = document.getElementById('phieu_giao_list');
            listContainer.innerHTML = detailHtml;
        }

        // Xác nhận phiếu giao hàng và điền vào form bên phải
        async function confirmPhieuGiao() {
            if (!selectedPhieu) {
                alert('Vui lòng chọn phiếu giao hàng');
                return;
            }

            const phieu = selectedPhieu;

            // Gọi endpoint để xác nhận lệnh kho trên server trước khi điền vào form
            try {
                const resp = await fetch('xac_nhan_lenh.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `ma_lenh=${encodeURIComponent(phieu.MaLenh)}`
                });
                const json = await resp.json();
                if (!json.success) {
                    alert('Không thể xác nhận phiếu giao: ' + (json.error || json.message));
                    return;
                }

                // Cập nhật trạng thái local và reload danh sách để UI phản ánh
                selectedPhieu.TrangThai = 'da_xac_nhan';
                await loadPhieuGiao();
                // Hiển thị lại chi tiết đã cập nhật
                await selectPhieu(phieu.MaLenh);

            } catch (e) {
                console.error('Lỗi xác nhận phiếu giao:', e);
                alert('Lỗi khi xác nhận phiếu giao: ' + e.message);
                return;
            }

            // Chuyển sang tab vật tư (hiển thị trường nhà cung cấp)
            switchTab('vat_tu');

            // Phiếu giao từ lenhkho là loại vật tư (có MaNCC)
            document.getElementById('loai_phieu').value = 'vat_tu';

            // Điền nhà cung cấp (form bên phải) và đồng bộ bộ lọc bên trái
            if (phieu.MaNCC) {
                const maNccSelect = document.getElementById('ma_ncc');
                if (maNccSelect) maNccSelect.value = phieu.MaNCC;

                const nccFilter = document.getElementById('ncc_filter');
                if (nccFilter) {
                    nccFilter.value = phieu.MaNCC;
                    // Reload danh sách phiếu giao để highlight
                    loadPhieuGiao();
                }
            }

            // Các field khác để user điền thêm (ngoài dữ liệu từ phiếu giao)
            // Vì lenhkho không chứa: người giao, đơn vị giao, kho
            const nguoiGiaoEl = document.getElementById('nguoi_giao');
            if (nguoiGiaoEl) nguoiGiaoEl.value = '';
            const donViGiaoEl = document.getElementById('don_vi_giao');
            if (donViGiaoEl) donViGiaoEl.value = '';

            // Lưu MaLenh để sau này có thể tra cứu
            document.getElementById('phieuNhapForm').dataset.maLenh = phieu.MaLenh;

            // Điền chi tiết hàng hóa từ lenhkho (với skipLoaiKhoCheck = true)
            const chiTiet = await loadChiTietPhieuGiaoWithRows();

            // Tự động cố gắng chọn loại kho nếu tất cả mặt hàng cùng loại
            try {
                if (Array.isArray(chiTiet) && chiTiet.length > 0) {
                    const loaiKhoSet = new Set();

                    chiTiet.forEach(ct => {
                        const hang = hangHoaList.find(h => h.ma_hang === ct.MaHang);
                        if (hang) {
                            // Tìm loại kho từ bản đồ loaiKhoToHangMap (value = ma_loai_hang)
                            Object.entries(loaiKhoToHangMap).forEach(([ma_loai_kho, ma_loai_hang]) => {
                                if (ma_loai_hang === hang.ma_loai_hang) loaiKhoSet.add(ma_loai_kho);
                            });
                        }
                    });

                    if (loaiKhoSet.size === 1) {
                        const loaiKho = Array.from(loaiKhoSet)[0];
                        // Chỉ ẩn/hiện option loại kho chứ không reset bảng
                        filterLoaiKhoOptions([loaiKho]);
                        document.getElementById('ma_loai_kho').value = loaiKho;

                        // Tự chọn kho nếu chỉ còn 1 kho cho loại này
                        const maKhoSelect = document.getElementById('ma_kho');
                        // Xóa options kho hiện tại mà không xóa table
                        maKhoSelect.innerHTML = '<option value="">-- Chọn kho --</option>';
                        const filteredKho = khoList.filter(k => k.ma_loai_kho === loaiKho);
                        filteredKho.forEach(k => {
                            const opt = document.createElement('option');
                            opt.value = k.ma_kho;
                            opt.textContent = k.ten_kho;
                            maKhoSelect.appendChild(opt);
                        });

                        if (filteredKho.length === 1) {
                            maKhoSelect.value = filteredKho[0].ma_kho;
                            // Cập nhật tồn kho cho các dòng
                            await reloadTonKhoAllRows();
                        }
                    }
                }
            } catch (err) {
                console.error('Lỗi khi xác định loại kho tự động:', err);
            }

            // Scroll đến form bên phải
            document.querySelector('.w-1/2:last-child').scrollIntoView({ behavior: 'smooth', block: 'start' });

            alert('Đã tự động điền mặt hàng & số lượng từ phiếu giao. Vui lòng kiểm tra và chọn kho nhập nếu cần.');
        }

        // Tải chi tiết phiếu giao hàng vào bảng
        function loadChiTietPhieuGiao() {
            if (!selectedPhieu) return;

            // Lấy chi tiết từ selectedPhieu (dùng MaLenh từ lenhkho)
            fetch(`get_chi_tiet_phieu_giao.php?ma_phieu=${encodeURIComponent(selectedPhieu.MaLenh)}`)
                .then(res => res.json())
                .then(result => {
                    if (result.error) {
                        console.error('Lỗi:', result.error);
                        return;
                    }

                    const chiTiet = result.chi_tiet;
                    const tbody = document.querySelector('#chiTietTable tbody');
                    tbody.innerHTML = '';

                    chiTiet.forEach((ct, index) => {
                        addRow(ct.MaHang, ct.SoLuong, ct.don_gia);
                    });

                    tinhTong();
                })
                .catch(err => console.error('Lỗi tải chi tiết:', err));
        }

        // Tải chi tiết phiếu giao hàng và tự động thêm vào bảng (dùng khi xác nhận phiếu giao)
      // Tải chi tiết phiếu giao hàng và điền trực tiếp vào bảng
async function loadChiTietPhieuGiaoWithRows() {
    if (!selectedPhieu) return [];

    try {
        const res = await fetch(`get_chi_tiet_phieu_giao.php?ma_phieu=${encodeURIComponent(selectedPhieu.MaLenh)}`);
        const result = await res.json();

        if (result.error) {
            console.error('Lỗi:', result.error);
            alert('Lỗi khi lấy chi tiết phiếu: ' + result.error);
            return [];
        }

        const chiTiet = result.chi_tiet || [];
        const tbody = document.querySelector('#chiTietTable tbody');
        tbody.innerHTML = '';
        rowIndex = 0;

        for (const ct of chiTiet) {
            // Truyền luôn ma_hang, so_luong, don_gia vào addRow
            addRow(ct.MaHang, ct.SoLuong, ct.don_gia || '', true);
        }

        // Chuẩn hóa lại name index để đảm bảo gửi form chính xác
        normalizeRowIndexes();

        tinhTong();

        // Debug: log lại các dòng vừa tạo để kiểm tra giá trị select + qty
        try {
            const rowsData = Array.from(tbody.querySelectorAll('tr')).map(tr => {
                const sel = tr.querySelector('select');
                const q = tr.querySelector('input[name$="[so_luong]"]');
                return { ma_hang: sel ? sel.value : null, so_luong: q ? q.value : null };
            });
            console.log('Chi tiết đã thêm vào form:', rowsData);
        } catch (e) {
            console.error('Lỗi khi log chi tiết vừa thêm:', e);
        }

        // Reload tồn kho cho tất cả các dòng (nếu đã chọn kho)
        const maKho = document.getElementById('ma_kho').value;
        if (maKho) {
            await reloadTonKhoAllRows();
        }

        return chiTiet;

    } catch (err) {
        console.error('Lỗi tải chi tiết phiếu giao:', err);
        alert('Không thể tải chi tiết phiếu giao: ' + err.message);
        return [];
    }
}
        function getStatusColor(status) {
            const colors = {
                'cho_xac_nhan': 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                'da_xac_nhan': 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                'da_huy': 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200'
            };
            return colors[status] || 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
        }
    </script>

</body>

</html>