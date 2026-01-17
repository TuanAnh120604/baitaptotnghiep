(function() {
    'use strict';

    // Kiểm tra xem form có phải là form login không (helper function)
    function isLoginFormCheck(form) {
        if (!form) return false;
        // Kiểm tra URL có chứa login không
        if (window.location.pathname.includes('login.php') || window.location.pathname.includes('/dn/')) {
            return true;
        }
        // Kiểm tra form có input password với name="password" không
        const passwordInput = form.querySelector('input[type="password"][name="password"]');
        if (passwordInput && form.action && form.action.includes('login')) {
            return true;
        }
        // Kiểm tra form có class hoặc id chứa "login" không
        if (form.id && form.id.toLowerCase().includes('login')) {
            return true;
        }
        if (form.className && form.className.toLowerCase().includes('login')) {
            return true;
        }
        return false;
    }

    // Lưu dữ liệu form vào localStorage
    function saveFormData(formId) {
        const form = document.getElementById(formId) || document.querySelector('form');
        if (!form) return;
        
        // KHÔNG lưu dữ liệu form login (lý do bảo mật)
        if (isLoginFormCheck(form)) {
            return;
        }

        const formData = {};
        const inputs = form.querySelectorAll('input, select, textarea');
        
        inputs.forEach(input => {
            // Bỏ qua các input type submit, button, hidden (trừ khi cần)
            if (input.type === 'submit' || input.type === 'button' || input.type === 'reset') {
                return;
            }
            
            // Bỏ qua checkbox và radio nếu không được chọn
            if ((input.type === 'checkbox' || input.type === 'radio') && !input.checked) {
                return;
            }

            const name = input.name || input.id;
            if (!name) return;

            if (input.type === 'checkbox' || input.type === 'radio') {
                formData[name] = input.checked ? input.value : '';
            } else {
                formData[name] = input.value;
            }
        });

        // Lưu vào localStorage với key dựa trên form ID hoặc URL
        const storageKey = `form_${formId || window.location.pathname}`;
        localStorage.setItem(storageKey, JSON.stringify(formData));
    }

    // Khôi phục dữ liệu form từ localStorage
    function restoreFormData(formId) {
        const form = document.getElementById(formId) || document.querySelector('form');
        if (!form) return;

        // KHÔNG khôi phục dữ liệu form login (lý do bảo mật)
        if (isLoginFormCheck(form)) {
            return;
        }

        const storageKey = `form_${formId || window.location.pathname}`;
        const savedData = localStorage.getItem(storageKey);
        
        if (!savedData) return;

        try {
            const formData = JSON.parse(savedData);
            const inputs = form.querySelectorAll('input, select, textarea');

            inputs.forEach(input => {
                if (input.type === 'submit' || input.type === 'button' || input.type === 'reset') {
                    return;
                }

                const name = input.name || input.id;
                if (!name || !formData.hasOwnProperty(name)) return;

                if (input.type === 'checkbox' || input.type === 'radio') {
                    input.checked = (formData[name] === input.value);
                } else {
                    input.value = formData[name] || '';
                }

                // Trigger change event để các script khác có thể xử lý
                input.dispatchEvent(new Event('change', { bubbles: true }));
            });
        } catch (e) {
            console.error('Error restoring form data:', e);
        }
    }

    // Xóa dữ liệu đã lưu (gọi sau khi submit thành công)
    function clearFormData(formId) {
        const storageKey = `form_${formId || window.location.pathname}`;
        localStorage.removeItem(storageKey);
    }

    // Xóa tất cả dữ liệu form khi trang được load
    function clearAllFormData() {
        const keys = Object.keys(localStorage);
        keys.forEach(key => {
            if (key.startsWith('form_')) {
                localStorage.removeItem(key);
            }
        });
    }

    // Xóa dữ liệu form ngay khi script được load (trước khi DOM ready)
    clearAllFormData();

    // Xóa dữ liệu form login nếu đang ở trang login
    if (window.location.pathname.includes('login.php') || window.location.pathname.includes('/dn/')) {
        // Xóa tất cả dữ liệu form khi ở trang login
        clearAllFormData();
    }

    // Khởi tạo khi DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        // Xóa tất cả dữ liệu form khi DOM ready (đảm bảo form sạch khi load)
        clearAllFormData();
        
        // Đặc biệt xóa dữ liệu form login nếu đang ở trang login
        if (window.location.pathname.includes('login.php') || window.location.pathname.includes('/dn/')) {
            clearAllFormData();
            // Xóa tất cả key có chứa "login" trong localStorage
            const keys = Object.keys(localStorage);
            keys.forEach(key => {
                if (key.toLowerCase().includes('login') || key.startsWith('form_')) {
                    localStorage.removeItem(key);
                }
            });
        }

        // Hàm khởi tạo cho một form
        function initForm(form) {
            const formId = form.id || form.getAttribute('data-form-id') || 'default';
            
            // Loại trừ form login khỏi auto-save (lý do bảo mật)
            if (isLoginFormCheck(form)) {
                // Xóa dữ liệu form login nếu có
                clearFormData(formId);
                // Không lưu dữ liệu form login
                return;
            }
            
            // KHÔNG khôi phục dữ liệu khi load trang - để form luôn sạch
            // Dữ liệu chỉ được lưu khi người dùng đang nhập để tránh mất dữ liệu khi có sự cố

            // Lưu dữ liệu khi người dùng nhập
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                // Bỏ qua các input không cần lưu
                if (input.type === 'submit' || input.type === 'button' || input.type === 'reset' || input.type === 'hidden') {
                    return;
                }

                // Lưu khi input thay đổi
                input.addEventListener('input', function() {
                    saveFormData(formId);
                });
                
                input.addEventListener('change', function() {
                    saveFormData(formId);
                });
            });

            // Xóa dữ liệu khi submit thành công
            form.addEventListener('submit', function(e) {
                // Nếu là form login, xóa ngay lập tức
                if (isLoginFormCheck(form)) {
                    clearFormData(formId);
                    return;
                }
                // Đợi một chút để đảm bảo form đã được submit
                setTimeout(function() {
                    // Chỉ xóa nếu không có lỗi validation
                    if (form.checkValidity()) {
                        clearFormData(formId);
                    }
                }, 100);
            });
        }

        // Khởi tạo tất cả form hiện có
        const forms = document.querySelectorAll('form');
        forms.forEach(initForm);

        // Sử dụng MutationObserver để theo dõi form mới được thêm vào (như modal)
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        // Kiểm tra nếu node là form
                        if (node.tagName === 'FORM') {
                            initForm(node);
                        }
                        // Kiểm tra form bên trong node
                        const forms = node.querySelectorAll && node.querySelectorAll('form');
                        if (forms) {
                            forms.forEach(initForm);
                        }
                    }
                });
            });
        });

        // Bắt đầu quan sát
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Xóa dữ liệu khi modal được mở (để form luôn sạch)
        document.addEventListener('click', function(e) {
            const button = e.target.closest('[id*="open"], [id*="add"], [id*="edit"]');
            if (button) {
                setTimeout(function() {
                    const visibleForm = document.querySelector('form:not([style*="display: none"])');
                    if (visibleForm) {
                        const formId = visibleForm.id || 'default';
                        // Xóa dữ liệu cũ khi mở modal để form luôn sạch
                        clearFormData(formId);
                    }
                }, 100);
            }
        });

        // Xóa dữ liệu form login khi trang được focus lại (nếu đang ở trang login)
        if (window.location.pathname.includes('login.php') || window.location.pathname.includes('/dn/')) {
            window.addEventListener('focus', function() {
                clearAllFormData();
            });
            
            // Xóa dữ liệu khi người dùng click vào form (đảm bảo form luôn sạch)
            document.addEventListener('click', function(e) {
                if (e.target.closest('form')) {
                    const form = e.target.closest('form');
                    if (isLoginFormCheck(form)) {
                        const formId = form.id || 'default';
                        clearFormData(formId);
                    }
                }
            });
        }
    });

    // Export functions để có thể gọi từ bên ngoài
    window.formAutoSave = {
        save: saveFormData,
        restore: restoreFormData,
        clear: clearFormData
    };
})();
