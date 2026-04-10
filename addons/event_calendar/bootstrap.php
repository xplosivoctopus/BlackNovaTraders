<?php

if (!defined('BNT_ADDON_RUNTIME')) {
    return;
}

include_once __DIR__ . '/lib.php';

bnt_event_calendar_ensure_table();

bnt_addon_register_page(
    'index',
    static function (): void {
        bnt_event_calendar_render_player_page();
    },
    array(
        'title' => 'Event Calendar',
        'label' => 'Event Calendar',
    )
);

bnt_addon_register_page(
    'admin',
    static function (): void {
        bnt_event_calendar_render_admin_page();
    },
    array(
        'title' => 'Event Calendar Control',
        'label' => 'Live Ops Control',
        'requires_admin' => true,
    )
);

bnt_addon_register_nav_link(
    array(
        'scope' => 'player',
        'label' => 'Event Calendar',
        'view' => 'index',
        'order' => 10,
    )
);

bnt_addon_register_nav_link(
    array(
        'scope' => 'admin',
        'label' => 'Live Ops Control',
        'view' => 'admin',
        'order' => 10,
    )
);

bnt_addon_register_hook(
    'page_top',
    static function (): string {
        return bnt_event_calendar_render_banner();
    }
);
