<?php
include '../include/connect.php';
include '../include/permissions.php';
checkAccess('daily');

// Khởi tạo biến thông báo
$message = '';
$message_type = ''; // 'success' hoặc 'error'

// Lấy thông báo từ URL parameter (từ redirect)
if (isset($_GET['status']) && isset($_GET['message'])) {
    $message_type = $_GET['status'];
    $message = urldecode($_GET['message']);
}

// Lấy tất cả dữ liệu đại lý (không lọc server-side)
try {
    $stmt = $pdo->prepare('SELECT * FROM dai_ly ORDER BY ma_dai_ly');
    $stmt->execute();
    $dai_ly = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error_message = 'Lỗi truy vấn: ' . $e->getMessage();
    $dai_ly = [];
}

// Lấy danh sách địa chỉ duy nhất cho dropdown lọc
$dia_chi_list = [];
foreach ($dai_ly as $item) {
    if (!empty($item['dia_chi']) && !in_array($item['dia_chi'], $dia_chi_list)) {
        $dia_chi_list[] = $item['dia_chi'];
    }
}
?>

<!DOCTYPE html>
 <html lang="vi">

 <head>
     <meta charset="utf-8" />
     <meta content="width=device-width, initial-scale=1.0" name="viewport" />
     <title>Quản lý Đại lý - Agent Management System</title>
     <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
     <link href="https://fonts.googleapis.com" rel="preconnect" />
     <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect" />
     <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap"
         rel="stylesheet" />
     <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@100..700&display=swap"
        rel="stylesheet" />
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
    .material-symbols-outlined {
      font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
    }

    .material-symbols-outlined.filled {
      font-variation-settings: 'FILL' 1, 'wght' 400, 'GRAD' 0, 'opsz' 24;
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
                         đại lý</h1>
                 </div>
             </div>
             <div class="flex flex-col md:flex-row justify-between items-center gap-4 mb-6">
                 <div class="flex flex-col sm:flex-row gap-3 w-full md:w-auto">
                    <div class="relative w-full sm:w-80">
                        <span class="material-symbols-outlined absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 text-sm">search</span>
                        <input id="searchInput" class="pl-9 pr-4 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-1 focus:ring-primary outline-none text-gray-700 dark:text-gray-200 w-full placeholder-gray-400" placeholder="Tìm theo tên, mã, SĐT..." type="text" />
                         </div>
                    <div class="relative w-full sm:w-48">
                        <select id="filterAddress" class="appearance-none w-full pl-3 pr-10 py-2 text-sm bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-1 focus:ring-primary outline-none text-gray-700 dark:text-gray-200 cursor-pointer">
                            <option value="">Tất cả địa chỉ</option>
                            <?php foreach ($dia_chi_list as $dia_chi_item): ?>
                             <option value="<?php echo htmlspecialchars($dia_chi_item); ?>">
                                 <?php echo htmlspecialchars($dia_chi_item); ?>
                             </option>
                             <?php endforeach; ?>
                         </select>
                         </div>
                     </div>
                <div class="flex gap-3">
                    <button id="refreshBtn" onclick="refreshPage()"
                        class="inline-flex items-center justify-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-lg text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors"
                        title="Làm mới">
                        <span class="material-symbols-outlined text-lg mr-1">refresh</span>
                        Làm mới
                    </button>
                 <?php if (canCreate('daily')): ?>
                 <button id="openModal"
                     class="w-full md:w-auto inline-flex items-center justify-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-primary hover:bg-primary-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-colors">
                     <span class="material-icons-round text-lg mr-2">add</span>
                     Thêm Đại lý Mới
                 </button>
                 <?php endif; ?>
             </div>
            </div>

            <div class="bg-surface-light dark:bg-surface-dark shadow-sm rounded-xl border border-border-light dark:border-border-dark overflow-hidden transition-colors duration-200">
                 <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-border-light dark:divide-border-dark">
                         <thead class="bg-gray-50 dark:bg-gray-800">
                             <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Mã Đại lý</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tên Đại lý</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Địa chỉ</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">SĐT</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Thao tác</th>
                             </tr>
                         </thead>
                        <tbody id="agentTableBody" class="bg-surface-light dark:bg-surface-dark divide-y divide-border-light dark:divide-border-dark">
                             <?php if (!empty($dai_ly)): ?>
                             <?php foreach ($dai_ly as $item): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors" data-address="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $item['dia_chi']))); ?>" data-original-address="<?php echo htmlspecialchars($item['dia_chi']); ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    <?php echo htmlspecialchars($item['ma_dai_ly']); ?>
                                 </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                    <?php echo htmlspecialchars($item['ten_dai_ly']); ?>
                                 </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($item['dia_chi']); ?>
                                 </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?php echo htmlspecialchars($item['sdt']); ?>
                                 </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                     <div class="flex items-center justify-end space-x-2">
                                         <?php if (canEdit('daily')): ?>
                                         <button
                                             class="text-primary hover:text-primary-dark p-1 rounded-full hover:bg-blue-50 dark:hover:bg-blue-900/30 transition-colors edit-agent"
                                            title="Chỉnh sửa" data-agent='<?php echo json_encode($item); ?>'>
                                             <span class="material-icons-round text-lg">edit</span>
                                         </button>
                                         <?php endif; ?>
                                        <?php if (canDelete('daily')): ?>
                                        <form method="POST" action="delete_daily.php" style="display:inline;" onsubmit="return confirm('Bạn có chắc chắn muốn xóa đại lý này không?');">
                                             <input type="hidden" name="action" value="delete_agent">
                                            <input type="hidden" name="ma_dai_ly" value="<?php echo htmlspecialchars($item['ma_dai_ly']); ?>">
                                            <button type="submit" class="text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors" title="Xóa">
                                                 <span class="material-icons-round text-base">delete</span>
                                             </button>
                                         </form>
                                         <?php endif; ?>
                                     </div>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                             <?php else: ?>
                             <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Không có đại lý nào.
                                 </td>
                             </tr>
                             <?php endif; ?>
                         </tbody>
                     </table>
                 </div>
                <div id="noResultsMessage" class="hidden px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                    Không tìm thấy đại lý nào phù hợp với tiêu chí tìm kiếm.
                         </div>
                     </div>
                 </div>
    </main>

    <!-- Modal Thêm/Chỉnh sửa Đại lý -->
    <div id="addModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden" id="my-modal">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="flex justify-between items-center mb-4">
                <h3 id="modalTitle" class="text-lg font-medium text-gray-900 dark:text-gray-100">Thêm Đại lý Mới</h3>
                <button id="closeModal" class="text-gray-400 hover:text-gray-600">
                                 <span class="material-icons-round">close</span>
                             </button>
                         </div>
            <form method="POST" action="add_daily.php" id="addAgentForm">
                             <input type="hidden" name="action" value="add_agent">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Tên Đại lý *</label>
                        <input type="text" name="ten_dai_ly" id="ten_dai_ly" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <p id="ten_dai_ly_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập tên đại lý</p>
                                         </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Địa chỉ *</label>
                        <input type="text" name="dia_chi" id="dia_chi" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <p id="dia_chi_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Vui lòng nhập địa chỉ</p>
                                     </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Số điện thoại *</label>
                        <input type="text" name="sdt" id="sdt" required class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <p id="sdt_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Số điện thoại phải có đúng 10 chữ số</p>
                                         </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">CCCD/CMND</label>
                        <input type="text" name="cccd" id="cccd" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <p id="cccd_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">CCCD/CMND phải có đúng 12 chữ số (nếu có nhập)</p>
                                     </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Người đại diện</label>
                        <input type="text" name="nguoi_dai_dien" id="nguoi_dai_dien" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                         </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300" title="Định dạng: DL_XX_XX (ví dụ: DL_AB_12)">Số hợp đồng</label>
                        <input type="text" name="so_hop_dong" id="so_hop_dong" placeholder="DL_AB_12" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                        <p id="so_hop_dong_error" class="mt-1 text-sm text-red-600 dark:text-red-400 hidden">Hợp đồng phải có định dạng DL_XX_XX (ví dụ: DL_AB_12)</p>
                                     </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Ngày ký</label>
                        <input type="date" name="ngay_ky" class="mt-1 block w-full border border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-2 px-3 bg-white dark:bg-gray-700 text-gray-900 dark:text-gray-100">
                                         </div>
                                     </div>
                <div class="flex justify-end mt-6 gap-3">
                    <button type="button" id="cancelModal" class="bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-300 font-bold py-2 px-4 rounded">
                        Hủy
                                 </button>
                    <button type="submit" id="submitBtn" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Thêm Đại lý
                                 </button>
                             </div>
                         </form>
                     </div>
                 </div>

 <script>
        // Modal functionality
        const modal = document.getElementById('addModal');
        const openModalBtn = document.getElementById('openModal');
        const closeModalBtn = document.getElementById('closeModal');
        const cancelModalBtn = document.getElementById('cancelModal');

        openModalBtn.onclick = function() {
            // Ensure form is in add mode
            resetFormToAddMode();
            modal.classList.remove('hidden');
        }

        closeModalBtn.onclick = function() {
            modal.classList.add('hidden');
        }

        cancelModalBtn.onclick = function() {
            modal.classList.add('hidden');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.classList.add('hidden');
            }
        }

        // Edit functionality - using event delegation for better reliability
        const modalTitle = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('submitBtn');
        const agentTableBody = document.getElementById('agentTableBody');

        // Use event delegation to handle edit button clicks
        if (agentTableBody) {
            agentTableBody.addEventListener('click', function(e) {
            const editButton = e.target.closest('.edit-agent');
            if (!editButton) return;

            e.preventDefault();
            e.stopPropagation();

            try {
                const agentData = JSON.parse(editButton.dataset.agent);

                // Change modal title
                modalTitle.textContent = 'Chỉnh sửa Đại lý';

                // Change submit button text
                submitBtn.textContent = 'Cập nhật Đại lý';

                // Change form action to update
                document.querySelector('#addAgentForm').action = 'update_daily.php';

                // Change hidden action value
                document.querySelector('#addAgentForm input[name="action"]').value = 'edit_agent';

                // Remove existing edit-id if any
                const existingEditId = document.getElementById('edit-id');
                if (existingEditId) {
                    existingEditId.remove();
                }

                // Add hidden input for ma_dai_ly
                document.querySelector('#addAgentForm').insertAdjacentHTML('beforeend',
                    `<input type='hidden' name='ma_dai_ly' value='${agentData.ma_dai_ly}' id='edit-id'>`);

                // Fill form fields
                document.querySelector('input[name="ten_dai_ly"]').value = agentData.ten_dai_ly || '';
                document.querySelector('input[name="dia_chi"]').value = agentData.dia_chi || '';
                document.querySelector('input[name="sdt"]').value = agentData.sdt || '';
                document.querySelector('input[name="cccd"]').value = agentData.cccd || '';
                document.querySelector('input[name="nguoi_dai_dien"]').value = agentData.nguoi_dai_dien || '';
                document.querySelector('input[name="so_hop_dong"]').value = agentData.so_hop_dong || '';
                document.querySelector('input[name="ngay_ky"]').value = agentData.ngay_ky || '';

                // Open the modal
    modal.classList.remove('hidden');
            } catch (error) {
                console.error('Error parsing agent data:', error);
                alert('Có lỗi xảy ra khi tải dữ liệu đại lý. Vui lòng thử lại.');
            }
        });
        }

        // Reset form when closing modal
        function resetFormToAddMode() {
            const form = document.querySelector('#addAgentForm');
            const editIdInput = document.getElementById('edit-id');

            // Reset modal title
            modalTitle.textContent = 'Thêm Đại lý Mới';

            // Reset submit button text
            submitBtn.textContent = 'Thêm Đại lý';

            // Reset form action
            form.action = 'add_daily.php';

            // Reset hidden action value
            form.querySelector('input[name="action"]').value = 'add_agent';

            // Remove edit-id input if exists
            if (editIdInput) {
                editIdInput.remove();
            }

            // Reset form
            form.reset();
            
            // Reset all validation errors
            hideError('ten_dai_ly', 'ten_dai_ly_error');
            hideError('dia_chi', 'dia_chi_error');
            hideError('sdt', 'sdt_error');
            hideError('cccd', 'cccd_error');
            hideError('so_hop_dong', 'so_hop_dong_error');
        }

        closeModalBtn.onclick = function() {
            modal.classList.add('hidden');
            resetFormToAddMode();
        }

        cancelModalBtn.onclick = function() {
            modal.classList.add('hidden');
            resetFormToAddMode();
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
    modal.classList.add('hidden');
                resetFormToAddMode();
            }
        }

        // Validation functions for all fields
        function showError(inputId, errorId) {
            const input = document.getElementById(inputId);
            const error = document.getElementById(errorId);
            if (input && error) {
                input.classList.add('border-red-500', 'dark:border-red-500');
                input.classList.remove('border-gray-300', 'dark:border-gray-600');
                error.classList.remove('hidden');
            }
        }

        function hideError(inputId, errorId) {
            const input = document.getElementById(inputId);
            const error = document.getElementById(errorId);
            if (input && error) {
                input.classList.remove('border-red-500', 'dark:border-red-500');
                input.classList.add('border-gray-300', 'dark:border-gray-600');
                error.classList.add('hidden');
            }
        }

        // Validation rules
        function validateTenDaiLy(value) {
            return value.trim() !== '';
        }

        function validateDiaChi(value) {
            return value.trim() !== '';
        }

        function validateSDT(value) {
            return /^\d{10}$/.test(value.trim());
        }

        function validateCCCD(value) {
            if (!value || value.trim() === '') return true; // Không bắt buộc
            return /^\d{12}$/.test(value.trim());
        }

        function validateSoHopDong(value) {
            if (!value || value.trim() === '') return true; // Không bắt buộc
            return /^DL_[a-zA-Z]{2}_[0-9]{2}$/.test(value.trim());
        }

        // Setup validation for each field
        const tenDaiLyInput = document.getElementById('ten_dai_ly');
        const diaChiInput = document.getElementById('dia_chi');
        const sdtInput = document.getElementById('sdt');
        const cccdInput = document.getElementById('cccd');
        const soHopDongInput = document.getElementById('so_hop_dong');

        // Tên Đại lý validation
        if (tenDaiLyInput) {
            tenDaiLyInput.addEventListener('blur', function() {
                if (!validateTenDaiLy(this.value)) {
                    showError('ten_dai_ly', 'ten_dai_ly_error');
                } else {
                    hideError('ten_dai_ly', 'ten_dai_ly_error');
                }
            });
            tenDaiLyInput.addEventListener('input', function() {
                if (validateTenDaiLy(this.value)) {
                    hideError('ten_dai_ly', 'ten_dai_ly_error');
                }
            });
        }

        // Địa chỉ validation
        if (diaChiInput) {
            diaChiInput.addEventListener('blur', function() {
                if (!validateDiaChi(this.value)) {
                    showError('dia_chi', 'dia_chi_error');
                } else {
                    hideError('dia_chi', 'dia_chi_error');
                }
            });
            diaChiInput.addEventListener('input', function() {
                if (validateDiaChi(this.value)) {
                    hideError('dia_chi', 'dia_chi_error');
                }
            });
        }

        // Số điện thoại validation
        if (sdtInput) {
            sdtInput.addEventListener('blur', function() {
                if (!validateSDT(this.value)) {
                    showError('sdt', 'sdt_error');
                } else {
                    hideError('sdt', 'sdt_error');
                }
            });
            sdtInput.addEventListener('input', function() {
                if (validateSDT(this.value)) {
                    hideError('sdt', 'sdt_error');
                }
            });
        }

        // CCCD validation
        if (cccdInput) {
            cccdInput.addEventListener('blur', function() {
                if (!validateCCCD(this.value)) {
                    showError('cccd', 'cccd_error');
                } else {
                    hideError('cccd', 'cccd_error');
                }
            });
            cccdInput.addEventListener('input', function() {
                if (validateCCCD(this.value)) {
                    hideError('cccd', 'cccd_error');
                }
            });
        }

        // Số hợp đồng validation
        if (soHopDongInput) {
            soHopDongInput.addEventListener('blur', function() {
                if (!validateSoHopDong(this.value)) {
                    showError('so_hop_dong', 'so_hop_dong_error');
                } else {
                    hideError('so_hop_dong', 'so_hop_dong_error');
                }
            });
            soHopDongInput.addEventListener('input', function() {
                if (validateSoHopDong(this.value)) {
                    hideError('so_hop_dong', 'so_hop_dong_error');
                }
            });
        }

        // Validate khi submit form
        document.querySelector('#addAgentForm').addEventListener('submit', function(e) {
            let hasError = false;
            let firstErrorField = null;

            // Validate Tên Đại lý
            if (!validateTenDaiLy(tenDaiLyInput.value)) {
                showError('ten_dai_ly', 'ten_dai_ly_error');
                if (!firstErrorField) firstErrorField = tenDaiLyInput;
                hasError = true;
            }

            // Validate Địa chỉ
            if (!validateDiaChi(diaChiInput.value)) {
                showError('dia_chi', 'dia_chi_error');
                if (!firstErrorField) firstErrorField = diaChiInput;
                hasError = true;
            }

            // Validate Số điện thoại
            if (!validateSDT(sdtInput.value)) {
                showError('sdt', 'sdt_error');
                if (!firstErrorField) firstErrorField = sdtInput;
                hasError = true;
            }

            // Validate CCCD
            if (!validateCCCD(cccdInput.value)) {
                showError('cccd', 'cccd_error');
                if (!firstErrorField) firstErrorField = cccdInput;
                hasError = true;
            }

            // Validate Số hợp đồng
            if (!validateSoHopDong(soHopDongInput.value)) {
                showError('so_hop_dong', 'so_hop_dong_error');
                if (!firstErrorField) firstErrorField = soHopDongInput;
                hasError = true;
            }

            if (hasError) {
                e.preventDefault();
                if (firstErrorField) {
                    firstErrorField.focus();
                    firstErrorField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                return false;
            }
            
            // Nếu validation thành công, reset form sau khi submit
            setTimeout(() => {
                modal.classList.add('hidden');
                resetFormToAddMode();
            }, 100);
        });

        // Search and Filter functionality
        const searchInput = document.getElementById('searchInput');
        const filterAddress = document.getElementById('filterAddress');
        // agentTableBody đã được khai báo ở trên (dòng 319)
        const allRows = agentTableBody ? Array.from(agentTableBody.querySelectorAll('tr')) : [];

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedAddress = filterAddress.value;

            let visibleCount = 0;

            allRows.forEach(row => {
                const originalAddress = row.getAttribute('data-original-address');
                const cells = row.querySelectorAll('td');
                const maDaiLy = cells[0].textContent.toLowerCase();
                const tenDaiLy = cells[1].textContent.toLowerCase();
                const diaChi = cells[2].textContent.toLowerCase();
                const sdt = cells[3].textContent.toLowerCase();

                const matchesSearch = !searchTerm ||
                    maDaiLy.includes(searchTerm) ||
                    tenDaiLy.includes(searchTerm) ||
                    diaChi.includes(searchTerm) ||
                    sdt.includes(searchTerm);
                const matchesAddress = !selectedAddress || originalAddress === selectedAddress;

                if (matchesSearch && matchesAddress) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            // Show/hide no results message
            const noResultsMessage = document.getElementById('noResultsMessage');
            if (visibleCount === 0) {
                noResultsMessage.classList.remove('hidden');
            } else {
                noResultsMessage.classList.add('hidden');
            }
        }

        function refreshPage() {
            searchInput.value = '';
            filterAddress.value = '';
            filterTable();
            // Hide no results message when refreshing
            document.getElementById('noResultsMessage').classList.add('hidden');
        }

        searchInput.addEventListener('input', filterTable);
        filterAddress.addEventListener('change', filterTable);
 </script>
</body>
 </html>