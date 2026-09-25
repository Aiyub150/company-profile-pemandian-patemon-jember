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

    // Live System Clock Ticker
    (function() {
        const clockEl = document.getElementById('topbar-clock');
        if (clockEl) {
            function tick() {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                clockEl.textContent = `${h}:${m}:${s} WIB`;
            }
            setInterval(tick, 1000);
        }
    })();

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
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
