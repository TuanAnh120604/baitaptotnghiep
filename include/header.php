
<header class="h-16 bg-surface-light dark:bg-surface-dark border-b border-border-light dark:border-border-dark flex items-center justify-between px-6 transition-colors duration-200">                
        <div class="flex items-center space-x-4 ml-auto">
          <div class="flex items-center space-x-2 pl-4 ml-2">
            <img alt="User Avatar" class="h-8 w-8 rounded-full" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBTMSJhLzVScKP-Vq_uP5RmsK5jjRhxjnfKQT8VCj6jISD76HjBDXenZ8Ak-mfYl7ML40tv4A97HlTMDmXW7ArKt2LfpNP0LHqj-GGp3Fzi-XYIAlwglMmtS0hQi7A8FoSDBXu9W8SimcyTxNPeB171yCiWHE8HKXWR0vYj2_L6HEC0PAEVaArVsE0P6EQRhOw_Uz9718IOaQXUs-oQfZcJQ5tmSRthb8ALgG3CZkrWkGqOTU7OXljtVMW10Pgjp2-26sDlwSHhcS8" />
            <div class="hidden md:block">
              <p class="text-sm font-medium text-slate-700 dark:text-slate-200">
                <?php echo $_SESSION['user_name'] ?? 'nguoi_dung'; ?>
              </p>
              <p class="text-xs text-slate-500">
                <?php echo $_SESSION['role'] ?? 'chuc_vu'; ?>
              </p>
            </div>
          </div>
        </div>
</header>