<?php
/**
 * Widget Kalender Bulanan Interaktif Standar SIM-ASET
 * Menampilkan grid kalender (Sen - Min), hari libur nasional (merah), cuti bersama (kuning),
 * dan navigasi bulan dinamis via AJAX.
 */
require_once __DIR__ . '/../config.php';

$cal_year  = isset($cal_year) ? (int)$cal_year : (int)($_GET['cal_year'] ?? date('Y'));
$cal_month = isset($cal_month) ? (int)$cal_month : (int)($_GET['cal_month'] ?? date('n'));

// Validasi tahun dan bulan
if ($cal_month < 1 || $cal_month > 12) $cal_month = (int)date('n');
if ($cal_year < 2000 || $cal_year > 2099) $cal_year = (int)date('Y');

$firstDayStr    = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$startDayOfWeek = (int)date('N', strtotime($firstDayStr)); // 1 (Mon) - 7 (Sun)
$daysInMonth    = (int)date('t', strtotime($firstDayStr));

$prevMonth = $cal_month - 1;
$prevYear  = $cal_year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $cal_month + 1;
$nextYear  = $cal_year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

$nama_bulan_arr = [
    1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
    5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
    9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
];
$calMonthTitle = ($nama_bulan_arr[$cal_month] ?? date('F')) . ' ' . $cal_year;

$holidays = get_month_holidays($cal_year, $cal_month);
$holidayMap = [];
foreach ($holidays as $h) {
    $holidayMap[$h['date']] = $h;
}
?>
<style>
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}
.cal-day-header {
    text-align: center;
    font-size: 0.75rem;
    font-weight: bold;
    color: var(--text-muted, #6c757d);
    padding-bottom: 4px;
}
.cal-cell {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    font-size: 0.85rem;
    font-weight: 600;
    position: relative;
    border: 1px solid var(--border-color, #dee2e6);
    background: var(--bg-card, #fff);
    color: var(--text-main, #212529);
    user-select: none;
    transition: transform 0.15s ease, box-shadow 0.15s ease, background-color 0.2s ease, border-color 0.2s ease;
}
.cal-cell:hover:not(.empty) {
    transform: scale(1.06);
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
    z-index: 2;
}
.cal-cell.empty {
    background: var(--bg-page, #f8f9fa);
    border-color: var(--border-color, #f8f9fa);
    opacity: 0.6;
}
.cal-cell.sunday {
    color: #ef4444;
}
.cal-cell.today {
    border: 2px solid #0284c7 !important;
    font-weight: 700;
}
.cal-cell.holiday {
    background: #ef4444 !important;
    color: #ffffff !important;
    border-color: #ef4444 !important;
    cursor: help;
}
.cal-cell.cuti {
    background: #f59e0b !important;
    color: #1e293b !important;
    border-color: #f59e0b !important;
    cursor: help;
}

/* Dark mode overrides for calendar */
body.theme-dark .cal-cell,
html.theme-dark .cal-cell {
    background: #1e293b;
    color: #f8fafc;
    border-color: #334155;
}
body.theme-dark .cal-cell.empty,
html.theme-dark .cal-cell.empty {
    background: #0f172a;
    border-color: #1e293b;
}
body.theme-dark .cal-cell.sunday,
html.theme-dark .cal-cell.sunday {
    color: #f87171;
}
body.theme-dark .cal-day-header,
html.theme-dark .cal-day-header {
    color: #94a3b8;
}
body.theme-dark .cal-nav-btn,
html.theme-dark .cal-nav-btn {
    background: #1e293b !important;
    color: #f8fafc !important;
    border-color: #334155 !important;
}
body.theme-dark .cal-month-title,
html.theme-dark .cal-month-title {
    color: #f8fafc !important;
}
body.theme-dark .calendar-loading-overlay,
html.theme-dark .calendar-loading-overlay {
    background: rgba(15, 23, 42, 0.75) !important;
}
</style>

<div class="calendar-widget position-relative">
    <div id="calendar-loading" class="position-absolute w-100 h-100 d-none calendar-loading-overlay" style="background:rgba(255,255,255,0.7); z-index:10; top:0; left:0; display:flex; align-items:center; justify-content:center; border-radius:6px;">
        <div class="spinner-border text-primary spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>
    </div>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button type="button" onclick="loadCalendar(<?= $prevYear ?>, <?= $prevMonth ?>)" class="btn btn-sm btn-light border px-2 py-1 cal-nav-btn" title="Bulan Sebelumnya">&laquo;</button>
        <h6 class="mb-0 fw-bold cal-month-title"><?= e($calMonthTitle) ?></h6>
        <button type="button" onclick="loadCalendar(<?= $nextYear ?>, <?= $nextMonth ?>)" class="btn btn-sm btn-light border px-2 py-1 cal-nav-btn" title="Bulan Berikutnya">&raquo;</button>
    </div>
    
    <div class="cal-grid mb-2">
        <div class="cal-day-header">Sen</div>
        <div class="cal-day-header">Sel</div>
        <div class="cal-day-header">Rab</div>
        <div class="cal-day-header">Kam</div>
        <div class="cal-day-header">Jum</div>
        <div class="cal-day-header text-primary">Sab</div>
        <div class="cal-day-header text-danger">Min</div>
        
        <?php for ($i = 1; $i < $startDayOfWeek; $i++): ?>
            <div class="cal-cell empty"></div>
        <?php endfor; ?>
        
        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
            $currentDateStr = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $day);
            $isHoliday = isset($holidayMap[$currentDateStr]);
            $holidayData = $isHoliday ? $holidayMap[$currentDateStr] : null;
            
            $dayOfWeekIso = (int)date('N', strtotime($currentDateStr));
            $isSunday = ($dayOfWeekIso === 7);
            $isToday = ($currentDateStr === date('Y-m-d'));
            
            $classes = ['cal-cell'];
            if ($isToday) $classes[] = 'today';
            
            if ($isHoliday) {
                if (!empty($holidayData['is_cuti'])) {
                    $classes[] = 'cuti';
                } else {
                    $classes[] = 'holiday';
                }
            } elseif ($isSunday) {
                $classes[] = 'sunday';
            }
        ?>
            <div class="<?= implode(' ', $classes) ?>"
                 <?php if ($isHoliday): ?> title="<?= e($holidayData['title']) ?>" data-bs-toggle="tooltip" data-bs-placement="top" <?php endif; ?>>
                <?= $day ?>
                <?php if ($isHoliday && !empty($holidayData['is_cuti'])): ?>
                    <span style="position: absolute; top:2px; right:2px; font-size:8px;">📌</span>
                <?php endif; ?>
            </div>
        <?php endfor; ?>
    </div>
    
    <div class="mt-3 text-start small">
        <div class="d-flex align-items-center mb-1">
            <div style="width:12px; height:12px;" class="bg-danger rounded me-2"></div>
            <span class="text-muted">Libur Nasional</span>
        </div>
        <div class="d-flex align-items-center">
            <div style="width:12px; height:12px; font-size:8px; display:flex; align-items:center; justify-content:center;" class="bg-warning rounded me-2 text-dark">📌</div>
            <span class="text-muted">Cuti Bersama</span>
        </div>
    </div>
</div>
