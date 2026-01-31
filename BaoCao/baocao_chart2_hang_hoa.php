<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('thongke');

// --- 1. KHỞI TẠO VÀ LẤY THAM SỐ FILTER ---
$role = trim($_SESSION['role'] ?? '');
$ma_nd = $_SESSION['MaND'] ?? null;

$chart2_ma_vung = isset($_GET['chart2_ma_vung']) ? $_GET['chart2_ma_vung'] : '';
$chart2_loai_kho = isset($_GET['chart2_loai_kho']) ? $_GET['chart2_loai_kho'] : '';
$chart2_ma_kho = isset($_GET['chart2_ma_kho']) ? $_GET['chart2_ma_kho'] : ''; 
$chart2_arr_ma_hang = isset($_GET['chart2_arr_ma_hang']) ? $_GET['chart2_arr_ma_hang'] : []; 
$chart2_ngay_bat_dau = isset($_GET['chart2_ngay_bat_dau']) ? $_GET['chart2_ngay_bat_dau'] : date('Y-01-01');
$chart2_ngay_ket_thuc = isset($_GET['chart2_ngay_ket_thuc']) ? $_GET['chart2_ngay_ket_thuc'] : date('Y-m-d');

// --- 2. LẤY DATA CHO DROPDOWN ---
// (Logic giữ nguyên để đảm bảo quyền hạn và dữ liệu chính xác)

// A. Danh sách Vùng
$sql_vung = "SELECT * FROM vung_mien WHERE 1=1";
$params_vung = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM kho WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_vung .= " AND ma_vung IN (SELECT DISTINCT ma_vung FROM phan_quyen WHERE ma_nd = ?)";
    $params_vung[] = $ma_nd;
}
$stmt_vung = $pdo->prepare($sql_vung);
$stmt_vung->execute($params_vung);
$danh_sach_vung = $stmt_vung->fetchAll(PDO::FETCH_ASSOC);

// B. Danh sách Loại kho
$sql_loai_kho = "SELECT DISTINCT lk.* FROM loai_kho lk JOIN kho k ON lk.ma_loai_kho = k.ma_loai_kho WHERE 1=1";
$params_loai_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_nd = ?";
    $params_loai_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_loai_kho .= " AND k.ma_kho IN (SELECT k2.ma_kho FROM kho k2 JOIN phan_quyen pq ON k2.ma_vung = pq.ma_vung AND k2.ma_loai_kho = pq.ma_loai_kho WHERE pq.ma_nd = ?)";
    $params_loai_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) {
    $sql_loai_kho .= " AND k.ma_vung = ?";
    $params_loai_kho[] = $chart2_ma_vung;
}
$stmt_loai_kho = $pdo->prepare($sql_loai_kho);
$stmt_loai_kho->execute($params_loai_kho);
$danh_sach_loai_kho = $stmt_loai_kho->fetchAll(PDO::FETCH_ASSOC);

// C. Danh sách Kho cụ thể
$sql_kho = "SELECT ma_kho, ten_kho FROM kho WHERE 1=1";
$params_kho = [];
if ($role === 'Thủ kho' && $ma_nd) {
    $sql_kho .= " AND ma_nd = ?";
    $params_kho[] = $ma_nd;
} elseif ($role === 'Quản lý kho' && $ma_nd) {
    $sql_kho .= " AND ma_kho IN (SELECT k.ma_kho FROM kho k JOIN phan_quyen pq ON k.ma_vung = pq.ma_vung AND k.ma_loai_kho = pq.ma_loai_kho WHERE pq.ma_nd = ?)";
    $params_kho[] = $ma_nd;
}
if (!empty($chart2_ma_vung)) { $sql_kho .= " AND ma_vung = ?"; $params_kho[] = $chart2_ma_vung; }
if (!empty($chart2_loai_kho)) { $sql_kho .= " AND ma_loai_kho = ?"; $params_kho[] = $chart2_loai_kho; }
$sql_kho .= " ORDER BY ten_kho";
$stmt_kho = $pdo->prepare($sql_kho);
$stmt_kho->execute($params_kho);
$danh_sach_kho = $stmt_kho->fetchAll(PDO::FETCH_ASSOC);

// D. Danh sách Hàng hóa
// Chỉ lấy những mặt hàng có tồn kho > 0 tại ngày kết thúc (lọc theo kho/vùng/loại và quyền)
$params_hang = [];
// Xác định danh sách kho hợp lệ (dùng $danh_sach_kho đã lấy phía trên)
if (!empty($chart2_ma_kho)) {
    $valid_kho_ids = [$chart2_ma_kho];
} else {
    $valid_kho_ids = array_column($danh_sach_kho, 'ma_kho');
}
if (empty($valid_kho_ids)) $valid_kho_ids = ['0'];
$placeholders = implode(',', array_fill(0, count($valid_kho_ids), '?'));
$where_kho_sql = " AND t.ma_kho IN ($placeholders) ";

// Tham số: ngày kết thúc cho nhập và xuất, sau đó các id kho
$params_hang = [$chart2_ngay_ket_thuc, $chart2_ngay_ket_thuc];
foreach ($valid_kho_ids as $id) $params_hang[] = $id;

$sql_hang = "SELECT DISTINCT h.ma_hang, h.ten_hang, h.don_vi_tinh
FROM hang_hoa h
JOIN (
    SELECT t.ma_hang, SUM(t.qty) AS ton
    FROM (
        SELECT ct.ma_hang, ct.so_luong_nhap AS qty, pn.ma_kho, pn.ngay_nhap AS ngay, pn.trang_thai
        FROM ct_phieu_nhap ct JOIN phieu_nhap pn ON ct.ma_phieu_nhap = pn.ma_phieu_nhap
        WHERE pn.trang_thai = 'da_xac_nhan' AND pn.ngay_nhap <= ?
        UNION ALL
        SELECT ct.ma_hang, -ct.so_luong_xuat AS qty, px.ma_kho, px.ngay_xuat AS ngay, px.trang_thai
        FROM ct_phieu_xuat ct JOIN phieu_xuat px ON ct.ma_phieu_xuat = px.ma_phieu_xuat
        WHERE px.trang_thai = 'da_xac_nhan' AND px.ngay_xuat <= ?
    ) t
    WHERE 1=1 $where_kho_sql
    GROUP BY t.ma_hang
    HAVING SUM(t.qty) > 0
) s ON h.ma_hang = s.ma_hang
ORDER BY h.ten_hang";

$stmt_hang = $pdo->prepare($sql_hang);
$stmt_hang->execute($params_hang);
$danh_sach_hang = $stmt_hang->fetchAll(PDO::FETCH_ASSOC);


// --- 3. TÍNH TOÁN DỮ LIỆU ---
$labels_ngay = [];
$ts_cur = strtotime($chart2_ngay_bat_dau);
$ts_end = strtotime($chart2_ngay_ket_thuc);
while ($ts_cur <= $ts_end) {
    $labels_ngay[] = date('Y-m-d', $ts_cur);
    $ts_cur = strtotime('+1 day', $ts_cur);
}

$chart_colors = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#9333ea', '#0891b2', '#db2777', '#4b5563'];
$datasets = [];
$summary_cards = [];
$color_index = 0;

if (!empty($chart2_arr_ma_hang)) {
    foreach ($chart2_arr_ma_hang as $ma_hang_selected) {
        $ten_hang = "";
        $dvt = "";
        foreach($danh_sach_hang as $h){
            if($h['ma_hang'] == $ma_hang_selected){
                $ten_hang = $h['ten_hang'];
                $dvt = $h['don_vi_tinh'];
                break;
            }
        }
        if(empty($ten_hang)) continue;

        $where_kho_sql = "";
        $params_ton = [];

        if (!empty($chart2_ma_kho)) {
            $where_kho_sql = " AND ma_kho = ? ";
            $params_ton[] = $chart2_ma_kho;
        } else {
            $valid_kho_ids = array_column($danh_sach_kho, 'ma_kho');
            if(empty($valid_kho_ids)) $valid_kho_ids = ['0'];
            $placeholders = implode(',', array_fill(0, count($valid_kho_ids), '?'));
            $where_kho_sql = " AND ma_kho IN ($placeholders) ";
            foreach($valid_kho_ids as $id) $params_ton[] = $id;
        }

        // Tồn đầu
        $p_dau_ky = array_merge($params_ton, [$ma_hang_selected, $chart2_ngay_bat_dau]);
        $stmt = $pdo->prepare("SELECT SUM(ct.so_luong_nhap) FROM phieu_nhap pn JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap WHERE pn.trang_thai = 'da_xac_nhan' $where_kho_sql AND ct.ma_hang = ? AND pn.ngay_nhap < ?");
        $stmt->execute($p_dau_ky);
        $nhap_dk = $stmt->fetchColumn() ?: 0;
        $stmt = $pdo->prepare("SELECT SUM(ct.so_luong_xuat) FROM phieu_xuat px JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat WHERE px.trang_thai = 'da_xac_nhan' $where_kho_sql AND ct.ma_hang = ? AND px.ngay_xuat < ?");
        $stmt->execute($p_dau_ky);
        $xuat_dk = $stmt->fetchColumn() ?: 0;
        $ton_dau_ky = $nhap_dk - $xuat_dk;

        // Trong kỳ
        $changes = array_fill_keys($labels_ngay, 0);
        $p_trong_ky = array_merge($params_ton, [$ma_hang_selected, $chart2_ngay_bat_dau, $chart2_ngay_ket_thuc]);
        
        $stmt = $pdo->prepare("SELECT DATE(pn.ngay_nhap) as ngay, SUM(ct.so_luong_nhap) as sl FROM phieu_nhap pn JOIN ct_phieu_nhap ct ON pn.ma_phieu_nhap = ct.ma_phieu_nhap WHERE pn.trang_thai = 'da_xac_nhan' $where_kho_sql AND ct.ma_hang = ? AND pn.ngay_nhap BETWEEN ? AND ? GROUP BY DATE(pn.ngay_nhap)");
        $stmt->execute($p_trong_ky);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $changes[$r['ngay']] += $r['sl'];

        $stmt = $pdo->prepare("SELECT DATE(px.ngay_xuat) as ngay, SUM(ct.so_luong_xuat) as sl FROM phieu_xuat px JOIN ct_phieu_xuat ct ON px.ma_phieu_xuat = ct.ma_phieu_xuat WHERE px.trang_thai = 'da_xac_nhan' $where_kho_sql AND ct.ma_hang = ? AND px.ngay_xuat BETWEEN ? AND ? GROUP BY DATE(px.ngay_xuat)");
        $stmt->execute($p_trong_ky);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) $changes[$r['ngay']] -= $r['sl'];

        $data_points = [];
        $curr = $ton_dau_ky;
        foreach ($labels_ngay as $d) {
            $curr += $changes[$d];
            $data_points[] = $curr;
        }

        $this_color = $chart_colors[$color_index % count($chart_colors)];
        $datasets[] = ['label' => $ten_hang, 'data' => $data_points, 'borderColor' => $this_color, 'dvt' => $dvt];
        $summary_cards[] = ['ten_hang' => $ten_hang, 'ton_cuoi' => end($data_points), 'dvt' => $dvt, 'color' => $this_color];
        $color_index++;
    }
}
?>

<!DOCTYPE html>
<html lang="vi" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Biểu Đồ Biến Động Hàng Hóa</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
        .summary-card:hover { transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); transition: all 0.3s ease; }
        /* Tùy chỉnh thanh cuộn cho multi-select */
        select[multiple]::-webkit-scrollbar { width: 4px; }
        select[multiple]::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
    </style>
</head>

<body class="bg-[#f8fafc] dark:bg-gray-900 text-slate-800 dark:text-slate-200 h-screen flex flex-col overflow-hidden">
    <?php include '../include/sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <?php include '../include/header.php'; ?>

        <main class="flex-1 overflow-y-auto p-4 md:p-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col mb-6">
                    <div class="mb-6">
                    <a href="baocao_bancandoi.php"
                       class="inline-flex items-center px-4 py-2 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">
                        ← Quay lại
                    </a>
                 <div class="text-center mb-10">
                    <h1 class="text-3xl md:text-4xl font-bold text-blue-500 dark:text-blue-400 tracking-tight uppercase">
                        📊 Biến động tồn của mặt hàng
                    </h1>
                   
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 p-6 mb-6">
    <h3 class="text-lg font-bold mb-6 text-gray-800 dark:text-gray-100 uppercase tracking-tight">
        Bộ lọc biểu đồ
    </h3>

    <form method="GET" id="filterForm" class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
            <div class="flex flex-col">
                <label class="text-[11px] font-bold uppercase text-slate-400 mb-2 tracking-wider">Vùng miền</label>
                <select name="chart2_ma_vung" class="h-[42px] w-full bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-3 text-sm focus:ring-2 focus:ring-blue-500 transition-all">
                    <option value="">Tất cả vùng</option>
                    <?php foreach ($danh_sach_vung as $v): ?>
                        <option value="<?= $v['ma_vung'] ?>" <?= $chart2_ma_vung == $v['ma_vung'] ? 'selected' : '' ?>><?= $v['ten_vung'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-[11px] font-bold uppercase text-slate-400 mb-2 tracking-wider">Loại kho</label>
                <select name="chart2_loai_kho" class="h-[42px] w-full bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-3 text-sm focus:ring-2 focus:ring-blue-500 transition-all" >
                    <option value="">Tất cả loại kho</option>
                    <?php foreach ($danh_sach_loai_kho as $lk): ?>
                        <option value="<?= $lk['ma_loai_kho'] ?>" <?= $chart2_loai_kho == $lk['ma_loai_kho'] ? 'selected' : '' ?>><?= $lk['ten_loai_kho'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-[11px] font-bold uppercase text-slate-400 mb-2 tracking-wider">Kho cụ thể</label>
                <select name="chart2_ma_kho" class="h-[42px] w-full bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-3 text-sm font-medium focus:ring-2 focus:ring-blue-500 transition-all">
                    <option value="">Chọn kho</option>
                    <?php foreach ($danh_sach_kho as $k): ?>
                        <option value="<?= $k['ma_kho'] ?>" <?= $chart2_ma_kho == $k['ma_kho'] ? 'selected' : '' ?>><?= $k['ten_kho'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col">
                <label class="text-[11px] font-bold uppercase text-slate-400 mb-2 tracking-wider">Mặt hàng</label>
                <select name="chart2_arr_ma_hang[]" class="h-[42px] w-full bg-white dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-2 py-1 text-sm focus:ring-2 focus:ring-blue-500 overflow-y-auto">
                     <option value="">Chọn mặt hàng</option>
                    <?php foreach ($danh_sach_hang as $h): ?>
                        <option value="<?= $h['ma_hang'] ?>" <?= in_array($h['ma_hang'], $chart2_arr_ma_hang) ? 'selected' : '' ?>><?= $h['ten_hang'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="flex flex-col justify-end">
               <button type="submit" class="px-6 py-2.5 bg-blue-500 hover:bg-blue-700 text-white font-medium rounded-lg shadow transition-colors">Lọc dữ liệu</button>
            </div>
        </div>

        <div class="pt-4 border-t border-slate-100 dark:border-gray-700">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                <div class="lg:col-span-2 flex flex-col">
                    <label class="text-[11px] font-bold uppercase text-slate-400 mb-2 tracking-wider">Khoảng thời gian (Từ ngày - Đến ngày)</label>
                    <div class="flex items-center gap-2">
                        <input type="date" name="chart2_ngay_bat_dau" value="<?= $chart2_ngay_bat_dau ?>" class="h-[42px] flex-1 bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-3 text-sm focus:ring-2 focus:ring-blue-500">
                        <span class="text-slate-300">—</span>
                        <input type="date" name="chart2_ngay_ket_thuc" value="<?= $chart2_ngay_ket_thuc ?>" class="h-[42px] flex-1 bg-slate-50 dark:bg-gray-700 border border-slate-200 dark:border-gray-600 rounded-lg px-3 text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>


                <?php if (!empty($summary_cards)): ?>
                <div class="mb-6">
                    <h3 class="text-sm font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Tổng hợp tồn kho cuối kỳ (<?= date('d/m/Y', strtotime($chart2_ngay_ket_thuc)) ?>)</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                        <?php foreach ($summary_cards as $card): ?>
                            <div class="summary-card bg-white dark:bg-gray-800 p-4 rounded-xl shadow-sm border border-slate-100 dark:border-gray-700 relative overflow-hidden">
                                <div class="absolute top-0 left-0 w-1 h-full" style="background-color: <?= $card['color'] ?>;"></div>
                                <p class="text-slate-400 text-[10px] font-bold uppercase mb-1 truncate" title="<?= $card['ten_hang'] ?>">
                                    <?= $card['ten_hang'] ?>
                                </p>
                                <h4 class="text-lg font-bold text-slate-800 dark:text-white">
                                    <?= number_format($card['ton_cuoi'], 0, ',', '.') ?> <span class="text-xs font-normal text-slate-500"><?= $card['dvt'] ?></span>
                                </h4>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-slate-200 dark:border-gray-700 p-4">
                    <div class="h-[500px]">
                        <?php if (empty($chart2_arr_ma_hang)): ?>
                            <div class="flex flex-col items-center justify-center h-full text-slate-400 italic">
                                <p>Chọn mặt hàng để xem biểu đồ</p>
                            </div>
                        <?php else: ?>
                            <canvas id="chartHangHoa"></canvas>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
    <?php if (!empty($datasets)): ?>
        const ctx = document.getElementById('chartHangHoa').getContext('2d');
        const labels = <?= json_encode($labels_ngay) ?>;
        const rawDatasets = <?= json_encode($datasets) ?>;

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: rawDatasets.map(ds => ({
                    label: ds.label,
                    data: ds.data,
                    borderColor: ds.borderColor,
                    backgroundColor: ds.borderColor,
                    // CẤU HÌNH NHƯ HÌNH ẢNH YÊU CẦU:
                    fill: false,            // Không đổ màu khối
                    tension: 0.3,           // Đường cong mềm mại
                    borderWidth: 2.5,       // Độ dày đường
                    pointRadius: 4,         // Kích thước nút
                    pointBackgroundColor: ds.borderColor, 
                    pointBorderColor: "#ffffff", // Viền trắng quanh nút
                    pointBorderWidth: 2,
                    pointHoverRadius: 6,
                    dvt: ds.dvt 
                }))
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { usePointStyle: true, padding: 20, font: { family: 'Inter', size: 12 } }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(255, 255, 255, 0.95)',
                        titleColor: '#1e293b',
                        bodyColor: '#475569',
                        borderColor: '#e2e8f0',
                        borderWidth: 1,
                        padding: 10,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + 
                                       context.parsed.y.toLocaleString('vi-VN') + ' ' + (context.dataset.dvt || '');
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: '#94a3b8', font: { size: 11 } } },
                    y: {
                        beginAtZero: true,
                        border: { display: false },
                        grid: { color: '#f1f5f9' },
                        ticks: { color: '#94a3b8', callback: v => v.toLocaleString('vi-VN') }
                    }
                }
            }
        });
    <?php endif; ?>
    </script>
</body>
</html>