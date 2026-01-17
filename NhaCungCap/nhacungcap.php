     <?php
     include '../include/connect.php';
     include '../include/permissions.php';
     checkAccess('nhacungcap');

     // Khởi tạo biến thông báo
     $message = '';
     $message_type = ''; // 'success' hoặc 'error'

     // Lấy thông báo từ URL parameter (từ redirect)
     if (isset($_GET['status']) && isset($_GET['message'])) {
         $message_type = $_GET['status'];
         $message = urldecode($_GET['message']);
     }
     
     $nha_cung_cap = [];
     try {
       $stmt = $pdo->prepare('SELECT ma_ncc, ten_ncc, dia_chi, sdt, hop_dong FROM nha_cung_cap ORDER BY ma_ncc');
       $stmt->execute();
       $nha_cung_cap = $stmt->fetchAll(PDO::FETCH_ASSOC);
     } catch (Exception $e) {
       $error_message = 'Lỗi khi lấy dữ liệu: ' . $e->getMessage();
     }
     
    ?>

     <!DOCTYPE html>
     <html lang="vi">

     <head>
         <meta charset="utf-8" />
         <meta content="width=device-width, initial-scale=1.0" name="viewport" />
         <title>Quản lý Đại lý và Nhà cung cấp - Agent Management System</title>
         <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
         <link href="https://fonts.googleapis.com" rel="preconnect" />
         <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
         <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
             rel="stylesheet" />
         <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
         <script>
         tailwind.config = {
             darkMode: "class",
             theme: {
                 extend: {
                     colors: {
                         primary: "#2563EB", // Standard Tailwind Blue-600
                         "primary-dark": "#1D4ED8", // Darker blue for hover
                         "background-light": "#F3F4F6", // Gray-100
                         "background-dark": "#111827", // Gray-900
                         "surface-light": "#FFFFFF",
                         "surface-dark": "#1F2937", // Gray-800
                         "border-light": "#E5E7EB", // Gray-200
                         "border-dark": "#374151", // Gray-700
                     },
                     fontFamily: {
                         sans: ['Inter', 'sans-serif'],
                     },
                     borderRadius: {
                         DEFAULT: "0.5rem", // 8px
                     },
                 },
             },
         };
         </script>
         <style>
         body {
             font-family: 'Inter', sans-serif;
         }

         .material-symbols-outlined {
             font-variation-settings: 'FILL'0, 'wght'400, 'GRAD'0, 'opsz'24;
         }

         .icon-fill {
             font-variation-settings: 'FILL'1, 'wght'400, 'GRAD'0, 'opsz'24;
         }
         </style>
     </head>

     <body
         class="bg-background-light dark:bg-background-dark text-gray-800 dark:text-gray-100 font-sans antialiased min-h-screen transition-colors duration-200">
         <?php include '../include/sidebar.php'; ?>

         <main class="flex-1 flex flex-col h-screen overflow-hidden relative">

             <?php include '../include/header.php'; ?>
             <!-- Toast Notification -->
             <?php if (!empty($message)): ?>
             <div id="toast"
                 class="fixed top-4 right-4 z-50 p-4 rounded-lg shadow-lg border flex items-center gap-3 <?php echo $message_type === 'success' ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-800 text-green-800 dark:text-green-200' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800 text-red-800 dark:text-red-200'; ?> animate-fadeInDown">
                 <span class="material-icons-round">
                     <?php echo $message_type === 'success' ? 'check_circle' : 'error'; ?>
                 </span>
                 <span><?php echo htmlspecialchars($message); ?></span>
                 <button onclick="document.getElementById('toast').remove()" class="ml-2 hover:opacity-70">
                     <span class="material-icons-round text-lg">close</span>
                 </button>
             </div>
             <style>
             @keyframes fadeInDown {
                 from {
                     opacity: 0;
                     transform: translateY(-20px);
                 }

                 to {
                     opacity: 1;
                     transform: translateY(0);
                 }
             }

             .animate-fadeInDown {
                 animation: fadeInDown 0.3s ease-out;
             }
             </style>
             <script>
             window.history.replaceState({}, document.title, window.location.pathname);
             setTimeout(() => {
                 const toast = document.getElementById('toast');
                 if (toast) {
                     toast.style.opacity = '0';
                     toast.style.transition = 'opacity 0.3s ease-out';
                     setTimeout(() => toast.remove(), 300);
                 }
             }, 5000);
             </script>
             <?php endif; ?>
             <div class="flex-1 overflow-y-auto p-4 md:p-8 bg-background-light dark:bg-background-dark">
                 <div class="flex py-3 flex-col sm:flex-row sm:items-center justify-between gap-4">
                     <div class="flex flex-col gap-1">
                         <h1 class="text-[#111418] dark:text-white text-2xl font-black tracking-tight">Quản lý danh sách
                             nhà cung cấp
                         </h1>
                     </div>
                 </div>
                 <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                     <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                         <div class="relative rounded-md shadow-sm w-full sm:w-80">
                             <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                 <span class="material-icons-round text-gray-400 text-lg">search</span>
                             </div>
                             <input
                                 class="focus:ring-primary focus:border-primary block w-full pl-10 sm:text-sm border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 py-2.5"
                                 id="search" name="search" placeholder="Tìm kiếm theo tên NCC, Mã NCC, SĐT..."
                                 type="text" />
                         </div>
                         <div class="relative w-full sm:w-48">
                             <select id="filterAddress"
                                 class="appearance-none w-full pl-3 pr-10 py-2.5 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md focus:ring-primary focus:border-primary outline-none text-gray-700 dark:text-gray-200 cursor-pointer">
                                 <option value="">Lọc theo địa chỉ</option>
                                 <?php
                             // Lấy danh sách địa chỉ duy nhất
                             $dia_chi_list = [];
                             foreach ($nha_cung_cap as $item) {
                                 if (!empty($item['dia_chi']) && !in_array($item['dia_chi'], $dia_chi_list)) {
                                     $dia_chi_list[] = $item['dia_chi'];
                                 }
                             }
                             sort($dia_chi_list);
                             foreach ($dia_chi_list as $dia_chi_item):
                             ?>
                                 <option value="<?php echo htmlspecialchars($dia_chi_item); ?>">
                                     <?php echo htmlspecialchars($dia_chi_item); ?></option>
                                 <?php endforeach; ?>
                             </select>
                         </div>
                     </div>
                     <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                         <button onclick="refreshPage()"
                             class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
                             title="Làm mới trang">
                             <span class="material-icons-round text-lg mr-2">refresh</span>
                             Làm mới
                         </button>
                         <?php if (canCreate('nhacungcap')): ?>
                         <button onclick="openExportModal()"
                             class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                             <span class="material-icons-round text-lg mr-2">add</span>
                             Thêm nhà cung cấp mới
                         </button>
                         <?php endif; ?>
                     </div>
                 </div>
                 <div
                     class="bg-surface-light dark:bg-surface-dark shadow overflow-hidden sm:rounded-lg border border-border-light dark:border-border-dark">
                     <div class="overflow-x-auto">
                         <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                             <thead class="bg-gray-50 dark:bg-gray-800">
                                 <tr>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[240px]"
                                         scope="col">
                                         Tên Nhà cung cấp
                                     </th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider min-w-[220px]"
                                         scope="col">
                                         Địa chỉ
                                     </th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap"
                                         scope="col">
                                         Số điện thoại
                                     </th>
                                     <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap"
                                         scope="col">
                                         Hợp đồng
                                     </th>
                                     <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider whitespace-nowrap"
                                         scope="col">
                                         Thao tác
                                     </th>
                                 </tr>
                             </thead>
                             <tbody class="bg-white dark:bg-surface-dark divide-y divide-gray-200 dark:divide-gray-700">
                                 <?php if (!empty($nha_cung_cap)): ?>
                                 <?php foreach ($nha_cung_cap as $item): ?>
                                 <?php 
                                 $first_char = strtoupper(substr($item['ten_ncc'], 0, 1));
                                 $colors = ['bg-blue-100', 'bg-orange-100', 'bg-green-100', 'bg-indigo-100', 'bg-red-100', 'bg-pink-100', 'bg-purple-100', 'bg-yellow-100'];
                                 $text_colors = ['text-blue-700', 'text-orange-700', 'text-green-700', 'text-indigo-700', 'text-red-700', 'text-pink-700', 'text-purple-700', 'text-yellow-700'];
                                 $dark_colors = ['dark:bg-blue-900', 'dark:bg-orange-900', 'dark:bg-green-900', 'dark:bg-indigo-900', 'dark:bg-red-900', 'dark:bg-pink-900', 'dark:bg-purple-900', 'dark:bg-yellow-900'];
                                 $dark_text_colors = ['dark:text-blue-400', 'dark:text-orange-400', 'dark:text-green-400', 'dark:text-indigo-400', 'dark:text-red-400', 'dark:text-pink-400', 'dark:text-purple-400', 'dark:text-yellow-400'];
                                 $index = (ord($first_char) - ord('A')) % 8;
                                 $bg_color = $colors[$index];
                                 $text_color = $text_colors[$index];
                                 $dark_bg_color = $dark_colors[$index];
                                 $dark_text_color = $dark_text_colors[$index];
                               ?>
                                 <tr class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors"
                                     data-ma-ncc="<?php echo htmlspecialchars($item['ma_ncc']); ?>"
                                     data-ten-ncc="<?php echo htmlspecialchars(strtolower($item['ten_ncc'])); ?>"
                                     data-sdt="<?php echo htmlspecialchars($item['sdt'] ?? ''); ?>"
                                     data-dia-chi="<?php echo htmlspecialchars($item['dia_chi'] ?? ''); ?>">
                                     <td class="px-6 py-4 whitespace-nowrap align-top">
                                         <div class="flex items-center">
                                             <div
                                                 class="h-9 w-9 rounded-full <?php echo $bg_color . ' ' . $dark_bg_color; ?> flex items-center justify-center <?php echo $text_color . ' ' . $dark_text_color; ?> font-bold text-sm mr-3 shrink-0">
                                                 <?php echo $first_char; ?>
                                             </div>
                                             <div>
                                                 <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                     <?php echo htmlspecialchars($item['ten_ncc']); ?></div>
                                                 <div class="text-xs text-gray-500 dark:text-gray-400">
                                                     #<?php echo htmlspecialchars($item['ma_ncc']); ?></div>
                                             </div>
                                         </div>
                                     </td>
                                     <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 align-top">
                                         <?php echo htmlspecialchars($item['dia_chi'] ?? 'N/A'); ?>
                                     </td>
                                     <td
                                         class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white align-top">
                                         <?php echo htmlspecialchars($item['sdt'] ?? 'N/A'); ?>
                                     </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-sm align-top">
                                         <div class="flex flex-col justify-center h-full pt-1.5">
                                             <span
                                                 class="text-sm text-gray-900 dark:text-gray-200 font-mono"><?php echo htmlspecialchars($item['hop_dong'] ?? 'N/A'); ?></span>
                                         </div>
                                     </td>
                                     <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium align-top">
                                         <?php if (canEdit('nhacungcap')): ?>
                                         <button
                                             onclick="openEditModal('<?php echo htmlspecialchars($item['ma_ncc'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['ten_ncc'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['sdt'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['dia_chi'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($item['hop_dong'] ?? '', ENT_QUOTES); ?>')"
                                             class="text-primary hover:text-primary-dark dark:text-blue-500 dark:hover:text-blue-400 p-2 rounded-full hover:bg-blue-50 dark:hover:bg-blue-900/20 mr-2 transition-colors"
                                             title="Chỉnh sửa">
                                             <span class="material-icons-round text-base">edit</span>
                                         </button>
                                         <?php endif; ?>
                                         <?php if (canDelete('nhacungcap')): ?>
                                         <form method="POST" action="delete_ncc.php" style="display:inline;"
                                             onsubmit="return confirm('Bạn có chắc chắn muốn xóa nhà cung cấp này không?');">
                                             <input type="hidden" name="action" value="delete_supplier">
                                             <input type="hidden" name="ma_ncc" value="<?php echo $item['ma_ncc']; ?>">
                                             <button type="submit"
                                                 class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors"
                                                 title="Xóa">
                                                 <span class="material-icons-round text-base">delete</span>
                                             </button>
                                         </form>
                                         <?php endif; ?>

                                     </td>
                                 </tr>
                                 <?php endforeach; ?>
                                 <?php else: ?>
                                 <tr class="no-data">
                                     <td colspan="5"
                                         class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                         Không có dữ liệu nhà cung cấp
                                     </td>
                                 </tr>
                                 <?php endif; ?>
                             </tbody>
                         </table>
                     </div>
                     <div
                         class="bg-white dark:bg-surface-dark px-4 py-3 border-t border-border-light dark:border-border-dark flex items-center justify-between sm:px-6">
                         <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                             <div>
                                 <p class="text-sm text-gray-700 dark:text-gray-400" id="resultCount">
                                     Hiển thị <span class="font-medium" id="startCount">1</span> đến <span
                                         class="font-medium"
                                         id="endCount"><?php echo min(10, count($nha_cung_cap)); ?></span>
                                     của <span class="font-medium"
                                         id="totalCount"><?php echo count($nha_cung_cap); ?></span> nhà cung cấp
                                 </p>
                             </div>
                             <div>
                                 <nav aria-label="Pagination"
                                     class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                     <a class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700"
                                         href="#">
                                         <span class="sr-only">Trước</span>
                                         <span class="material-icons-round text-lg">chevron_left</span>
                                     </a>
                                     <a aria-current="page"
                                         class="z-10 bg-primary/10 dark:bg-primary/20 border-primary text-primary relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                         href="#">
                                         1
                                     </a>
                                     <a class="bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700 relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                                         href="#">
                                         2
                                     </a>
                                     <a class="bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700 hidden md:inline-flex relative items-center px-4 py-2 border text-sm font-medium"
                                         href="#">
                                         3
                                     </a>
                                     <span
                                         class="relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-700 dark:text-gray-400">
                                         ...
                                     </span>
                                     <a class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-700"
                                         href="#">
                                         <span class="sr-only">Sau</span>
                                         <span class="material-icons-round text-lg">chevron_right</span>
                                     </a>
                                 </nav>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>
         </main>
         <div id="exportModal"
             class="fixed hidden inset-0 bg-slate-900/60 backdrop-blur-[2px] z-50 flex items-center justify-center p-4">
             <div
                 class="bg-white dark:bg-slate-900 w-full max-w-lg rounded-xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden">
                 <div
                     class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                     <div>
                         <h2 class="text-xl font-bold text-slate-900 dark:text-white">Thêm nhà cung cấp mới</h2>
                     </div>
                     <button onclick="closeExportModal()"
                         class="text-slate-400 hover:text-slate-500 dark:hover:text-slate-300 transition-colors p-1 rounded-md hover:bg-slate-100 dark:hover:bg-slate-800">
                         <span class="material-symbols-outlined text-3xl">x</span>
                     </button>
                 </div>
                 <form class="p-6 space-y-4" method="POST" action="add_ncc.php">
                     <input type="hidden" name="action" value="add_supplier">
                     <div class="space-y-1.5">
                         <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="supplier-name">
                             Tên nhà cung cấp <span class="text-red-500">*</span>
                         </label>
                         <input
                             class="w-full px-4 py-2 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary"
                             id="supplier-name" name="ten_ncc" placeholder="Tên đơn vị cung cấp..." type="text"
                             required />
                     </div>
                     <div class="space-y-1.5">
                         <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="phone">
                             Số điện thoại <span class="text-red-500">*</span>
                         </label>
                         <input
                             class="w-full px-4 py-2 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary"
                             id="phone" name="sdt" placeholder="0912345678" type="tel" pattern="\d{10}"
                             inputmode="numeric" required />
                         <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Phải nhập 10 chữ số</p>
                     </div>
                     <div class="space-y-1.5">
                         <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="address">
                             Địa chỉ <span class="text-red-500">*</span>
                         </label>
                         <input
                             class="w-full px-4 py-2 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary"
                             id="address" name="dia_chi" placeholder="Địa chỉ trụ sở..." type="text" required />
                     </div>
                     <div class="space-y-1.5">
                         <label class="text-sm font-semibold text-slate-700 dark:text-slate-300" for="contract">
                             Hợp đồng
                         </label>
                         <input
                             class="w-full mt-2 px-4 py-2 rounded-lg border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm focus:ring-2 focus:ring-primary/20 focus:border-primary"
                             id="contract" name="hop_dong" placeholder="(Ví dụ: HD-2000/01)" type="text" />
                     </div>
                     <div class="flex items-center justify-end gap-3 pt-4">
                         <button onclick="closeExportModal()"
                             class="px-5 py-2 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all text-sm"
                             type="button">
                             Hủy
                         </button>
                         <button
                             class="px-8 py-2 rounded-lg bg-primary text-white font-semibold shadow-sm shadow-primary/20 hover:bg-blue-700 focus:ring-2 focus:ring-primary/40 transition-all flex items-center gap-2 text-sm"
                             type="submit">
                             Lưu
                         </button>
                     </div>
                 </form>
             </div>
         </div>

         <!-- Edit Supplier Modal -->
         <div aria-labelledby="modal-title" aria-modal="true" class="relative z-50 hidden" id="editModal" role="dialog">
             <div class="fixed inset-0 bg-gray-500/75 dark:bg-gray-900/75 transition-opacity"></div>
             <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
                 <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                     <div
                         class="relative transform overflow-hidden rounded-lg bg-white dark:bg-surface-dark text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-border-light dark:border-border-dark">
                         <div class="absolute right-0 top-0 pr-4 pt-4">
                             <button onclick="closeEditModal()"
                                 class="rounded-md bg-white dark:bg-surface-dark text-gray-400 hover:text-gray-500 focus:outline-none transition-colors"
                                 type="button">
                                 <span class="sr-only">Đóng</span>
                                 <span class="material-icons-round">close</span>
                             </button>
                         </div>
                         <div class="bg-white dark:bg-surface-dark px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                             <div class="sm:flex sm:items-start">
                                 <div class="mt-3 text-center sm:mt-0 sm:text-left w-full">
                                     <h3 class="text-xl font-bold leading-6 text-gray-900 dark:text-white mb-6"
                                         id="modal-title">Chỉnh sửa Nhà cung cấp</h3>
                                     <form method="POST" action="update_ncc.php" id="editForm" class="mt-4 space-y-4">
                                         <input type="hidden" name="action" value="edit_supplier">
                                         <input type="hidden" name="ma_ncc" id="ncc-id-hidden">
                                         <div class="grid grid-cols-1 gap-4">
                                             <div>
                                                 <label
                                                     class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                                     for="ncc-code">Mã Nhà cung cấp</label>
                                                 <input
                                                     class="block w-full rounded-md border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400 sm:text-sm py-2.5 cursor-not-allowed shadow-sm"
                                                     id="ncc-code" readonly="" type="text" />
                                             </div>
                                             <div>
                                                 <label
                                                     class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                                     for="ncc-name">Tên Nhà cung cấp</label>
                                                 <input
                                                     class="focus:ring-primary focus:border-primary block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 sm:text-sm py-2.5 shadow-sm"
                                                     id="ncc-name" name="ten_ncc" type="text" required />
                                             </div>
                                             <div>
                                                 <label
                                                     class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                                     for="ncc-address">Địa chỉ</label>
                                                 <input
                                                     class="focus:ring-primary focus:border-primary block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 sm:text-sm py-2.5 shadow-sm"
                                                     id="ncc-address" name="dia_chi" type="text" required />
                                             </div>
                                             <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                 <div>
                                                     <label
                                                         class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                                         for="ncc-phone">Số điện thoại</label>
                                                     <input
                                                         class="focus:ring-primary focus:border-primary block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 sm:text-sm py-2.5 shadow-sm"
                                                         id="ncc-phone" name="sdt" type="text" pattern="\d{10}"
                                                         required />
                                                 </div>
                                                 <div>
                                                     <label
                                                         class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1"
                                                         for="ncc-contract">Mã Hợp đồng</label>
                                                     <input
                                                         class="focus:ring-primary focus:border-primary block w-full rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-gray-100 sm:text-sm py-2.5 shadow-sm"
                                                         id="ncc-contract" name="hop_dong" type="text" />
                                                 </div>
                                             </div>
                                         </div>
                                     </form>
                                 </div>
                             </div>
                         </div>
                         <div
                             class="bg-gray-50 dark:bg-gray-800/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6 gap-3">
                             <button onclick="document.getElementById('editForm').submit()"
                                 class="inline-flex w-full justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-sm hover:bg-primary-dark sm:w-auto transition-colors"
                                 type="button">
                                 Cập nhật
                             </button>
                             <button onclick="closeEditModal()"
                                 class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-surface-dark px-4 py-2.5 text-sm font-semibold text-gray-700 dark:text-gray-200 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto transition-colors"
                                 type="button">
                                 Hủy
                             </button>
                         </div>
                     </div>
                 </div>
             </div>
         </div>

         <script>
         function refreshPage() {
             window.location.reload();
         }

         function openExportModal() {
             const modal = document.getElementById("exportModal");
             modal.classList.remove("hidden");
         }

         const form = document.querySelector('form');
         if (form) {
             form.onsubmit = function() {
                 setTimeout(() => {
                     closeExportModal();
                     form.reset();
                 }, 100);
             };
         }

         document.getElementById("exportModal")?.addEventListener("click", function(e) {
             if (e.target === this) closeExportModal();
         });

         document.addEventListener("keydown", function(e) {
             if (e.key === "Escape") closeExportModal();
         });

         function closeExportModal() {
             document.getElementById("exportModal").classList.add("hidden");
         }

         function openEditModal(ma_ncc, ten_ncc, sdt, dia_chi, hop_dong) {
             document.getElementById("ncc-id-hidden").value = ma_ncc;
             document.getElementById("ncc-code").value = ma_ncc;
             document.getElementById("ncc-name").value = ten_ncc;
             document.getElementById("ncc-phone").value = sdt;
             document.getElementById("ncc-address").value = dia_chi;
             document.getElementById("ncc-contract").value = hop_dong || '';
             document.getElementById("editModal").classList.remove("hidden");
         }

         function closeEditModal() {
             document.getElementById("editModal").classList.add("hidden");
             const editForm = document.getElementById("editForm");
             if (editForm) editForm.reset();
         }

         // Close modal when clicking on backdrop
         const editModal = document.getElementById("editModal");
         if (editModal) {
             editModal.addEventListener("click", function(e) {
                 // Check if click is on the backdrop (the fixed inset-0 div)
                 const backdrop = editModal.querySelector('.fixed.inset-0');
                 if (backdrop && (e.target === backdrop || e.target === editModal)) {
                     closeEditModal();
                 }
             });
         }

         // Close modal on Escape key
         document.addEventListener("keydown", function(e) {
             if (e.key === "Escape") {
                 closeExportModal();
                 closeEditModal();
             }
         });

         // Close modal after form submission
         const editForm = document.getElementById("editForm");
         if (editForm) {
             editForm.onsubmit = function() {
                 setTimeout(() => {
                     closeEditModal();
                 }, 100);
             };
         }

         // Search and Filter functionality
         const searchInput = document.getElementById("search");
         const filterAddress = document.getElementById("filterAddress");
         const tableRows = document.querySelectorAll('tbody tr[data-ma-ncc]');
         const totalCount = tableRows.length;

         function filterTable() {
             const searchTerm = searchInput.value.toLowerCase().trim();
             const selectedAddress = filterAddress.value;

             let visibleCount = 0;
             let firstVisible = null;

             // Hide "no data" row if it exists
             const noDataRow = document.querySelector('tr.no-data');
             if (noDataRow) {
                 noDataRow.style.display = 'none';
             }

             tableRows.forEach((row, index) => {
                 // Skip if row doesn't have data attributes (like no-data row)
                 if (!row.hasAttribute('data-ma-ncc')) {
                     return;
                 }

                 const maNcc = row.getAttribute('data-ma-ncc').toLowerCase();
                 const tenNcc = row.getAttribute('data-ten-ncc');
                 const sdt = row.getAttribute('data-sdt');
                 const diaChi = row.getAttribute('data-dia-chi');

                 // Check search match
                 const matchesSearch = !searchTerm ||
                     maNcc.includes(searchTerm) ||
                     tenNcc.includes(searchTerm) ||
                     sdt.includes(searchTerm);

                 // Check address filter
                 const matchesAddress = !selectedAddress || diaChi === selectedAddress;

                 if (matchesSearch && matchesAddress) {
                     row.style.display = '';
                     if (firstVisible === null) firstVisible = index + 1;
                     visibleCount++;
                 } else {
                     row.style.display = 'none';
                 }
             });

             // Update count display
             const startCount = firstVisible || 0;
             const endCount = visibleCount > 0 ? Math.min(startCount + 4, startCount + visibleCount - 1) : 0;

             document.getElementById('startCount').textContent = startCount || 0;
             document.getElementById('endCount').textContent = endCount || 0;
             document.getElementById('totalCount').textContent = visibleCount;

             // Show "no results" message if needed
             const tbody = document.querySelector('tbody');
             let noResultsRow = tbody.querySelector('tr.no-results');
             if (visibleCount === 0 && tableRows.length > 0 && !noResultsRow) {
                 noResultsRow = document.createElement('tr');
                 noResultsRow.className = 'no-results';
                 noResultsRow.innerHTML =
                     '<td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Không tìm thấy kết quả phù hợp</td>';
                 tbody.appendChild(noResultsRow);
             } else if (visibleCount > 0 && noResultsRow) {
                 noResultsRow.remove();
             }
         }

         // Add event listeners
         searchInput.addEventListener('input', filterTable);
         filterAddress.addEventListener('change', filterTable);

         // Initialize filter on page load
         filterTable();
         </script>

     </body>

     </html>