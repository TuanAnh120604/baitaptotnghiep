    // Xử lý lọc và tìm kiếm - không reload trang
    const searchInput = document.getElementById('liveSearch');
    const filterSelect = document.getElementById('liveFilterType');
    const resetBtn = document.getElementById('resetBtn');
    const tableBody = document.querySelector('tbody');
    const paginationControls = document.getElementById('paginationInfo');
    let filteredData = [];
    let currentPageNum = 1;
    let searchTimeout;

    // Kiểm tra quyền từ PHP
    // const canEditWarehouse = <?php echo canEdit('danhsachkho') ? 'true' : 'false'; ?>;
    // const canDeleteWarehouse = <?php echo canDelete('danhsachkho') ? 'true' : 'false'; ?>;

    // Initialize data and UI
    const urlParams = new URLSearchParams(window.location.search);
    const initialPage = parseInt(urlParams.get('page')) || 1;
    const initialSearch = urlParams.get('search') || '';
    const initialFilter = urlParams.get('filter_loai_kho') || '';

    if (searchInput) searchInput.value = initialSearch;
    if (filterSelect) filterSelect.value = initialFilter;

    filteredData = [...allWarehouseData];
    currentPageNum = initialPage;
    renderTable();

    // Hàm lọc và phân trang bảng
    function filterAndRenderTable() {
        const searchText = searchInput ? searchInput.value.trim().toLowerCase() : '';
        const filterValue = filterSelect ? filterSelect.value : '';

        // Lọc dữ liệu
        filteredData = allWarehouseData.filter(warehouse => {
            // Lọc theo loại kho
            if (filterValue && warehouse.ma_loai_kho !== filterValue) {
                return false;
            }

            // Lọc theo tìm kiếm
            if (searchText) {
                const maMatch = (warehouse.ma_kho || '').toLowerCase().includes(searchText);
                const tenMatch = (warehouse.ten_kho || '').toLowerCase().includes(searchText);
                if (!maMatch && !tenMatch) {
                    return false;
                }
            }

            return true;
        });

        renderTable();
    }

    // Hàm escape HTML để tránh XSS
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Hàm render bảng dựa trên trang hiện tại
    function renderTable() {
        const recordsPerPage = 10;
        const totalPages = Math.ceil(filteredData.length / recordsPerPage);
        const offset = (currentPageNum - 1) * recordsPerPage;
        const pageData = filteredData.slice(offset, offset + recordsPerPage);

        // Render các hàng dữ liệu
        if (pageData.length === 0) {
            tableBody.innerHTML = '<tr><td colspan="7" class="px-6 py-8 text-center text-slate-500 dark:text-slate-400">Không có dữ liệu kho</td></tr>';
        } else {
            tableBody.innerHTML = pageData.map(warehouse => {
                const maKho = escapeHtml(warehouse.ma_kho || '');
                const tenKho = escapeHtml(warehouse.ten_kho || '');
                const diaChi = escapeHtml(warehouse.dia_chi || '');
                const tenVung = escapeHtml(warehouse.ten_vung || '');
                const tenNd = escapeHtml(warehouse.ten_nd || '');
                const tenLoaiKho = escapeHtml(warehouse.ten_loai_kho || 'Chưa xác định');
                const tenKhoEscaped = tenKho.replace(/'/g, "\\'");

                let actionButtons = '';
                if (canEditWarehouse) {
                    actionButtons += `<button onclick="openEditModal('${maKho}', '${tenKhoEscaped}', '${diaChi}', '${warehouse.ma_vung || ''}', '${warehouse.ma_nd || ''}', '${warehouse.ma_loai_kho || ''}')" class="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded transition-colors" title="Sửa"><span class="material-symbols-outlined text-lg">edit</span></button>`;
                }
                if (canDeleteWarehouse) {
                    actionButtons += `<button onclick="confirmDelete('${maKho}')" class="p-1.5 text-slate-500 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded transition-colors" title="Xóa"><span class="material-symbols-outlined text-lg">delete</span></button>`;
                }

                return `
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors group">
                        <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300">${maKho}</td>
                        <td class="px-6 py-4 font-medium text-slate-800 dark:text-white truncate" title="${tenKho}">${tenKho}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400 truncate" title="${diaChi}">${diaChi}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">${tenVung}</td>
                        <td class="px-6 py-4 text-slate-600 dark:text-slate-400">${tenNd}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">${tenLoaiKho}</span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <div class="flex items-center justify-end space-x-2">${actionButtons}</div>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // Cập nhật pagination info
        const startRecord = filteredData.length > 0 ? (currentPageNum - 1) * recordsPerPage + 1 : 0;
        const endRecord = Math.min(currentPageNum * recordsPerPage, filteredData.length);
        document.getElementById('resultCount').textContent = `Hiển thị từ ${startRecord}-${endRecord} trong ${filteredData.length} kết quả`;

        // Render pagination buttons
        updatePaginationButtons(totalPages);
    }

    // Hàm cập nhật nút phân trang
    function updatePaginationButtons(totalPages) {
        const paginationContainer = paginationControls?.querySelector('.flex.items-center.gap-1');
        if (!paginationContainer) return;

        const maxVisiblePages = 5;
        const startPage = Math.max(1, currentPageNum - Math.floor(maxVisiblePages / 2));
        const endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

        let paginationHtml = '';

        // Previous button
        paginationHtml += `<button onclick="changePage('prev')" class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-300 ${currentPageNum <= 1 ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''}"><span class="material-symbols-outlined text-[20px]">chevron_left</span></button>`;

        // Page numbers
        if (startPage > 1) {
            paginationHtml += `<button onclick="changePage(1)" class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm font-medium">1</button>`;
            if (startPage > 2) {
                paginationHtml += `<span class="h-8 w-8 flex items-center justify-center text-slate-600 dark:text-slate-300">...</span>`;
            }
        }

        for (let i = startPage; i <= endPage; i++) {
            paginationHtml += `<button onclick="changePage(${i})" data-page="${i}" class="pagination-btn h-8 w-8 flex items-center justify-center rounded-lg ${i === currentPageNum ? 'bg-primary text-white' : 'text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700'} text-sm font-medium">${i}</button>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) {
                paginationHtml += `<span class="h-8 w-8 flex items-center justify-center text-slate-600 dark:text-slate-300">...</span>`;
            }
            paginationHtml += `<button onclick="changePage(${totalPages})" class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 dark:text-slate-300 hover:bg-gray-100 dark:hover:bg-slate-700 text-sm font-medium">${totalPages}</button>`;
        }

        // Next button
        paginationHtml += `<button onclick="changePage('next')" class="h-8 w-8 flex items-center justify-center rounded-lg text-slate-600 hover:bg-gray-100 dark:hover:bg-slate-700 dark:text-slate-300 ${currentPageNum >= totalPages ? 'opacity-50 cursor-not-allowed pointer-events-none' : ''}"><span class="material-symbols-outlined text-[20px]">chevron_right</span></button>`;

        paginationContainer.innerHTML = paginationHtml;
    }

    function changePage(page) {
        const totalPages = Math.ceil(filteredData.length / 10);
        if (page === 'prev') page = currentPageNum - 1;
        else if (page === 'next') page = currentPageNum + 1;
        else page = parseInt(page);
        if (page < 1 || page > totalPages) return;
        currentPageNum = page;
        renderTable();
        document.querySelector('table')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    // Sự kiện click nút lọc
    if (filterSelect) {
        filterSelect.addEventListener('change', function() {
            filterAndRenderTable();
        });
    }

    // Sự kiện tìm kiếm với debounce
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                filterAndRenderTable();
            }, 300);
        });
    }

    // Nút làm mới
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            if (searchInput) searchInput.value = '';
            if (filterSelect) filterSelect.value = '';
            filterAndRenderTable();
        });
    }

    // Modal functions
    function openModal() {
        document.getElementById('addWarehouseModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeModal() {
        document.getElementById('addWarehouseModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    function openEditModal(maKho, tenKho, diaChi, maVung, maThuKho, maLoaiKho) {
        document.getElementById('edit_ma_kho').value = maKho;
        document.getElementById('edit_ma_kho_display').value = maKho;
        document.getElementById('edit-warehouse-name').value = tenKho;
        document.getElementById('edit-warehouse-address').value = diaChi;
        document.getElementById('edit-warehouse-vung').value = maVung;
        document.getElementById('edit-warehouse-keeper').value = maThuKho;
        document.getElementById('edit-warehouse-type').value = maLoaiKho;

        document.getElementById('editWarehouseModal').classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    function closeEditModal() {
        document.getElementById('editWarehouseModal').classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    // Close edit modal when clicking on backdrop
    const editModalDialog = document.getElementById('editWarehouseModal');
    if (editModalDialog) {
        editModalDialog.addEventListener('click', function(e) {
            const backdrop = editModalDialog.querySelector('.fixed.inset-0');
            if (backdrop && (e.target === backdrop || e.target === editModalDialog)) {
                closeEditModal();
            }
        });

        const editForm = editModalDialog.querySelector('form');
        if (editForm) {
            editForm.onsubmit = function() {
                setTimeout(() => {
                    closeEditModal();
                }, 100);
            };
        }
    }

    function confirmDelete(maKho) {
        if (confirm('Bạn có chắc chắn muốn xóa kho này?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'delete_dskho.php';

            const deleteInput = document.createElement('input');
            deleteInput.type = 'hidden';
            deleteInput.name = 'delete-warehouse';
            deleteInput.value = maKho;
            form.appendChild(deleteInput);

            document.body.appendChild(form);
            form.submit();
        }
    }

    // Auto hide notifications
    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('[role="alert"]');
        alerts.forEach(alert => {
            setTimeout(() => {
                alert.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                setTimeout(() => alert.remove(), 500);
            }, 2000);
        });
    });
