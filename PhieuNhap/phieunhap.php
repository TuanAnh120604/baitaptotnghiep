<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('phieunhap');
if (isset($_SERVER['HTTP_CACHE_CONTROL']) && $_SERVER['HTTP_CACHE_CONTROL'] === 'max-age=0' && !empty($_GET)) {
    header("Location: " . strtok($_SERVER["REQUEST_URI"], '?'));
    exit;
}

// Lấy thông tin user
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

// Lấy filter từ GET
$loai_filter = $_GET['loai'] ?? '';
$keyword     = trim($_GET['q'] ?? '');
$ngay        = $_GET['ngay'] ?? '';
// Lấy dữ liệu phiếu nhập
$phieu_nhap_list = [];
$error_message = '';

try {
    $sql = '
        SELECT 
            pn.ma_phieu_nhap,
            pn.ngay_nhap,
            pn.nguoi_giao,
            pn.don_vi_giao,
            pn.loai_nhap,
            k.ten_kho,
            ncc.ten_ncc
        FROM phieu_nhap pn
        LEFT JOIN kho k ON pn.ma_kho = k.ma_kho
        LEFT JOIN nha_cung_cap ncc ON pn.ma_ncc = ncc.ma_ncc
        WHERE 1=1
    ';

    $params = [];

    // Lọc theo quyền user
    if ($role === 'Thủ kho' && $ma_nd) {
        $sql .= ' AND pn.ma_kho IN (SELECT ma_kho FROM kho WHERE ma_nd = :ma_nd)';
        $params[':ma_nd'] = $ma_nd;
    } elseif ($role === 'Quản lý kho' && $ma_nd) {
        $sql .= ' AND pn.ma_kho IN (
            SELECT k.ma_kho 
            FROM kho k 
            JOIN phan_quyen pq ON k.ma_vung = pq.ma_vung AND k.ma_loai_kho = pq.ma_loai_kho 
            WHERE pq.ma_nd = :ma_nd
        )';
        $params[':ma_nd'] = $ma_nd;
    }
    // Admin và Ban giám đốc thấy hết, không thêm điều kiện

    // Lọc theo loại
    if (!empty($loai_filter)) {
        $sql .= ' AND pn.loai_nhap = :loai';
        $params[':loai'] = $loai_filter;
    }


    // Tìm kiếm theo người giao, đơn vị giao, nhà cung cấp
    if (!empty($keyword)) {
        $sql .= ' AND (
            pn.nguoi_giao LIKE :kw
            OR pn.don_vi_giao LIKE :kw
            OR ncc.ten_ncc LIKE :kw
        )';
        $params[':kw'] = '%' . $keyword . '%';
    }
    if ($ngay !== '') {
        $sql .= ' AND DATE(pn.ngay_nhap) = :ngay';
        $params[':ngay'] = $ngay;
    }

    $sql .= ' ORDER BY pn.ngay_nhap DESC, pn.ma_phieu_nhap DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $phieu_nhap_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'Lỗi khi lấy dữ liệu: ' . $e->getMessage();
}

// Hàm format ngày
function formatDate($date)
{
    if (empty($date)) return '-';
    $dateObj = new DateTime($date);
    return $dateObj->format('d/m/Y');
}

// Hàm hiển thị loại nhập
function getLoaiNhapText($loai)
{
    $map = [
        'vat_tu'     => 'Nhập vật tư',
        'thanh_pham' => 'Nhập thành phẩm',
    ];
    return $map[$loai] ?? ucfirst($loai ?? 'Không xác định');
}

// Hàm hiển thị ĐV giao
function getDVGiao($row)
{
    if (!empty($row['ten_ncc'] ?? '')) {
        return $row['ten_ncc'];
    }
    return $row['don_vi_giao'] ?? '-';
}
?>


<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Quản lý Phiếu Nhập</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Symbols+Outlined" rel="stylesheet" />
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        primary: "#2563eb",
                        "primary-dark": "#1d4ed8",
                        "background-light": "#f3f4f6",
                        "background-dark": "#111827",
                        "surface-light": "#ffffff",
                        "surface-dark": "#1f2937",
                        "border-light": "#e5e7eb",
                        "border-dark": "#374151",
                    },
                },
            },
        };
    </script>
</head>

<body class="bg-background-light dark:bg-background-dark text-gray-900 dark:text-gray-100 font-body antialiased transition-colors duration-200">

    <?php include '../include/sidebar.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-light dark:bg-background-dark">
        <?php include '../include/header.php'; ?>

        <div class="p-6 max-w-[1920px] mx-auto">
            <div class="mb-6 flex flex-col justify-between gap-4 md:flex-row md:items-center">
                <h2 class="text-2xl font-bold text-gray-900 dark:text-white">Danh sách phiếu nhập kho</h2>
                <div class="flex items-center gap-3">
                    <a href="xuat_excel_phieu_nhap.php?<?= http_build_query($_GET) ?>"
                        class="flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-3 text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-600 dark:bg-surface-dark dark:text-gray-200 dark:hover:bg-gray-700 transition-colors">
                        <span class="material-symbols-outlined">file_download</span>
                        <span class="text-[14px]">Xuất Excel</span>
                    </a>

                    <?php if (canCreate('phieunhap')): ?>
                    <a href="Them_phieu_nhap.php" class="flex items-center gap-2 rounded-lg bg-primary px-5 py-3 text-white shadow-lg shadow-blue-500/30 hover:bg-blue-700 transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                        <span class="material-symbols-outlined text-[16px]">add</span>
                        <span class="text-[14px]">Thêm phiếu nhập mới</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <div
                class="bg-white dark:bg-[#1a2332] p-5 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col justify-between gap-4">

                <form method="get" class="grid grid-cols-1 md:grid-cols-12 gap-10">
                    <div class="md:col-span-4 lg:col-span-5 relative">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Tìm
                            kiếm</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined text-[20px]">search</span>
                            <input name="q"
                                value="<?= htmlspecialchars($_GET['q'] ?? '') ?>"
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-primary/50 focus:border-primary text-slate-900 dark:text-white"
                                placeholder="Người giao, đơn vị giao, nhà cung cấp...">

                        </div>
                    </div>
                    <div class="md:col-span-3 lg:col-span-2">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Thời gian
                            xuất</label>
                        <input
                            type="date"
                            name="ngay"
                            value="<?= htmlspecialchars($_GET['ngay'] ?? '') ?>"
                            onchange="this.form.submit()"
                            class="rounded-lg border-gray-300 bg-gray-50 py-2.5 px-4 text-sm text-gray-900 focus:border-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-800 dark:text-white" />

                    </div>
                    <div class="md:col-span-3 lg:col-span-3">
                        <label class="block text-xs font-medium text-slate-500 dark:text-slate-400 mb-1.5">Loại
                            xuất</label>
                        <div class="relative">
                            <span
                                class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined text-[20px]">category</span>
                            <select name="loai"
                                onchange="this.form.submit()"
                                class="w-full pl-10 pr-8 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm focus:ring-2 focus:ring-primary/50 focus:border-primary appearance-none text-slate-900 dark:text-white cursor-pointer">
                                <option value="">Tất cả</option>
                                <option value="vat_tu" <?= ($_GET['loai'] ?? '') === 'vat_tu' ? 'selected' : '' ?>>Nhập vật tư</option>
                                <option value="thanh_pham" <?= ($_GET['loai'] ?? '') === 'thanh_pham' ? 'selected' : '' ?>>Nhập thành phẩm</option>
                            </select>

                        </div>
                    </div>
                    <div class="md:col-span-2 lg:col-span-2 flex items-end">
                        <a href="<?= strtok($_SERVER['REQUEST_URI'], '?') ?>"
                            class="w-full h-[42px] inline-flex items-center justify-center px-4 py-1 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-primary transition-colors">
                            Làm mới
                        </a>
                    </div>
                </form>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">
                    <?= htmlspecialchars($error_message) ?>
                </div>
            <?php endif; ?>

            <div class="overflow-hidden rounded-xl border border-border-light dark:border-border-dark bg-surface-light dark:bg-surface-dark shadow-sm mt-6">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-4 font-semibold text-center w-12"><input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700"></th>
                                <th class="px-4 py-4 font-semibold">Mã Phiếu</th>
                                <th class="px-4 py-4 font-semibold text-center">Ngày Nhập</th>
                                <th class="px-4 py-4 font-semibold">Người giao</th>
                                <th class="px-4 py-4 font-semibold">ĐV giao</th>
                                <th class="px-4 py-4 font-semibold">Ghi chú (Loại nhập)</th>
                                <th class="px-4 py-4 font-semibold">Kho Nhập</th>
                                <th class="px-4 py-4 font-semibold">Nhà Cung Cấp</th>
                                <th class="px-4 py-4 font-semibold text-right sticky right-0 bg-gray-50 dark:bg-gray-800 shadow-l">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (!empty($phieu_nhap_list)): ?>
                                <?php foreach ($phieu_nhap_list as $row): ?>
                                    <tr class="group hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                        <td class="px-4 py-4 text-center"><input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary dark:border-gray-600 dark:bg-gray-700"></td>
                                        <td class="px-4 py-4 font-medium text-primary"><?= htmlspecialchars($row['ma_phieu_nhap'] ?? '-') ?></td>
                                        <td class="px-4 py-4 text-center text-gray-600 dark:text-gray-300"><?= formatDate($row['ngay_nhap']) ?></td>
                                        <td class="px-4 py-4 text-gray-900 dark:text-white"><?= htmlspecialchars($row['nguoi_giao'] ?? '-') ?></td>
                                        <td class="px-4 py-4 text-gray-900 dark:text-white"><?= htmlspecialchars(getDVGiao($row)) ?></td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300">
                                            <?= htmlspecialchars(getLoaiNhapText($row['loai_nhap'] ?? '')) ?>
                                        </td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($row['ten_kho'] ?? '-') ?></td>
                                        <td class="px-4 py-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($row['ten_ncc'] ?? '-') ?></td>
                                        <td class="px-4 py-4 text-right sticky right-0 bg-white dark:bg-surface-dark group-hover:bg-gray-50 dark:group-hover:bg-transparent shadow-l">
                                            <div class="flex items-center justify-end gap-2">
                                                <button onclick="openDetailModal('nhap', '<?= htmlspecialchars($row['ma_phieu_nhap']) ?>')" class="p-1.5 rounded-md text-gray-500 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700 transition-colors" title="Xem chi tiết">
                                                    <span class="material-symbols-outlined text-lg">visibility</span>
                                                </button>
                                                <?php if (canEdit('phieunhap')): ?>
                                                <button onclick="openEditModal('<?= htmlspecialchars($row['ma_phieu_nhap']) ?>')"
                                                    class="p-1.5 rounded-md text-gray-500 hover:bg-blue-50 hover:text-primary dark:text-gray-400 dark:hover:bg-blue-900/30 dark:hover:text-blue-300 transition-colors"
                                                    title="Sửa">
                                                    <span class="material-symbols-outlined text-lg">edit</span>
                                                </button>
                                                <?php endif; ?>

                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                                        <?= $error_message ?: 'Không có dữ liệu phiếu nhập' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Phân trang đơn giản -->
                <div class="flex items-center justify-between border-t border-gray-200 bg-white px-6 py-4 dark:border-gray-700 dark:bg-gray-800">
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Hiển thị <span class="font-medium">1</span> đến <span class="font-medium"><?= count($phieu_nhap_list) ?></span> của <span class="font-medium"><?= count($phieu_nhap_list) ?></span> kết quả
                    </div>
                    <div class="flex items-center gap-2">
                        <button class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600" disabled>
                            <span class="material-symbols-outlined text-base">chevron_left</span>
                        </button>
                        <button class="inline-flex items-center rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600">
                            <span class="material-symbols-outlined text-base">chevron_right</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal Xem Chi Tiết -->
    <div id="detailModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-fade-in">
        <div class="w-full max-w-4xl bg-white dark:bg-surface-dark rounded-xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Chi tiết phiếu nhập</h3>
                <button onclick="closeDetailModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                <div id="detailContent" class="space-y-6">
                    <!-- Nội dung chi tiết sẽ được load bằng JS -->
                </div>
            </div>
            <div class="flex items-center justify-end gap-3 border-t border-gray-200 dark:border-gray-700 px-6 py-4 bg-gray-50 dark:bg-gray-800/50 flex-shrink-0">
                <button onclick="closeDetailModal()" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 transition-colors">
                    Đóng
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Sửa Phiếu Nhập -->
    <div id="editModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4 animate-fade-in">
        <div class="w-full max-w-4xl bg-white dark:bg-surface-dark rounded-xl shadow-2xl flex flex-col max-h-[90vh] overflow-hidden">
            <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex-shrink-0">
                <h3 class="text-xl font-bold text-gray-900 dark:text-white">Sửa phiếu nhập</h3>
                <button onclick="closeEditModal()" class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 focus:outline-none">
                    <span class="material-symbols-outlined text-2xl">close</span>
                </button>
            </div>
            <div class="p-6 overflow-y-auto custom-scrollbar flex-1">
                <div id="editContent" class="space-y-6">
                    <!-- Nội dung form sửa sẽ được load bằng JS -->
                </div>
            </div>
        </div>
    </div>

    <script>
        // Hàm mở modal chi tiết
        function openDetailModal(loai, ma_phieu) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');
            content.innerHTML = '<div class="flex justify-center items-center h-64"><span class="material-symbols-outlined animate-spin text-6xl text-primary">hourglass_empty</span></div>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            fetch(`../include/Chi_tiet.php?loai=${loai}&ma=${encodeURIComponent(ma_phieu)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<p class="text-center text-red-500 font-medium mt-8">${data.error}</p>`;
                        return;
                    }

                    const badgeClass = data.phieu.loai_nhap.includes('Nhập') ?
                        'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300' :
                        'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';

                    let html = `
                <div class="bg-gradient-to-r from-primary to-blue-700 text-white px-6 py-5 rounded-t-2xl">
                    <h3 class="text-xl font-bold">Chi tiết phiếu ${loai === 'nhap' ? 'nhập' : 'xuất'}</h3>
                    <p class="text-sm opacity-90 mt-1">Mã: <strong>${data.phieu.ma_phieu}</strong></p>
                </div>

                <div class="p-6 space-y-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Ngày</label>
                            <p class="text-lg font-medium">${data.phieu.ngay}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Người ${loai === 'nhap' ? 'giao' : 'nhận'}</label>
                            <p class="text-lg font-medium">${data.phieu.nguoi_giao || data.phieu.nguoi_nhan || '-'}</p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-800 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
                            <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Đơn vị</label>
                            <p class="text-lg font-medium">${data.phieu.don_vi_giao || data.phieu.don_vi_nhan || '-'}</p>
                        </div>
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            Kho: ${data.phieu.ten_kho || '-'}
                        </div>
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium ${badgeClass}">
                            ${data.phieu.loai_nhap}
                        </div>
                        <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-indigo-100 dark:bg-indigo-900/40 text-indigo-800 dark:text-indigo-300">
                            Nhà cung cấp / Đại lý: ${data.phieu.ten_ncc || '-'}
                        </div>
                    </div>

                    <div class="bg-white dark:bg-gray-900 rounded-xl border border-gray-200 dark:border-gray-800 overflow-hidden shadow-sm">
                        <div class="p-5 border-b border-gray-200 dark:border-gray-800 bg-gray-50 dark:bg-gray-800">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Chi tiết hàng hóa</h4>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th class="px-6 py-4 text-left font-medium text-gray-600 dark:text-gray-400">Tên hàng</th>
                                        <th class="px-6 py-4 text-right font-medium text-gray-600 dark:text-gray-400">Số lượng</th>
                                        <th class="px-6 py-4 text-right font-medium text-gray-600 dark:text-gray-400">Đơn giá</th>
                                        <th class="px-6 py-4 text-right font-medium text-gray-600 dark:text-gray-400">Thành tiền</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                                    ${data.chi_tiet.map(item => `
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition">
                                            <td class="px-6 py-4 font-medium">${item.ten_hang}</td>
                                            <td class="px-6 py-4 text-right">${item.so_luong.toLocaleString('vi-VN')}</td>
                                            <td class="px-6 py-4 text-right">${item.don_gia.toLocaleString('vi-VN')} ₫</td>
                                            <td class="px-6 py-4 text-right font-bold text-green-600 dark:text-green-400">${item.thanh_tien.toLocaleString('vi-VN')} ₫</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                                <tfoot class="bg-gray-50 dark:bg-gray-800 font-bold">
                                    <tr>
                                        <td colspan="3" class="px-6 py-4 text-right text-gray-900 dark:text-white">Tổng cộng:</td>
                                        <td class="px-6 py-4 text-right text-xl text-primary">${data.tong_thanh_tien.toLocaleString('vi-VN')} ₫</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            `;
                    content.innerHTML = html;
                })
                .catch(err => {
                    content.innerHTML = `<div class="text-center text-red-500 mt-12">
                <span class="material-symbols-outlined text-6xl">error</span>
                <p class="mt-4">Lỗi tải dữ liệu: ${err.message}</p>
            </div>`;
                });
        }

        function closeDetailModal() {
            document.getElementById('detailModal').classList.add('hidden');
            document.getElementById('detailModal').classList.remove('flex');
        }

        document.getElementById('detailModal').addEventListener('click', e => {
            if (e.target === document.getElementById('detailModal')) closeDetailModal();
        });

        // Hàm mở modal sửa phiếu nhập
        function openEditModal(ma_phieu) {
            const modal = document.getElementById('editModal');
            const content = document.getElementById('editContent');
            content.innerHTML = '<div class="flex justify-center items-center h-64"><span class="material-symbols-outlined animate-spin text-6xl text-primary">hourglass_empty</span></div>';
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            fetch(`ajax_edit_phieu_nhap.php?action=get_form&ma=${encodeURIComponent(ma_phieu)}`)
                .then(r => r.json())
                .then(data => {
                    if (data.error) {
                        content.innerHTML = `<p class="text-center text-red-500 font-medium mt-8">${data.error}</p>`;
                        return;
                    }

                    // Build the edit form
                    let formHtml = `
                        <form method="POST" class="bg-white dark:bg-surface-dark p-6 rounded-xl max-w-5xl mx-auto" onsubmit="return submitEditForm(this)">
                            <input type="hidden" name="ma_phieu" value="${data.phieu.ma_phieu_nhap}">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mã phiếu</label>
                                    <input value="${data.phieu.ma_phieu_nhap}" disabled class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 cursor-not-allowed focus:ring-0">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày nhập</label>
                                    <input type="date" name="ngay_nhap" value="${data.phieu.ngay_nhap}" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Người giao</label>
                                    <input name="nguoi_giao" value="${data.phieu.nguoi_giao || ''}" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors" placeholder="Nhập tên người giao">
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Đơn vị giao</label>
                                    <select name="don_vi_giao" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                        <option value="">-- Chọn đơn vị --</option>`;

                    data.don_vi_list.forEach(d => {
                        const selected = d.don_vi_giao === data.phieu.don_vi_giao ? 'selected' : '';
                        formHtml += `<option value="${d.don_vi_giao}" ${selected}>${d.don_vi_giao}</option>`;
                    });

                    formHtml += `
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Kho</label>
                                    <select name="ma_kho" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">`;

                    data.kho_list.forEach(k => {
                        const selected = k.ma_kho === data.phieu.ma_kho ? 'selected' : '';
                        formHtml += `<option value="${k.ma_kho}" ${selected}>${k.ten_kho}</option>`;
                    });

                    formHtml += `
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Nhà cung cấp</label>
                                    <select name="ma_ncc" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">`;

                    data.ncc_list.forEach(n => {
                        const selected = n.ma_ncc === data.phieu.ma_ncc ? 'selected' : '';
                        formHtml += `<option value="${n.ma_ncc}" ${selected}>${n.ten_ncc}</option>`;
                    });

                    formHtml += `
                                    </select>
                                </div>
                            </div>

                            <div class="mt-8">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Chi tiết hàng hóa</h3>
                                <div class="overflow-x-auto bg-white dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="w-full">
                                        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900 dark:text-white">Tên hàng</th>
                                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-white">Số lượng</th>
                                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900 dark:text-white">Đơn giá</th>
                                                <th class="px-6 py-4 text-right text-sm font-semibold text-gray-900 dark:text-white">Thành tiền</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">`;

                    data.chi_tiet.forEach((ct, i) => {
                        formHtml += `
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                                                <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-white">
                                                    ${ct.ten_hang}
                                                    <input type="hidden" name="hang_hoa[${i}][ma_hang]" value="${ct.ma_hang}">
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <input type="number" min="1" name="hang_hoa[${i}][so_luong]" value="${ct.so_luong_nhap}" onchange="tinh(this)"
                                                           class="w-24 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                                </td>
                                                <td class="px-6 py-4 text-center">
                                                    <input type="number" min="0" step="0.01" name="hang_hoa[${i}][don_gia]" value="${ct.don_gia}" onchange="tinh(this)"
                                                           class="w-32 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-white text-center focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                                                </td>
                                                <td class="px-6 py-4 text-right text-sm font-semibold text-green-600 dark:text-green-400" data-tt>
                                                    ${(ct.so_luong_nhap * ct.don_gia).toLocaleString('vi-VN')} đ
                                                </td>
                                            </tr>`;
                    });

                    formHtml += `
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="flex justify-end gap-4 mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                                <button type="button" onclick="closeEditModal()"
                                        class="px-6 py-3 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-gray-500 focus:ring-offset-2 transition-colors">
                                    Hủy
                                </button>
                                <button type="submit"
                                        class="px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium shadow-sm hover:shadow-md focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                                    <span class="flex items-center gap-2">
                                        <span class="material-symbols-outlined text-sm">save</span>
                                        Cập nhật
                                    </span>
                                </button>
                            </div>
                        </form>`;

                    content.innerHTML = formHtml;
                })
                .catch(err => {
                    content.innerHTML = `<div class="text-center text-red-500 mt-12">
                <span class="material-symbols-outlined text-6xl">error</span>
                <p class="mt-4">Lỗi tải dữ liệu: ${err.message}</p>
            </div>`;
                });
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.getElementById('editModal').classList.remove('flex');
        }

        // Hàm submit form sửa
        function submitEditForm(form) {
            const formData = new FormData(form);
            formData.append('action', 'update');

            fetch('ajax_edit_phieu_nhap.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(result => {
                if (result.success) {
                    closeEditModal();
                    // Reload the page to show updated data
                    window.location.reload();
                } else {
                    // Show error message
                    alert('Lỗi: ' + (result.error || 'Có lỗi xảy ra khi cập nhật'));
                }
            })
            .catch(err => {
                console.error('Submit error:', err);
                alert('Lỗi khi gửi dữ liệu: ' + err.message);
            });

            return false; // Prevent default form submission
        }

        document.getElementById('editModal').addEventListener('click', e => {
            if (e.target === document.getElementById('editModal')) closeEditModal();
        });

        // Hàm tính thành tiền trong modal sửa
        function tinh(el) {
            const tr = el.closest('tr');
            const sl = +tr.querySelector('[name$="[so_luong]"]').value || 0;
            const dg = +tr.querySelector('[name$="[don_gia]"]').value || 0;
            const ttElement = tr.querySelector('[data-tt]');
            ttElement.innerText = (sl * dg).toLocaleString('vi-VN') + ' đ';
        }
    </script>
</body>

</html>