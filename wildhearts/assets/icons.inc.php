<?php
// Feine Linien-Icons für Movecia (Vivien, 26.10.2026): minimalistisch, ausdrucksstark, wechseln sanft (mv_icons()).
function mv_icon_svg(string $k): string {
    $p = [
        'sonne'   => '<path d="M8 30h32"/><path d="M14 30a10 10 0 0 1 20 0"/><path d="M24 12v4M12.5 17.5l2.8 2.8M35.5 17.5l-2.8 2.8M7 24h3.5M37.5 24H41"/><path d="M14 36h20" opacity=".55"/>',
        'blatt'   => '<path d="M12 36C12 20 22 11 37 11c0 15-9 25-25 25z"/><path d="M12 36L29 19"/><path d="M19 29h7M23 25v-6" opacity=".6"/>',
        'knospe'  => '<path d="M24 41V25"/><path d="M24 25c-6-2-8-8-6-15 4 1 6 4 6 4s2-3 6-4c2 7 0 13-6 15z"/><path d="M24 34c-4 0-7-2-8-5 4-1 7 0 8 3M24 36c4 0 7-2 8-5-4-1-7 0-8 3"/>',
        'bluete'  => '<circle cx="24" cy="21" r="3.2"/><path d="M24 17.8c-2-5 2-9 0-11-2 2 2 6 0 11zM27.2 21c5-2 9 2 11 0-2-2-6 2-11 0zM24 24.2c2 5-2 9 0 11 2-2-2-6 0-11zM20.8 21c-5 2-9-2-11 0 2 2 6-2 11 0z"/><path d="M24 35v7"/>',
        'mond'    => '<path d="M30 9a14 14 0 1 0 9 22A11.5 11.5 0 0 1 30 9z"/><path d="M36 13.5v4M34 15.5h4" opacity=".7"/>',
        'herz'    => '<path d="M24 38s-13-7.5-13-17a7 7 0 0 1 13-3.6A7 7 0 0 1 37 21c0 9.5-13 17-13 17z"/><circle cx="24" cy="25" r="3.2" opacity=".7"/>',
        'kerze'   => '<path d="M19 41V22h10v19"/><path d="M15 41h18"/><path d="M24 22v-3"/><path d="M24 17c-2.6-2.2-2.6-5.2 0-8 2.6 2.8 2.6 5.8 0 8z"/>',
        'kreis'   => '<circle cx="24" cy="24" r="13" opacity=".45"/><circle cx="24" cy="10.5" r="2.4"/><circle cx="36.8" cy="19.8" r="2.4"/><circle cx="31.9" cy="34.9" r="2.4"/><circle cx="16.1" cy="34.9" r="2.4"/><circle cx="11.2" cy="19.8" r="2.4"/>',
        'welle'   => '<path d="M6 20c4.5-5 8.5-5 13 0s8.5 5 13 0 8.5-5 13 0"/><path d="M6 29c4.5-5 8.5-5 13 0s8.5 5 13 0 8.5-5 13 0" opacity=".55"/>',
        'atem'    => '<circle cx="24" cy="24" r="5"/><circle cx="24" cy="24" r="11" opacity=".6"/><circle cx="24" cy="24" r="17" opacity=".3"/>',
        'berg'    => '<path d="M5 37l12-17 7 9 5-6 14 14z"/><path d="M14 24.5l3-4.5 3 3.5" opacity=".6"/><circle cx="35" cy="12" r="3"/>',
        'spirale' => '<path d="M24 24c0-2 2.5-2.5 3.5-1s0 4.5-3 4.5-5.5-3-4.5-6.5 5-5.5 8.5-4 6 6.5 4 10.5-7.5 7-12 5.5-8.5-6.5-7.5-11.5 6-9.5 12-9.5"/>',
    ];
    return '<svg viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">' . ($p[$k] ?? '') . '</svg>';
}
// Zwei bis drei Icons, die sich abwechseln (CSS .ico-swap, Takt per --d).
function mv_icons(array $keys, string $class = 'ico', float $delay = 0): string {
    $out = '<span class="' . $class . ' ico-swap" aria-hidden="true" style="--n:' . count($keys) . ';--d:' . $delay . 's">';
    foreach ($keys as $i => $k) $out .= '<i style="--i:' . $i . '">' . mv_icon_svg($k) . '</i>';
    return $out . '</span>';
}
