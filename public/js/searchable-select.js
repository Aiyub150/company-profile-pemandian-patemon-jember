/**
 * Patemon Searchable Select Controller
 * Memberikan search bar interaktif pada semua elemen <select> (Feedback-6 Poin 5)
 * Mendukung pencarian instan, keyboard navigation, light/dark mode, dan responsif.
 */
(function() {
    'use strict';

    function initSearchableSelect(select) {
        if (!select || select.dataset.searchableInit === 'true') return;
        // Jangan bungkus jika elemen memiliki data-no-search="true"
        if (select.dataset.noSearch === 'true') return;

        select.dataset.searchableInit = 'true';

        // Sembunyikan elemen select asli tetapi tetap jaga di DOM agar Form submit berjalan normal
        select.style.display = 'none';

        // Buat komponen wrapper pengganti
        const container = document.createElement('div');
        container.className = 'patemon-select-container';

        // Ambil info ukuran/lebar jika ada inline style
        if (select.style.width) {
            container.style.width = select.style.width;
        }

        // Trigger Button
        const toggleBtn = document.createElement('button');
        toggleBtn.type = 'button';
        toggleBtn.className = 'patemon-select-btn';
        if (select.classList.contains('form-select-sm')) {
            toggleBtn.style.minHeight = '34px';
            toggleBtn.style.padding = '0.35rem 0.65rem';
            toggleBtn.style.fontSize = '0.825rem';
        }

        const labelSpan = document.createElement('span');
        labelSpan.className = 'patemon-select-text text-truncate';

        const arrowIcon = document.createElement('i');
        arrowIcon.className = 'fa-solid fa-chevron-down patemon-select-arrow';

        toggleBtn.appendChild(labelSpan);
        toggleBtn.appendChild(arrowIcon);

        // Menu Dropdown
        const menu = document.createElement('div');
        menu.className = 'patemon-select-menu';
        menu.style.display = 'none';

        // Search Bar Wrapper di dalam Select Option (Feedback-6 Poin 5)
        const searchWrapper = document.createElement('div');
        searchWrapper.className = 'patemon-select-search-wrapper';

        const searchIcon = document.createElement('i');
        searchIcon.className = 'fa-solid fa-magnifying-glass patemon-select-search-icon';

        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'patemon-select-search-field';
        searchInput.placeholder = 'Ketik untuk mencari...';
        searchInput.autocomplete = 'off';

        searchWrapper.appendChild(searchIcon);
        searchWrapper.appendChild(searchInput);

        // List container
        const list = document.createElement('div');
        list.className = 'patemon-select-list';
        list.setAttribute('role', 'listbox');

        // Empty message
        const emptyMsg = document.createElement('div');
        emptyMsg.className = 'patemon-select-empty';
        emptyMsg.innerHTML = '<i class="fa-solid fa-circle-question me-1"></i> Tidak ada pilihan yang cocok';
        emptyMsg.style.display = 'none';

        menu.appendChild(searchWrapper);
        menu.appendChild(list);
        menu.appendChild(emptyMsg);

        container.appendChild(toggleBtn);
        container.appendChild(menu);

        // Sisipkan container tepat setelah select asli
        select.parentNode.insertBefore(container, select.nextSibling);

        let highlightedIndex = -1;

        // Render opsi-opsi dari <option> asli
        function renderOptions(filterText) {
            list.innerHTML = '';
            const query = (filterText || '').toLowerCase().trim();
            const options = Array.from(select.options);
            let visibleCount = 0;

            options.forEach((opt, idx) => {
                const text = opt.textContent.trim();
                const val = opt.value;
                const isSelected = opt.selected;
                const isMatch = !query || text.toLowerCase().includes(query);

                if (isMatch) {
                    visibleCount++;
                    const item = document.createElement('div');
                    item.className = 'patemon-select-option' + (isSelected ? ' active' : '');
                    item.dataset.value = val;
                    item.dataset.index = idx;
                    item.textContent = text;

                    item.addEventListener('click', (e) => {
                        e.stopPropagation();
                        selectOption(val, text);
                    });

                    list.appendChild(item);
                }
            });

            emptyMsg.style.display = visibleCount === 0 ? 'block' : 'none';
            highlightedIndex = -1;
        }

        function updateButtonLabel() {
            const selectedOpt = select.options[select.selectedIndex];
            labelSpan.textContent = selectedOpt ? selectedOpt.textContent.trim() : 'Pilih...';
        }

        function selectOption(val, text) {
            select.value = val;
            updateButtonLabel();
            closeDropdown();

            // Trigger change event pada select asli agar onchange / form handlers berjalan
            select.dispatchEvent(new Event('change', { bubbles: true }));
            if (typeof select.onchange === 'function') {
                select.onchange();
            }
        }

        function openDropdown() {
            // Tutup dropdown lain yang sedang terbuka
            document.querySelectorAll('.patemon-select-container.open').forEach(c => {
                if (c !== container) {
                    c.classList.remove('open');
                    const m = c.querySelector('.patemon-select-menu');
                    if (m) m.style.display = 'none';
                }
            });

            container.classList.add('open');
            menu.style.display = 'block';
            searchInput.value = '';
            renderOptions('');
            setTimeout(() => searchInput.focus(), 50);
        }

        function closeDropdown() {
            container.classList.remove('open');
            menu.style.display = 'none';
            searchInput.value = '';
        }

        // Toggle klik
        toggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            e.stopPropagation();
            if (container.classList.contains('open')) {
                closeDropdown();
            } else {
                openDropdown();
            }
        });

        // Search input event
        searchInput.addEventListener('input', (e) => {
            renderOptions(e.target.value);
        });

        // Prevent dropdown click from closing menu
        menu.addEventListener('click', (e) => {
            e.stopPropagation();
        });

        // Keyboard navigation
        searchInput.addEventListener('keydown', (e) => {
            const visibleItems = Array.from(list.querySelectorAll('.patemon-select-option'));
            if (!visibleItems.length) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % visibleItems.length;
                updateHighlight(visibleItems);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex - 1 + visibleItems.length) % visibleItems.length;
                updateHighlight(visibleItems);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && visibleItems[highlightedIndex]) {
                    const chosen = visibleItems[highlightedIndex];
                    selectOption(chosen.dataset.value, chosen.textContent);
                }
            } else if (e.key === 'Escape') {
                closeDropdown();
                toggleBtn.focus();
            }
        });

        function updateHighlight(items) {
            items.forEach((it, idx) => {
                it.classList.toggle('highlighted', idx === highlightedIndex);
                if (idx === highlightedIndex) {
                    it.scrollIntoView({ block: 'nearest' });
                }
            });
        }

        // Initial setup
        updateButtonLabel();
    }

    // Inisialisasi semua elemen select di halaman
    function initAllSearchableSelects(root) {
        const context = root || document;
        const selector = 'select.searchable-select, select.form-select-modern, select.form-select';
        const selects = context.querySelectorAll(selector);
        selects.forEach(sel => {
            initSearchableSelect(sel);
        });
    }

    // Tutup saat klik di luar
    document.addEventListener('click', () => {
        document.querySelectorAll('.patemon-select-container.open').forEach(c => {
            c.classList.remove('open');
            const m = c.querySelector('.patemon-select-menu');
            if (m) m.style.display = 'none';
        });
    });

    // Jalankan saat DOM siap
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAllSearchableSelects());
    } else {
        initAllSearchableSelects();
    }

    // Ekspor fungsi ke window agar dapat dipanggil dinamis
    window.initSearchableSelects = initAllSearchableSelects;
    window.initSingleSearchableSelect = initSearchableSelect;
})();
