<?php
// Kleine Icons für Prä- & Postnatal (Vivien, 24.10.2026): Schwangerschaft, Baby und Jahreszeiten in den Movecia-Farben.
// Als Inline-SVG im Mitgliederbereich, als PNG (img/movecia/icons/, per Playwright erzeugt) in Mails und Workbooks.
declare(strict_types=1);

const NATAL_ICON_COLORS = ['plum' => '#5A3C4C', 'peach' => '#E6DBCF', 'blush' => '#E4DCD1', 'rose' => '#C9A3A3', 'sand' => '#D8BFA8', 'deep' => '#3A2631'];

/** SVG-Körper (viewBox 0 0 64 64). Farben: P = Pflaume (Linie), F = Füllung. */
function natal_icon_body(string $n): string
{
    return [
        // Schwangerschaft
        'mond' => '<path d="M40 10a22 22 0 1 0 14 34A18 18 0 0 1 40 10z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M48 14l1.6 3.4 3.4 1.6-3.4 1.6L48 24l-1.6-3.4-3.4-1.6 3.4-1.6z" fill="{P}"/>',
        'herz' => '<path d="M32 54C17 44 9 36 9 25a11 11 0 0 1 23-6 11 11 0 0 1 23 6c0 11-8 19-23 29z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M14 30h9l3-6 5 12 4-9 3 3h12" fill="none" stroke="{P}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>',
        'knospe' => '<path d="M32 58V32" stroke="{P}" stroke-width="2.2" stroke-linecap="round"/><path d="M32 44c-8 0-13-5-14-11 7-1 13 3 14 11zM32 40c7 0 11-4 12-9-6-1-11 2-12 9z" fill="{F}" stroke="{P}" stroke-width="2" stroke-linejoin="round"/><path d="M32 32c-6-3-8-10-5-18 2 4 5 6 5 6s3-2 5-6c3 8 1 15-5 18z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/>',
        'kugel' => '<circle cx="32" cy="34" r="21" fill="{F}" stroke="{P}" stroke-width="2.2"/><path d="M32 44c-7-5-10-8-10-12a5 5 0 0 1 10-2 5 5 0 0 1 10 2c0 4-3 7-10 12z" fill="{P}"/><path d="M16 22c2-3 5-5 8-6" fill="none" stroke="{P}" stroke-width="2" stroke-linecap="round" opacity=".5"/>',
        'feder' => '<path d="M50 12C30 14 18 28 16 48l6-4c10-2 20-10 26-22l-8 2 10-12z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M14 52l22-26" stroke="{P}" stroke-width="2" stroke-linecap="round"/>',
        'sterne' => '<path d="M24 12l3.2 7.8L35 23l-7.8 3.2L24 34l-3.2-7.8L13 23l7.8-3.2z" fill="{F}" stroke="{P}" stroke-width="2" stroke-linejoin="round"/><path d="M44 30l2.4 5.6L52 38l-5.6 2.4L44 46l-2.4-5.6L36 38l5.6-2.4z" fill="{F}" stroke="{P}" stroke-width="2" stroke-linejoin="round"/><circle cx="46" cy="16" r="2.2" fill="{P}"/><circle cx="20" cy="46" r="1.8" fill="{P}"/>',
        // Baby
        'schuh' => '<path d="M18 14h18v20c8 1 16 5 16 12 0 4-3 6-8 6H18c-4 0-6-2-6-6V20c0-4 2-6 6-6z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M14 26h22M36 34c-4 2-6 6-6 10" fill="none" stroke="{P}" stroke-width="2" stroke-linecap="round"/><circle cx="25" cy="20" r="1.8" fill="{P}"/>',
        'rassel' => '<circle cx="24" cy="24" r="13" fill="{F}" stroke="{P}" stroke-width="2.2"/><path d="M33 33l14 14" stroke="{P}" stroke-width="5" stroke-linecap="round"/><circle cx="50" cy="50" r="5" fill="{F}" stroke="{P}" stroke-width="2.2"/><circle cx="20" cy="21" r="2" fill="{P}"/><circle cx="27" cy="27" r="2" fill="{P}"/><circle cx="27" cy="19" r="1.4" fill="{P}"/>',
        'wolke' => '<path d="M18 46a10 10 0 0 1 0-20 13 13 0 0 1 25-3 9 9 0 0 1 4 18v5z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M26 36q2 2 4 0M36 36q2 2 4 0" fill="none" stroke="{P}" stroke-width="2" stroke-linecap="round"/><circle cx="24" cy="40" r="1.6" fill="{P}" opacity=".5"/><circle cx="42" cy="40" r="1.6" fill="{P}" opacity=".5"/>',
        'wagen' => '<path d="M10 28h36a18 18 0 0 1-36 0z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M28 28V10a18 18 0 0 1 18 18z" fill="{P}" opacity=".35" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M46 28l4-10h6" fill="none" stroke="{P}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/><circle cx="20" cy="52" r="5" fill="none" stroke="{P}" stroke-width="2.2"/><circle cx="38" cy="52" r="5" fill="none" stroke="{P}" stroke-width="2.2"/>',
        'mobile' => '<path d="M32 6v8M14 14h36M14 14v10M32 14v16M50 14v8" stroke="{P}" stroke-width="2" stroke-linecap="round"/><path d="M14 24l2.4 5 5.6.8-4 4 1 5.6-5-2.6-5 2.6 1-5.6-4-4 5.6-.8z" fill="{F}" stroke="{P}" stroke-width="1.8" stroke-linejoin="round"/><path d="M38 30a10 10 0 1 1-12 12 8 8 0 0 0 12-12z" fill="{F}" stroke="{P}" stroke-width="2" stroke-linejoin="round"/><path d="M50 22c4 0 6 3 6 6s-3 6-6 6-6-3-6-6 2-6 6-6z" fill="{F}" stroke="{P}" stroke-width="2"/>',
        'body' => '<path d="M27 6h10v6H27z" fill="{P}"/><path d="M24 12h16l-2 6H26z" fill="{F}" stroke="{P}" stroke-width="2" stroke-linejoin="round"/><rect x="22" y="18" width="20" height="38" rx="7" fill="{F}" stroke="{P}" stroke-width="2.2"/><path d="M22 30h7M22 38h7M22 46h7" stroke="{P}" stroke-width="2" stroke-linecap="round"/><path d="M36 26v22" stroke="{P}" stroke-width="2" stroke-linecap="round" opacity=".4"/>',
        // Jahreszeiten
        'herbst' => '<path d="M32 8c14 8 18 24 10 38-3 5-10 10-10 10s-7-5-10-10C14 32 18 16 32 8z" fill="{F}" stroke="{P}" stroke-width="2.2" stroke-linejoin="round"/><path d="M32 18v40M32 30l-7-5M32 30l7-5M32 40l-8-5M32 40l8-5" fill="none" stroke="{P}" stroke-width="2" stroke-linecap="round"/>',
        'winter' => '<g fill="none" stroke="{P}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M32 8v48M11 20l42 24M11 44l42-24"/><path d="M26 12l6 6 6-6M26 52l6-6 6 6M12 28l8-2-2-8M52 36l-8 2 2 8M12 36l8 2-2 8M52 28l-8-2 2-8"/></g><circle cx="32" cy="32" r="5" fill="{F}" stroke="{P}" stroke-width="2"/>',
        'fruehling' => '<g fill="{F}" stroke="{P}" stroke-width="2.2"><circle cx="32" cy="18" r="9"/><circle cx="45" cy="28" r="9"/><circle cx="40" cy="44" r="9"/><circle cx="24" cy="44" r="9"/><circle cx="19" cy="28" r="9"/></g><circle cx="32" cy="32" r="6" fill="{P}"/>',
        'sommer' => '<circle cx="32" cy="32" r="12" fill="{F}" stroke="{P}" stroke-width="2.2"/><g stroke="{P}" stroke-width="2.2" stroke-linecap="round"><path d="M32 8v6M32 50v6M8 32h6M50 32h6M15 15l4 4M45 45l4 4M15 49l4-4M45 19l4-4"/></g>',
    ][$n] ?? '';
}

/** Icon als SVG-Tag. $fill = Farbname oder Hex. */
function natal_icon(string $n, int $size = 40, string $fill = 'peach', string $line = 'plum', string $attr = ''): string
{
    $c = NATAL_ICON_COLORS;
    $b = str_replace(['{P}', '{F}'], [$c[$line] ?? $line, $c[$fill] ?? $fill], natal_icon_body($n));
    return '<svg viewBox="0 0 64 64" width="' . $size . '" height="' . $size . '" aria-hidden="true" ' . $attr . '>' . $b . '</svg>';
}

/** Passende Icons: Phase (prae/post) plus die aktuelle Jahreszeit. */
function natal_icon_set(string $phase, ?int $ts = null): array
{
    $m = (int)date('n', $ts ?? time());
    $season = $m >= 3 && $m <= 5 ? 'fruehling' : ($m >= 6 && $m <= 8 ? 'sommer' : ($m >= 9 && $m <= 11 ? 'herbst' : 'winter'));
    $base = $phase === 'post' ? ['schuh', 'rassel', 'wolke', 'mobile', 'wagen', 'body', 'herz'] : ['mond', 'kugel', 'knospe', 'herz', 'sterne', 'feder'];
    return array_merge($base, [$season, $season]);
}
