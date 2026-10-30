# Movecia-Workbook-Icons (viewBox 64): feine Pflaume-Linie, Füllung Pfirsich/Blush/Rosé. Gleicher Stil wie api/natal-icons.inc.php.
P, F, B, R = '#4A3040', '#E6DBCF', '#E4DCD1', '#D9B8B4'
S = f'stroke="{P}" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"'
N = f'fill="none" {S}'


def spark(x, y, r=4, c=R):
    return f'<path d="M{x} {y-r}l{r*.3:.1f} {r*.7:.1f} {r*.7:.1f} {r*.3:.1f}-{r*.7:.1f} {r*.3:.1f}L{x} {y+r}l-{r*.3:.1f}-{r*.7:.1f}-{r*.7:.1f}-{r*.3:.1f} {r*.7:.1f}-{r*.3:.1f}z" fill="{c}"/>'


ICONS = {
    'impuls': f'<path d="M32 8a16 16 0 0 0-9 29c2 2 3 4 3 7h12c0-3 1-5 3-7a16 16 0 0 0-9-29z" fill="{F}" {S}/><path d="M26 50h12M28 56h8" {N}/><path d="M32 32c-5-3-7-6-7-8a3.5 3.5 0 0 1 7-1 3.5 3.5 0 0 1 7 1c0 2-2 5-7 8z" fill="{P}"/>' + spark(51, 13),
    'uebung': f'<rect x="8" y="30" width="40" height="14" rx="7" fill="{F}" {S}/><circle cx="48" cy="37" r="9" fill="{B}" {S}/><circle cx="48" cy="37" r="3.5" {N}/><path d="M14 37h22" {N} opacity=".5"/>' + spark(20, 16, 5) + spark(36, 12, 3.5, P),
    'atem': f'<path d="M8 24h30a7 7 0 1 0-7-7" {N}/><path d="M8 34h40a8 8 0 1 1-8 8" {N}/><path d="M8 44h18" {N}/><circle cx="50" cy="16" r="6" fill="{F}" {S}/>',
    'meditation': f'<path d="M22 30h20v22a4 4 0 0 1-4 4H26a4 4 0 0 1-4-4z" fill="{F}" {S}/><path d="M32 30v-6" {N}/><path d="M32 6c6 7 6 12 0 16-6-4-6-9 0-16z" fill="{R}" {S}/><path d="M12 56h40" {N}/>' + spark(11, 22, 3, P) + spark(52, 16, 4, P),
    'home': f'<path d="M10 30L32 10l22 20v22a4 4 0 0 1-4 4H14a4 4 0 0 1-4-4z" fill="{F}" {S}/><path d="M32 46c-6-4-9-7-9-10a4.5 4.5 0 0 1 9-2 4.5 4.5 0 0 1 9 2c0 3-3 6-9 10z" fill="{P}"/><path d="M44 14v-4h6v10" {N}/>',
    'journal': f'<rect x="12" y="8" width="32" height="46" rx="4" fill="{F}" {S}/><path d="M12 16h-3M12 26h-3M12 36h-3M12 46h-3" {N}/><path d="M20 20h16M20 28h16M20 36h10" {N} opacity=".55"/><path d="M50 18l6 6-18 18-8 2 2-8z" fill="{B}" {S}/><path d="M46 22l6 6" {N}/>',
    'merk': f'<path d="M32 8l6 14 15 1-11 10 4 15-14-8-14 8 4-15-11-10 15-1z" fill="{F}" {S}/><circle cx="32" cy="29" r="3" fill="{P}"/>',
    'stern': f'<path d="M32 6c2 14 10 22 24 24-14 2-22 10-24 24-2-14-10-22-24-24 14-2 22-10 24-24z" fill="{F}" {S}/>' + spark(52, 12, 4, P) + spark(12, 50, 3),
    'tipp': f'<path d="M32 54C18 45 11 37 11 27a10 10 0 0 1 21-5 10 10 0 0 1 21 5c0 10-7 18-21 27z" fill="{B}" {S}/><path d="M24 26a5 5 0 0 1 5-5" {N}/>',
    'herz': f'<path d="M32 54C18 45 11 37 11 27a10 10 0 0 1 21-5 10 10 0 0 1 21 5c0 10-7 18-21 27z" fill="{F}" {S}/><path d="M32 40c-4-3-6-5-6-7a3 3 0 0 1 6-1 3 3 0 0 1 6 1c0 2-2 4-6 7z" fill="{P}"/>' + spark(52, 12, 4),
    'uhr': f'<circle cx="32" cy="34" r="20" fill="{B}" {S}/><path d="M32 22v12l8 5M26 8h12M32 8v6" {N}/>',
    'stimmung': f'<circle cx="32" cy="32" r="22" fill="{F}" {S}/><circle cx="24" cy="28" r="2.4" fill="{P}"/><circle cx="40" cy="28" r="2.4" fill="{P}"/><path d="M23 38q9 8 18 0" {N}/><circle cx="18" cy="36" r="3" fill="{R}" opacity=".8"/><circle cx="46" cy="36" r="3" fill="{R}" opacity=".8"/>',
    'wasser': f'<path d="M32 8c10 13 16 22 16 30a16 16 0 0 1-32 0c0-8 6-17 16-30z" fill="{B}" {S}/><path d="M24 40a8 8 0 0 0 6 7" {N}/>',
    'zweig': f'<path d="M8 48C24 46 40 36 56 16" {N}/><path d="M20 46c-2-6 0-11 5-13 2 6 0 11-5 13zM32 40c-2-6 0-11 5-13 2 6 0 11-5 13zM44 30c-2-6 0-11 5-13 2 6 0 11-5 13z" fill="{F}" {S}/>',
    'kopf': f'<path d="M20 56V44c-6-4-9-10-9-17a21 21 0 0 1 42 0c0 4 2 7 4 10l-5 2v6a4 4 0 0 1-4 4h-6v7" fill="{F}" {S}/><path d="M24 26a5 5 0 0 1 5-5h1a5 5 0 0 1 9 2 4 4 0 0 1 0 8H27a4 4 0 0 1-3-5z" fill="{B}" {S}/>' + spark(14, 10, 3, P),
    'kalender': f'<rect x="9" y="13" width="46" height="42" rx="6" fill="{F}" {S}/><path d="M9 25h46M21 8v10M43 8v10" {N}/><circle cx="21" cy="35" r="2.4" fill="{P}"/><circle cx="32" cy="35" r="2.4" fill="{P}"/><path d="M42 46c-4-3-6-5-6-7a3 3 0 0 1 6-1 3 3 0 0 1 6 1c0 2-2 4-6 7z" fill="{R}"/>',
    'akku': f'<rect x="8" y="20" width="42" height="24" rx="6" fill="{B}" {S}/><path d="M50 28h4v8h-4" {N}/><rect x="13" y="25" width="10" height="14" rx="2.5" fill="{P}"/><rect x="26" y="25" width="10" height="14" rx="2.5" fill="{R}"/><path d="M32 8l-4 8h6l-4 8" {N}/>',
    'schild': f'<path d="M32 6l20 8v14c0 14-9 23-20 28-11-5-20-14-20-28V14z" fill="{F}" {S}/><path d="M32 40c-5-4-8-6-8-9a4 4 0 0 1 8-2 4 4 0 0 1 8 2c0 3-3 5-8 9z" fill="{P}"/>',
    'keim': f'<path d="M32 58V30" {N}/><path d="M32 34C30 20 20 14 8 16c1 12 11 19 24 18zM32 28c2-12 12-18 24-16-1 12-11 18-24 16z" fill="{F}" {S}/><path d="M18 58h28" {N}/>' + spark(50, 44, 3.5, P),
    'blume': f'<path d="M32 36v22M32 50c-6 0-10-3-12-8 6-1 10 2 12 8z" {N}/><g fill="{F}" {S}><circle cx="32" cy="14" r="7"/><circle cx="43" cy="22" r="7"/><circle cx="39" cy="34" r="7"/><circle cx="25" cy="34" r="7"/><circle cx="21" cy="22" r="7"/></g><circle cx="32" cy="25" r="5" fill="{P}"/>',
    'berg': f'<path d="M4 54l18-28 9 12 9-18 20 34z" fill="{F}" {S}/><path d="M36 28l4-8 5 9-4 3z" fill="#fff" {S}/><circle cx="48" cy="12" r="5" fill="{B}" {S}/>',
    'mond': f'<path d="M40 10a22 22 0 1 0 14 34A18 18 0 0 1 40 10z" fill="{F}" {S}/>' + spark(50, 18, 4.5, P) + spark(54, 32, 3),
    'sonne': f'<circle cx="32" cy="32" r="12" fill="{F}" {S}/><path d="M32 8v6M32 50v6M8 32h6M50 32h6M15 15l4 4M45 45l4 4M15 49l4-4M45 19l4-4" {N}/>',
    'kerze': f'<rect x="22" y="28" width="20" height="28" rx="3" fill="{F}" {S}/><path d="M32 28v-5" {N}/><path d="M32 6c5 6 5 10 0 14-5-4-5-8 0-14z" fill="{R}" {S}/><path d="M26 36h12" {N} opacity=".5"/>',
    'tee': f'<path d="M12 26h32v12a14 14 0 0 1-14 14h-4a14 14 0 0 1-14-14z" fill="{F}" {S}/><path d="M44 30h4a6 6 0 0 1 0 12h-6" {N}/><path d="M22 18c-2-3 2-5 0-8M30 18c-2-3 2-5 0-8M38 18c-2-3 2-5 0-8" {N} opacity=".6"/><path d="M8 58h40" {N}/>',
    'topf': f'<path d="M10 28h44v16a10 10 0 0 1-10 10H20a10 10 0 0 1-10-10z" fill="{F}" {S}/><path d="M6 28h52M24 22h16" {N}/><path d="M26 16c-2-3 2-5 0-8M38 16c-2-3 2-5 0-8" {N} opacity=".6"/>',
    'haken': f'<rect x="10" y="10" width="44" height="44" rx="12" fill="{F}" {S}/><path d="M21 33l8 8 15-17" {N} stroke-width="3"/>',
    'schritte': f'<path d="M18 50c-5 0-7-4-6-9 1-6 4-10 8-10s5 4 4 9c0 6-2 10-6 10z" fill="{F}" {S}/><path d="M42 34c-5 0-7-4-6-9 1-6 4-10 8-10s5 4 4 9c0 6-2 10-6 10z" fill="{B}" {S}/><circle cx="16" cy="56" r="2" fill="{P}"/><circle cx="40" cy="40" r="2" fill="{P}"/>',
    'feier': f'<path d="M10 54l10-30 20 20z" fill="{F}" {S}/><path d="M28 18c4-4 2-8 6-10M40 26c4-2 8 0 12-4M46 38c4 0 6 3 10 2" {N}/>' + spark(44, 12, 4, P) + spark(54, 30, 3),
    'welle': f'<path d="M4 26c8-8 16-8 24 0s16 8 24 0 8-4 8-4" {N}/><path d="M4 40c8-8 16-8 24 0s16 8 24 0 8-4 8-4" fill="none" stroke="{R}" stroke-width="2.6" stroke-linecap="round"/><circle cx="50" cy="12" r="5" fill="{F}" {S}/>',
    'kompass': f'<circle cx="32" cy="32" r="22" fill="{B}" {S}/><path d="M38 20l-4 14-8 10 4-14z" fill="{P}"/><path d="M38 20l-8 10 4 4z" fill="{F}"/><path d="M32 6v4M32 54v4M6 32h4M54 32h4" {N}/>',
    'spiegel': f'<ellipse cx="32" cy="26" rx="16" ry="20" fill="{B}" {S}/><path d="M32 46v12M22 58h20" {N}/><path d="M24 18a10 10 0 0 1 6-6" {N}/>' + spark(48, 40, 3.5, P),
    'schmetterling': f'<path d="M32 22c-4-10-14-14-22-12-2 10 4 18 16 20-8 2-12 10-8 16 6 2 12-2 14-10z" fill="{F}" {S}/><path d="M32 22c4-10 14-14 22-12 2 10-4 18-16 20 8 2 12 10 8 16-6 2-12-2-14-10z" fill="{B}" {S}/><path d="M32 20v28M32 20l-4-8M32 20l4-8" {N}/>',
    'nest': f'<path d="M8 34h48c0 12-10 20-24 20S8 46 8 34z" fill="{F}" {S}/><path d="M12 40c10 4 30 4 40 0M14 46c8 3 28 3 36 0" {N} opacity=".5"/><ellipse cx="24" cy="30" rx="6" ry="7" fill="{B}" {S}/><ellipse cx="38" cy="29" rx="6" ry="7" fill="#fff" {S}/>' + spark(50, 14, 4, P),
    'rakete': f'<path d="M32 6c10 8 12 20 8 34H24C20 26 22 14 32 6z" fill="{F}" {S}/><circle cx="32" cy="22" r="5" fill="{B}" {S}/><path d="M24 40l-8 8 2-14M40 40l8 8-2-14" fill="{B}" {S}/><path d="M28 46c0 4 2 8 4 12 2-4 4-8 4-12" fill="{R}" {S}/>',
    'umarmung': f'<circle cx="24" cy="18" r="7" fill="{F}" {S}/><circle cx="40" cy="18" r="7" fill="{B}" {S}/><path d="M10 54c0-12 6-20 14-20 4 0 6 2 8 4 2-2 4-4 8-4 8 0 14 8 14 20z" fill="{F}" {S}/><path d="M32 46c-4-3-6-5-6-7a3 3 0 0 1 6-1 3 3 0 0 1 6 1c0 2-2 4-6 7z" fill="{P}"/>',
    'schluessel': f'<circle cx="20" cy="32" r="12" fill="{F}" {S}/><circle cx="20" cy="32" r="4" fill="#fff" {S}/><path d="M32 32h24M48 32v8M54 32v6" {N}/>' + spark(46, 18, 3.5, P),
    # Haltungen für Übungen
    'p-vier': f'<circle cx="14" cy="24" r="5" fill="{F}" {S}/><path d="M20 26q14-8 28 0M20 27v20M48 27l2 20M24 47h-6M54 47h-6" {N}/><path d="M6 50h52" {N} opacity=".4"/>',
    'p-sitz': f'<circle cx="30" cy="12" r="5.5" fill="{F}" {S}/><path d="M30 18v18M30 24l-9 8M30 24l9 8M18 38h24M30 36l-6 10M30 36l8 10" {N}/><path d="M20 38v14M40 38v14" {N} opacity=".5"/>',
    'p-stand': f'<circle cx="32" cy="10" r="5.5" fill="{F}" {S}/><path d="M32 16v22M32 22l-10 10M32 22l10 10M32 38l-7 16M32 38l7 16" {N}/><path d="M14 56h36" {N} opacity=".4"/>',
    'p-lieg': f'<circle cx="12" cy="42" r="5" fill="{F}" {S}/><path d="M18 44h20l8-12 8 12M24 44l4-6" {N}/><path d="M4 50h56" {N} opacity=".4"/>',
    'p-seit': f'<circle cx="12" cy="36" r="5" fill="{F}" {S}/><path d="M18 40h22l-4-10M40 40l10-8M18 36l8-8" {N}/><path d="M4 46h56" {N} opacity=".4"/>',
    'p-band': f'<circle cx="22" cy="12" r="5.5" fill="{F}" {S}/><path d="M22 18v20M22 38l-6 16M22 38l6 16M22 24l12 6" {N}/><path d="M34 30q10 2 22 0" fill="none" stroke="{R}" stroke-width="3" stroke-linecap="round"/><path d="M56 14v42" {N} opacity=".5"/>',
    'p-hocke': f'<circle cx="32" cy="12" r="5.5" fill="{F}" {S}/><path d="M32 18v16M32 24l-8 8 8 4 8-4-8-8M32 34l-12 6 4 12M32 34l12 6-4 12" {N}/><path d="M12 54h40" {N} opacity=".4"/>',
    'p-haende': f'<path d="M18 52V30a4 4 0 0 1 8 0v-8a4 4 0 0 1 8 0v4a4 4 0 0 1 8 0v4a4 4 0 0 1 8 0v12c0 7-5 10-12 10H24" fill="{F}" {S}/>' + spark(10, 18, 4),
}


def icon(name, size=48):
    return f'<svg viewBox="0 0 64 64" width="{size}" height="{size}" aria-hidden="true">{ICONS.get(name, ICONS["stern"])}</svg>'


def pose(name):
    n = name.lower()
    for ic, keys in [('p-band', ['band']), ('p-hocke', ['malasana', 'hocke']), ('p-haende', ['massage', 'gegendruck', 'kiefer']),
                     ('p-seit', ['seitlage', 'clamshell', 'open book', 'seitstütz', 'aufstehen über']),
                     ('p-vier', ['katze', 'bird dog', 'vierfüßler', 'wiegen', 'kindhaltung']),
                     ('p-lieg', ['rücken', 'brücke', 'bridge', 'heel', 'toe', 'dead bug', 'beine an die wand', 'atmung', 'aktivierung', 'diastase', 'transversus', 'atem']),
                     ('p-sitz', ['sitzen', 'beckenboden finden', 'schütteln']),
                     ('p-stand', [])]:
        if any(k in n for k in keys):
            return ic
    return 'p-stand'
