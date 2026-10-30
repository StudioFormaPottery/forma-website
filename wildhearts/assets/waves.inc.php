<?php
// Bewegter Movecia-Hintergrund (Vivien, 27.10.2026): Wellen, feine Linien, schwebende Punkte, optional der atmende Kiesel.
// Einbauen als erstes Kind eines Elements mit Klasse has-hw (oder .hero-x); Styles in cute.css.
function mv_waves(bool $pebble = true, string $cls = ''): string {
    $w = fn(string $d) => '<svg viewBox="0 0 2880 320" preserveAspectRatio="none"><path d="' . $d . ' V320 H0Z"/></svg>';
    $o = '<div class="hw calm' . ($pebble ? '' : ' no-pb') . ($cls !== '' ? ' ' . $cls : '') . '" aria-hidden="true">';
    if ($pebble) $o .= '<svg class="hw-pebble" viewBox="17 14 94 94"><path class="pb-soft" d="M56 32 C 76 29, 92 44, 91 64 C 90 84, 72 96, 53 92 C 34 88, 25 72, 29 53 C 32 40, 43 33, 56 32 Z" transform="translate(-6 -4)"/><path class="pb-line" d="M44 30 C 62 20, 90 28, 98 50 C 104 68, 92 78, 84 88 C 76 100, 50 98, 36 88 C 22 78, 22 58, 28 46 C 32 38, 38 33, 44 30 Z" transform="translate(7 4) translate(63 60) scale(0.85) translate(-63 -60)"/><circle class="pb-dot" cx="81" cy="54" r="10"/></svg>';
    $o .= '<div class="hw-lines"><svg viewBox="0 0 2880 300" preserveAspectRatio="none"><path d="M0 150 C240 70 480 70 720 150 S1200 230 1440 150 S1920 70 2160 150 S2640 230 2880 150"/><path d="M0 190 C240 120 480 120 720 190 S1200 260 1440 190 S1920 120 2160 190 S2640 260 2880 190"/><path d="M0 110 C240 40 480 40 720 110 S1200 180 1440 110 S1920 40 2160 110 S2640 180 2880 110"/></svg></div>';
    $o .= '<div class="hw-wave w1">' . $w('M0 150 C240 70 480 70 720 150 S1200 230 1440 150 S1920 70 2160 150 S2640 230 2880 150') . '</div>';
    $o .= '<div class="hw-wave w2">' . $w('M0 170 C240 240 480 240 720 170 S1200 100 1440 170 S1920 240 2160 170 S2640 100 2880 170') . '</div>';
    $o .= '<div class="hw-wave w3">' . $w('M0 200 C240 140 480 140 720 200 S1200 260 1440 200 S1920 140 2160 200 S2640 260 2880 200') . '</div>';
    return $o . '<span class="hw-dot d1"></span><span class="hw-dot d2"></span><span class="hw-dot d3"></span><span class="hw-dot d4"></span></div>';
}
