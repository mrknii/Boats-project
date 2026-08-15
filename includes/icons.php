<?php
/**
 * ---------------------------------------------------------------------
 *  Inline SVG icon library
 * ---------------------------------------------------------------------
 *  Every icon is hand drawn on a 24x24 grid and rendered inline, so the
 *  system needs no icon font, no image files and no internet connection.
 *  Because the SVG is inline it inherits `currentColor`, which lets the
 *  stylesheet animate icons on hover exactly like any other element.
 *
 *  Usage:  echo icon('livestock', 20, 'icon--spin');
 * ---------------------------------------------------------------------
 */

function icon_library(): array
{
    return [

    // --- Navigation ---------------------------------------------------
    'dashboard' => '<rect x="3" y="3" width="7.5" height="8.5" rx="2"/><rect x="13.5" y="3" width="7.5" height="5.5" rx="2"/><rect x="13.5" y="11.5" width="7.5" height="9.5" rx="2"/><rect x="3" y="14.5" width="7.5" height="6.5" rx="2"/>',

    'livestock' => '<path d="M8 6.8C7.6 4.8 6 3.6 4.2 4.2"/><path d="M16 6.8c.4-2 2-3.2 3.8-2.6"/><path d="M6.2 8.2h11.6v3.6a5.8 5.8 0 0 1-11.6 0z"/><path d="M6.3 9.6c-1.6-1-3.3-.9-3.8.6-.4 1.4.8 2.5 2.6 2.4"/><path d="M17.7 9.6c1.6-1 3.3-.9 3.8.6.4 1.4-.8 2.5-2.6 2.4"/><ellipse cx="12" cy="15.2" rx="3.3" ry="2.3"/><path d="M10.7 15.1h.01M13.3 15.1h.01"/><path d="M9.4 10.6h.01M14.6 10.6h.01"/>',

    'crops' => '<path d="M12 21v-8.5"/><path d="M12 13c0-3.6 2.4-6.4 6.5-7-.2 4.4-2.6 7-6.5 7z"/><path d="M12 16c-3.6 0-6-2.2-6.4-5.8 3.8.4 6 2.6 6.4 5.8z"/><path d="M4 21h16"/>',

    'inventory' => '<path d="M20.5 8.2 12 3.5 3.5 8.2v7.6L12 20.5l8.5-4.7z"/><path d="M3.6 8.2 12 12.9l8.4-4.7"/><path d="M12 12.9v7.6"/><path d="M7.8 5.8 16.2 10.5"/>',

    'finance' => '<path d="M3.5 8.5A2.5 2.5 0 0 1 6 6h11.5A2.5 2.5 0 0 1 20 8.5v8a2.5 2.5 0 0 1-2.5 2.5H6a2.5 2.5 0 0 1-2.5-2.5z"/><path d="M3.5 9.5h13a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-13"/><path d="M15.5 12.5h.01"/><path d="M6 6V5.2A1.7 1.7 0 0 1 8.1 3.6l8.2 2.1"/>',

    'staff' => '<circle cx="9" cy="8" r="3.3"/><path d="M3.5 20a5.6 5.6 0 0 1 11 0"/><path d="M16.2 5.1a3.3 3.3 0 0 1 0 6.2"/><path d="M17.5 14.6a5.6 5.6 0 0 1 3 5.4"/>',

    'tasks' => '<path d="M9 4H7a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-2"/><rect x="9" y="2.5" width="6" height="3.5" rx="1.2"/><path d="m8.5 13 2.3 2.3 4.7-4.7"/>',

    'reports' => '<path d="M4 20h16"/><rect x="5" y="12" width="3.6" height="6" rx="1.2"/><rect x="10.2" y="8" width="3.6" height="10" rx="1.2"/><rect x="15.4" y="4.5" width="3.6" height="13.5" rx="1.2"/>',

    'settings' => '<circle cx="12" cy="12" r="3.1"/><path d="M12 2.8l1.5 2.3 2.7-.5.5 2.7 2.3 1.5-1.4 2.3 1.4 2.3-2.3 1.5-.5 2.7-2.7-.5-1.5 2.3-1.5-2.3-2.7.5-.5-2.7-2.3-1.5L4.4 12 3 9.7l2.3-1.5.5-2.7 2.7.5z"/>',

    'fields' => '<path d="M12 3.2 21 8v8l-9 4.8L3 16V8z"/><path d="M3 8.3 12 13l9-4.7"/><path d="M7.5 5.6v9.6"/><path d="M16.5 5.6v9.6"/>',

    'suppliers' => '<path d="M2.5 7.5A1.5 1.5 0 0 1 4 6h8.5v9.5H2.5z"/><path d="M12.5 10H17l3 3v2.5h-7.5z"/><circle cx="6.5" cy="17.5" r="2"/><circle cx="16.5" cy="17.5" r="2"/><path d="M8.5 17.5h6"/>',

    'health' => '<path d="M12 20.3S3.8 15.7 3.8 9.8A4.6 4.6 0 0 1 12 7a4.6 4.6 0 0 1 8.2 2.8c0 5.9-8.2 10.5-8.2 10.5z"/><path d="M4.5 13h3l1.5-2.6L11 15l2-6.4 1.7 4.4H19"/>',

    'production' => '<path d="M8 3h8l-.6 3.4a4 4 0 0 0 .5 2.7l1.2 2a4 4 0 0 1 .6 2.1V19a2 2 0 0 1-2 2H8.3a2 2 0 0 1-2-2v-5.8a4 4 0 0 1 .6-2.1l1.2-2a4 4 0 0 0 .5-2.7z"/><path d="M6.6 13.5h10.8"/>',

    'harvest' => '<path d="M4.5 20.5 12 13"/><path d="M12 13c-1.8-1.8-1.8-4.6 0-6.4 1.8 1.8 1.8 4.6 0 6.4z"/><path d="M12 13c1.8-1.8 4.6-1.8 6.4 0-1.8 1.8-4.6 1.8-6.4 0z"/><path d="M12 6.6V3.5"/>',

    'activity' => '<path d="M3.5 12.5h4l2.2-6 4 12.5 2.4-8.2 1.6 3.2h4.3"/>',

    // --- Livestock species --------------------------------------------
    'goat' => '<path d="M8.8 6.6C7.5 4.6 5.6 3.8 4 4.6"/><path d="M15.2 6.6c1.3-2 3.2-2.8 4.8-2"/><path d="M8 7.6h8v4.4c0 2.6-1.6 4.2-4 4.2s-4-1.6-4-4.2z"/><path d="M8 9.8 5.2 9"/><path d="M16 9.8 18.8 9"/><path d="M9.8 10.4h.01M14.2 10.4h.01"/><path d="M12 16.2v2.6"/>',

    'sheep' => '<path d="M7.2 9.4a2.6 2.6 0 0 1 .5-4.7A3 3 0 0 1 12 3.5a3 3 0 0 1 4.3 1.2 2.6 2.6 0 0 1 .5 4.7"/><path d="M9 9.2h6v3a3 3 0 0 1-6 0z"/><path d="M9 10.2 6.4 11.2"/><path d="M15 10.2l2.6 1"/><path d="M10.4 11h.01M13.6 11h.01"/><path d="M8 15.4a4 4 0 0 0 4 4 4 4 0 0 0 4-4"/>',

    'poultry' => '<path d="M10.2 6.4c0-1.1.9-1.7 1.5-1 .6-1 1.7-1 2.3 0 .8-.5 1.5.1 1.5 1.1"/><circle cx="13.2" cy="9.2" r="3.1"/><path d="m16.3 9.7 2.9.9-2.7 1.3"/><path d="M14 12.3c0 1.2-.6 1.9-1.5 1.9"/><path d="M10.7 11.8C7.6 12.7 5.2 14.9 5.2 17.7c0 1.4 1 2.5 2.5 2.5h6.8c2 0 3.6-1.7 3.6-3.7 0-1.4-.6-2.7-1.6-3.5"/><path d="M9.6 20.2v1.4M13.6 20.2v1.4"/><path d="M12.6 8.6h.01"/>',

    'pig' => '<path d="M7.2 7.6 5.4 4.6l3.4 1"/><path d="M16.8 7.6l1.8-3-3.4 1"/><path d="M5.6 11.2a6.4 6.4 0 0 1 12.8 0v1.2a6.4 6.4 0 0 1-6.4 6.4 6.4 6.4 0 0 1-6.4-6.4z"/><ellipse cx="12" cy="13.4" rx="3" ry="2.2"/><path d="M10.9 13.3h.01M13.1 13.3h.01"/><path d="M9 10h.01M15 10h.01"/>',

    // --- Actions ------------------------------------------------------
    'plus'        => '<path d="M12 5v14M5 12h14"/>',
    'edit'        => '<path d="M4 20h4.3L19.4 8.9a2.1 2.1 0 0 0 0-3l-1.3-1.3a2.1 2.1 0 0 0-3 0L4 15.7z"/><path d="M14.5 5.6 18.4 9.5"/>',
    'trash'       => '<path d="M4 7h16"/><path d="M10 4.5h4a1 1 0 0 1 1 1V7H9V5.5a1 1 0 0 1 1-1z"/><path d="M6 7v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V7"/><path d="M10.5 11v6M13.5 11v6"/>',
    'eye'         => '<path d="M2.5 12S6 5.8 12 5.8 21.5 12 21.5 12 18 18.2 12 18.2 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'search'      => '<circle cx="11" cy="11" r="6.6"/><path d="m16 16 4.5 4.5"/>',
    'filter'      => '<path d="M3.5 5.5h17l-6.6 7.6v5.6l-3.8 2v-7.6z"/>',
    'download'    => '<path d="M12 3.5v11"/><path d="m7.5 10.5 4.5 4.5 4.5-4.5"/><path d="M4 17v1.5a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V17"/>',
    'print'       => '<path d="M7 8.5V3.5h10v5"/><rect x="3.5" y="8.5" width="17" height="7.5" rx="2"/><path d="M7 13.5h10V21H7z"/><path d="M17.2 11.5h.01"/>',
    'refresh'     => '<path d="M20.5 12a8.5 8.5 0 1 1-2.6-6.1"/><path d="M20.8 4.2v4.6h-4.6"/>',
    'close'       => '<path d="M6 6 18 18M18 6 6 18"/>',
    'check'       => '<path d="m4.5 12.5 5 5 10-11"/>',
    'menu'        => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'more'        => '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
    'logout'      => '<path d="M9.5 20H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h3.5"/><path d="M15.5 8.5 19 12l-3.5 3.5"/><path d="M19 12H9.5"/>',
    'login'       => '<path d="M14.5 4H18a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3.5"/><path d="M8.5 8.5 5 12l3.5 3.5"/><path d="M5 12h9.5"/>',
    'save'        => '<path d="M5 4h11l3 3v13a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1z"/><path d="M8 4v5h7V4"/><rect x="8" y="13" width="8" height="7"/>',

    // --- Arrows -------------------------------------------------------
    'chevron-left'  => '<path d="m14.5 5.5-7 6.5 7 6.5"/>',
    'chevron-right' => '<path d="m9.5 5.5 7 6.5-7 6.5"/>',
    'chevron-down'  => '<path d="m5.5 9.5 6.5 6 6.5-6"/>',
    'chevron-up'    => '<path d="m5.5 14.5 6.5-6 6.5 6"/>',
    'arrow-right'   => '<path d="M4 12h15"/><path d="m13.5 6.5 5.5 5.5-5.5 5.5"/>',
    'arrow-up'      => '<path d="M12 19.5V5"/><path d="m6.5 10.5 5.5-5.5 5.5 5.5"/>',
    'arrow-down'    => '<path d="M12 4.5V19"/><path d="m6.5 13.5 5.5 5.5 5.5-5.5"/>',
    'trend-up'      => '<path d="m3.5 16.5 5.5-5.5 3.5 3.5 6-6"/><path d="M14.5 8.5h4v4"/>',
    'trend-down'    => '<path d="m3.5 7.5 5.5 5.5 3.5-3.5 6 6"/><path d="M14.5 15.5h4v-4"/>',

    // --- Status / feedback -------------------------------------------
    'success' => '<circle cx="12" cy="12" r="8.8"/><path d="m8 12.3 2.7 2.7 5.3-5.6"/>',
    'warning' => '<path d="M12 3.6 21 19.4H3z"/><path d="M12 9.5v4.2"/><path d="M12 17h.01"/>',
    'danger'  => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.6v5"/><path d="M12 16.2h.01"/>',
    'info'    => '<circle cx="12" cy="12" r="8.8"/><path d="M12 11.2v5"/><path d="M12 7.9h.01"/>',
    'bell'    => '<path d="M18 9a6 6 0 1 0-12 0c0 5-2 6.5-2 6.5h16S18 14 18 9z"/><path d="M13.8 19a2.1 2.1 0 0 1-3.6 0"/>',
    'clock'   => '<circle cx="12" cy="12" r="8.8"/><path d="M12 7.2V12l3.2 2"/>',
    'calendar'=> '<rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M3.5 10h17"/><path d="M8 3v4M16 3v4"/><path d="M8 14h2M14 14h2M8 17.5h2M14 17.5h2"/>',
    'shield'  => '<path d="M12 3.2 19.5 6v5.6c0 4.4-3.1 8.1-7.5 9.2-4.4-1.1-7.5-4.8-7.5-9.2V6z"/><path d="m9 12 2.2 2.2L15.2 10"/>',
    'lock'    => '<rect x="4.5" y="10" width="15" height="10.5" rx="2.5"/><path d="M8 10V7.5a4 4 0 0 1 8 0V10"/><path d="M12 14v2.5"/>',
    'sparkle' => '<path d="m12 3 1.9 5.4L19.5 10l-5.6 1.6L12 17l-1.9-5.4L4.5 10l5.6-1.6z"/><path d="M18.5 16.5 19.3 19l2.2.8-2.2.8-.8 2.2-.8-2.2-2.2-.8 2.2-.8z" opacity=".6"/>',

    // --- Objects ------------------------------------------------------
    'user'    => '<circle cx="12" cy="8" r="3.6"/><path d="M4.8 20.2a7.2 7.2 0 0 1 14.4 0"/>',
    'mail'    => '<rect x="3" y="5.5" width="18" height="13" rx="2.5"/><path d="m3.6 7.5 8.4 5.5 8.4-5.5"/>',
    'phone'   => '<path d="M7.5 3.5h-2A2.5 2.5 0 0 0 3 6.3C3 13.8 10.2 21 17.7 21a2.5 2.5 0 0 0 2.8-2.5v-2l-4-1.6-1.9 2.2a13.8 13.8 0 0 1-5.7-5.7L11.1 9z"/>',
    'pin'     => '<path d="M12 21s6.8-6.2 6.8-11a6.8 6.8 0 0 0-13.6 0C5.2 14.8 12 21 12 21z"/><circle cx="12" cy="10" r="2.6"/>',
    'box'     => '<rect x="3.5" y="7" width="17" height="13" rx="2"/><path d="M3.5 11h17"/><path d="M8.5 7V4.5h7V7"/>',
    'tag'     => '<path d="M11 3.5H4.5A1 1 0 0 0 3.5 4.5V11l9 9a1.6 1.6 0 0 0 2.3 0l5.2-5.2a1.6 1.6 0 0 0 0-2.3z"/><circle cx="7.8" cy="7.8" r="1.4"/>',
    'weight'  => '<path d="M5.5 7h13l2 13.5H3.5z"/><circle cx="12" cy="5.5" r="2.2"/><path d="M9.5 12h5"/>',
    'drop'    => '<path d="M12 3.5c3.4 3.7 6 6.8 6 9.8a6 6 0 0 1-12 0c0-3 2.6-6.1 6-9.8z"/>',
    'fuel'    => '<path d="M4.5 20.5V5a2 2 0 0 1 2-2h5a2 2 0 0 1 2 2v15.5"/><path d="M3.5 20.5h11"/><path d="M13.5 9h3a2 2 0 0 1 2 2v5.5a1.6 1.6 0 0 0 3.2 0V10L19 7"/><path d="M6.8 7.5h4.4"/>',
    'tool'    => '<path d="M14.2 6.4a4 4 0 0 1 5.4 5.2l-9 9a2.3 2.3 0 0 1-3.3-3.2z"/><path d="M9.5 4.5 6.8 3 4 5.8l1.5 2.7 3 .6z"/><path d="m8.5 9.1 4-2.7"/>',
    'seed'    => '<path d="M18.5 5.5C18.5 12 14.5 16 8 16 8 9.5 12 5.5 18.5 5.5z"/><path d="M4.5 20 12 12"/>',
    'medical' => '<rect x="3.5" y="7" width="17" height="12.5" rx="2.5"/><path d="M9 7V5.2a1.7 1.7 0 0 1 1.7-1.7h2.6A1.7 1.7 0 0 1 15 5.2V7"/><path d="M12 10.6v5.4M9.3 13.3h5.4"/>',
    'egg'     => '<path d="M12 3.5c3.3 0 5.8 5.2 5.8 9.2a5.8 5.8 0 0 1-11.6 0c0-4 2.5-9.2 5.8-9.2z"/>',
    'leaf'    => '<path d="M4.5 19.5C3 12 8 4.5 19.5 4.5 19.5 15 13 20 6.5 17.5"/><path d="M6.5 17.5c1.5-4.5 4.5-7.5 9-9"/>',
    'chart-pie' => '<path d="M12 3.5V12h8.5A8.5 8.5 0 0 0 12 3.5z"/><path d="M20.2 14.5A8.5 8.5 0 1 1 9.5 3.8"/>',
    'wallet'  => '<rect x="3.5" y="6" width="17" height="13" rx="2.5"/><path d="M16.5 11.5h4v4h-4a2 2 0 0 1 0-4z"/><path d="M3.5 9.5h13"/>',
    'sun'     => '<circle cx="12" cy="12" r="4.2"/><path d="M12 2.5v2.2M12 19.3v2.2M4.2 4.2l1.6 1.6M18.2 18.2l1.6 1.6M2.5 12h2.2M19.3 12h2.2M4.2 19.8l1.6-1.6M18.2 5.8l1.6-1.6"/>',
    'moon'    => '<path d="M20 14.5A8.5 8.5 0 0 1 9.5 4a8.5 8.5 0 1 0 10.5 10.5z"/>',
    'grid'    => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.6"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.6"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.6"/>',
    'list'    => '<path d="M8.5 6.5h12M8.5 12h12M8.5 17.5h12"/><path d="M4 6.5h.01M4 12h.01M4 17.5h.01"/>',
    'home'    => '<path d="M4 10.5 12 4l8 6.5V19a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2z"/><path d="M9.5 21v-6h5v6"/>',

    ];
}

/**
 * Render an inline SVG icon.
 *
 * @param string $name  Key from icon_library()
 * @param int    $size  Pixel size (width and height)
 * @param string $class Extra CSS classes
 */
function icon(string $name, int $size = 20, string $class = ''): string
{
    static $library = null;
    $library ??= icon_library();

    // Unknown names fall back to a neutral dot rather than breaking layout
    $paths = $library[$name] ?? '<circle cx="12" cy="12" r="3.5"/>';

    return sprintf(
        '<svg class="icon %s" width="%d" height="%d" viewBox="0 0 24 24" fill="none" '
        . 'stroke="currentColor" stroke-width="1.7" stroke-linecap="round" '
        . 'stroke-linejoin="round" aria-hidden="true" focusable="false">%s</svg>',
        e($class), $size, $size, $paths
    );
}

/** Map a livestock/inventory category icon name onto the library. */
function category_icon(string $stored): string
{
    $map = [
        'cow' => 'livestock', 'goat' => 'goat', 'sheep' => 'sheep',
        'poultry' => 'poultry', 'pig' => 'pig',
        'feed' => 'production', 'seed' => 'seed', 'fertilizer' => 'drop',
        'spray' => 'drop', 'medical' => 'medical', 'tool' => 'tool',
        'fuel' => 'fuel', 'box' => 'box',
    ];
    return $map[$stored] ?? 'box';
}
