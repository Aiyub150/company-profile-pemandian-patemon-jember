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
    </script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
</body>
</html>
