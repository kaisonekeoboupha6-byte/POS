    </main>
  </div>
</div>

<!-- Reusable Custom Confirm Modal -->
<div class="modal-bg no-print" id="globalConfirmModal" role="dialog" aria-modal="true" aria-labelledby="gcTitle">
  <div class="modal" style="width: 420px;">
    <h3 id="gcTitle">
      <i data-lucide="alert-circle" aria-hidden="true" style="color:var(--danger);"></i>
      <span>ຢືນຢັນການດຳເນີນການ</span>
    </h3>
    <p id="gcMsg" style="color:var(--text-muted); font-size:14px; line-height:1.6; margin-bottom:20px;"></p>
    <div style="display:flex; gap:10px; justify-content:flex-end;">
      <button type="button" class="btn btn-secondary" onclick="closeGlobalConfirm()">ຍົກເລີກ</button>
      <button type="button" class="btn btn-danger" id="gcOkBtn">
        <i data-lucide="check" aria-hidden="true"></i>
        <span>ຢືນຢັນ</span>
      </button>
    </div>
  </div>
</div>

<script>
  (function () {
    var sidebar = document.getElementById('appSidebar');
    var backdrop = document.getElementById('sidebarBackdrop');

    try {
      if (sidebar && localStorage.getItem('pos_sidebar_collapsed') === '1' && window.innerWidth > 1024) {
        sidebar.classList.add('collapsed');
      }
    } catch (e) {}

    window.toggleSidebarCollapse = function () {
      if (!sidebar) return;
      sidebar.classList.toggle('collapsed');
      try {
        localStorage.setItem('pos_sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
      } catch (e) {}
    };

    window.openMobileSidebar = function () {
      if (sidebar) sidebar.classList.add('open');
      if (backdrop) backdrop.classList.add('show');
    };

    window.closeMobileSidebar = function () {
      if (sidebar) sidebar.classList.remove('open');
      if (backdrop) backdrop.classList.remove('show');
    };

    window.hydrateIcons = function (root) {
      if (window.lucide) {
        lucide.createIcons(root ? { root: root } : undefined);
      }
    };

    function syncThemeIcon(theme) {
      var icon = document.getElementById('themeIcon');
      if (!icon) return;
      icon.setAttribute('data-lucide', theme === 'dark' ? 'sun' : 'moon');
      window.hydrateIcons(icon.parentElement || document.body);
    }

    window.toggleTheme = function () {
      var root = document.documentElement;
      var next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
      root.setAttribute('data-theme', next);
      try { localStorage.setItem('pos_theme', next); } catch (e) {}
      syncThemeIcon(next);
      document.dispatchEvent(new CustomEvent('pos:themechange', { detail: { theme: next } }));
    };

    var pendingConfirmForm = null;
    window.confirmAction = function (formEl, message) {
      var modal = document.getElementById('globalConfirmModal');
      var msgEl = document.getElementById('gcMsg');
      if (!modal || !msgEl) return true;
      pendingConfirmForm = formEl;
      msgEl.textContent = message || 'ຕ້ອງການດຳເນີນການນີ້ແທ້ບໍ່?';
      modal.classList.add('show');
      var okBtn = document.getElementById('gcOkBtn');
      if (okBtn) okBtn.focus();
      return false;
    };

    window.closeGlobalConfirm = function () {
      pendingConfirmForm = null;
      var modal = document.getElementById('globalConfirmModal');
      if (modal) modal.classList.remove('show');
    };

    var okBtn = document.getElementById('gcOkBtn');
    if (okBtn) {
      okBtn.addEventListener('click', function () {
        var f = pendingConfirmForm;
        window.closeGlobalConfirm();
        if (f) f.submit();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        window.closeMobileSidebar();
        window.closeGlobalConfirm();
        document.querySelectorAll('.modal-bg.show').forEach(function (m) {
          m.classList.remove('show');
        });
      }
    });

    function initShellIcons() {
      syncThemeIcon(document.documentElement.getAttribute('data-theme') || 'light');
      window.hydrateIcons();
    }
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initShellIcons);
    } else {
      initShellIcons();
    }
  })();
</script>
</body>
</html>
