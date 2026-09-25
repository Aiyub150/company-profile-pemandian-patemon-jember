/**
 * Modul Waktu Dinamis (Dynamic Time & Timezone Engine)
 * Pemandian Patemon Jember
 * Mendukung deteksi dinamis WIB (UTC+7), WITA (UTC+8), WIT (UTC+9),
 * serta zona waktu internasional otomatis berbasis offset perangkat pengguna.
 */

(function() {
    'use strict';

    function getDynamicTimezoneInfo() {
        const now = new Date();
        // getTimezoneOffset mengembalikan selisih menit dari UTC (negatif untuk timur UTC)
        const utcOffsetHours = -now.getTimezoneOffset() / 60;
        
        // Selisih jam relatif terhadap WIB (Waktu Indonesia Barat = UTC+7)
        const diffFromWib = Math.round(utcOffsetHours - 7);

        let tzCode = 'WIB';
        let tzName = 'Waktu Indonesia Barat';
        let tzOffsetStr = 'UTC+7';

        if (diffFromWib === 0) {
            tzCode = 'WIB';
            tzName = 'Waktu Indonesia Barat';
            tzOffsetStr = 'UTC+7';
        } else if (diffFromWib === 1) {
            tzCode = 'WITA';
            tzName = 'Waktu Indonesia Tengah';
            tzOffsetStr = 'UTC+8';
        } else if (diffFromWib === 2) {
            tzCode = 'WIT';
            tzName = 'Waktu Indonesia Timur';
            tzOffsetStr = 'UTC+9';
        } else {
            // Pengguna di luar Indonesia (Internasional)
            let ianaTz = '';
            try {
                ianaTz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
            } catch(e) {}

            const sign = utcOffsetHours >= 0 ? '+' : '-';
            const absOffset = Math.abs(utcOffsetHours);
            const intPart = Math.floor(absOffset);
            const fracPart = Math.round((absOffset - intPart) * 60);
            const gmtStr = 'GMT' + sign + intPart + (fracPart > 0 ? ':' + String(fracPart).padStart(2, '0') : '');

            let cityName = ianaTz.includes('/') ? ianaTz.split('/').pop().replace(/_/g, ' ') : '';
            tzCode = cityName ? `${cityName} (${gmtStr})` : gmtStr;
            tzName = ianaTz ? `${ianaTz} (${gmtStr})` : gmtStr;
            tzOffsetStr = gmtStr;
        }

        return {
            code: tzCode,
            name: tzName,
            offsetStr: tzOffsetStr,
            diffFromWib: diffFromWib
        };
    }

    function formatLocalizedDate(date, lang) {
        const daysId = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        const monthsId = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        const daysEn = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        const monthsEn = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        const dayIdx = date.getDay();
        const dateNum = date.getDate();
        const monthIdx = date.getMonth();
        const year = date.getFullYear();

        if (lang === 'en') {
            return `${daysEn[dayIdx]}, ${monthsEn[monthIdx]} ${dateNum}, ${year}`;
        }
        return `${daysId[dayIdx]}, ${dateNum} ${monthsId[monthIdx]} ${year}`;
    }

    function updateLiveClock() {
        const clockEl = document.getElementById('topbar-clock');
        const dateEl = document.getElementById('topbar-date');
        const containerEl = document.querySelector('.topbar-time-pill');

        const now = new Date();
        const tz = getDynamicTimezoneInfo();
        const currentLang = localStorage.getItem('patemon_lang') || 'id';

        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');

        if (clockEl) {
            clockEl.textContent = `${h}:${m}:${s} ${tz.code}`;
            clockEl.setAttribute('title', `${tz.name} (${tz.offsetStr})`);
        }

        if (dateEl) {
            dateEl.textContent = formatLocalizedDate(now, currentLang);
        }

        if (containerEl) {
            containerEl.setAttribute('title', `Waktu Lokal Perangkat: ${tz.name} (${tz.offsetStr})`);
        }
    }

    // Ekspor fungsi ke global scope
    window.getPatemonTimezoneInfo = getDynamicTimezoneInfo;
    window.updatePatemonLiveClock = updateLiveClock;

    document.addEventListener('DOMContentLoaded', function() {
        updateLiveClock();
        setInterval(updateLiveClock, 1000);
    });

    // Perbarui tanggal saat bahasa berganti
    window.addEventListener('patemon_language_changed', function() {
        updateLiveClock();
    });
})();
