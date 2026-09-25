<?php
/**
 * Master Admin & Staff Layout - Footer
 * Pemandian Patemon
 */
?>
            <div class="mt-5">
                <?php include __DIR__ . '/../partials/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Core Scripts -->
    <script src="<?= public_url('assets/js/bootstrap.js') ?>"></script>
    <script>
    // Universal table search filter helper
    function setupTableSearch(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return;

        input.addEventListener('keyup', function() {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    }

    // Mobile Sidebar Interactive Controller (Feedback-5 Poin 6)
    function togglePatemonSidebar(forceState) {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;
        const isOpen = sidebar.classList.contains('active');
        const shouldOpen = (typeof forceState === 'boolean') ? forceState : !isOpen;
        if (shouldOpen) {
            sidebar.classList.add('active');
            if (window.innerWidth < 1200) {
                document.body.style.overflow = 'hidden';
            }
        } else {
            sidebar.classList.remove('active');
            document.body.style.overflow = '';
        }
    }
    window.togglePatemonSidebar = togglePatemonSidebar;

    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        // Initial state: disable/hide sidebar on mobile (<1200px)
        if (sidebar) {
            if (window.innerWidth >= 1200) {
                sidebar.classList.add('active');
            } else {
                sidebar.classList.remove('active');
            }
        }

        // Attach to burger buttons
        document.querySelectorAll('.burger-btn, #mobileBurgerBtn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                togglePatemonSidebar();
            });
        });

        // Attach to close buttons inside sidebar
        document.querySelectorAll('.sidebar-hide, .sidebar-toggler').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                togglePatemonSidebar(false);
            });
        });

        // Attach to backdrop
        document.querySelectorAll('.sidebar-backdrop').forEach(el => {
            el.addEventListener('click', () => {
                togglePatemonSidebar(false);
            });
        });

        // Window resize
        window.addEventListener('resize', () => {
            if (!sidebar) return;
            if (window.innerWidth >= 1200) {
                sidebar.classList.add('active');
                document.body.style.overflow = '';
            } else if (!sidebar.classList.contains('active')) {
                document.body.style.overflow = '';
            }
        });
    });

    // Live System Clock & Timezone handled automatically by dynamic-time.js
    if (typeof window.updatePatemonLiveClock === 'function') {
        window.updatePatemonLiveClock();
    }

    // Dark / Light Theme Controller
    function togglePatemonTheme() {
        const isDark = document.body.classList.contains('theme-dark') || document.documentElement.classList.contains('theme-dark');
        const newTheme = isDark ? 'light' : 'dark';
        applyPatemonTheme(newTheme);
        localStorage.setItem('patemon_theme', newTheme);
    }

    function applyPatemonTheme(theme) {
        const btn = document.getElementById('themeToggleBtn');
        const label = document.getElementById('themeLabelText');
        if (theme === 'dark') {
            document.body.classList.add('theme-dark');
            document.documentElement.classList.add('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'dark');
            if (btn) {
                btn.classList.add('active-dark');
                btn.setAttribute('title', 'Beralih ke Mode Terang');
            }
            if (label) label.textContent = 'Gelap';
        } else {
            document.body.classList.remove('theme-dark');
            document.documentElement.classList.remove('theme-dark');
            document.documentElement.setAttribute('data-bs-theme', 'light');
            if (btn) {
                btn.classList.remove('active-dark');
                btn.setAttribute('title', 'Beralih ke Mode Gelap');
            }
            if (label) label.textContent = 'Terang';
        }

        // Broadcast event agar komponen dinamis seperti grafik / widget kalender merespons
        window.dispatchEvent(new CustomEvent('patemon_theme_changed', { detail: { theme: theme } }));
    }

    document.addEventListener('DOMContentLoaded', () => {
        const currentTheme = localStorage.getItem('patemon_theme') || 'light';
        applyPatemonTheme(currentTheme);
    });
    </script>
    <script src="<?= public_url('js/searchable-select.js') ?>"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
