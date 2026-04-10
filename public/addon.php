<?php
// Blacknova Traders - A web-based massively multiplayer space combat and trading game
// Copyright (C) 2001-2012 Ron Harwood and the BNT development team
//
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
//  This program is distributed in the hope that it will be useful,
//  but WITHOUT ANY WARRANTY; without even the implied warranty of
//  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//  GNU Affero General Public License for more details.
//
//  You should have received a copy of the GNU Affero General Public License
//  along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
// File: addon.php

include 'config/config.php';

$addonSlug = trim((string) ($_GET['addon'] ?? ''));
$addonView = trim((string) ($_GET['view'] ?? 'index'));

if (!bnt_addon_slug_is_valid($addonSlug)) {
    bnt_render_plain_error_page(404, 'Addon Not Found', 'The requested addon could not be found.');
    exit;
}

$addons = bnt_discover_addons(true);
$addon = $addons[$addonSlug] ?? null;
if (!$addon || empty($addon['enabled']) || empty($addon['is_valid'])) {
    bnt_render_plain_error_page(404, 'Addon Not Found', 'That addon is not installed or enabled.');
    exit;
}

load_languages($db, $lang, array('common', 'global_includes', 'footer', 'news'), $langvars, $db_logging);

$page = bnt_get_addon_page($addonSlug, $addonView);
if ($page === null) {
    bnt_render_plain_error_page(404, 'Addon Page Not Found', 'That addon page does not exist.');
    exit;
}

if (!empty($page['requires_admin'])) {
    bnt_require_admin();
} elseif (!empty($page['requires_login']) && checklogin()) {
    exit;
}

$title = $page['title'] ?: $addon['name'];
include 'header.php';

echo "<div style='width:min(1100px, calc(100% - 24px)); margin:16px auto 0; display:flex; flex-wrap:wrap; gap:10px; align-items:center;'>";
echo "<a class='bnt-nav-button' href='main.php'>&lt; Cockpit</a>";
echo "<a class='bnt-nav-button' href='admin.php?menu=addons'>&lt; Addons</a>";
if (!empty($page['requires_admin'])) {
    echo "<a class='bnt-nav-button' href='admin.php'>&lt; Admin</a>";
}
echo "</div>";

echo "<div style='width:min(1100px, calc(100% - 24px)); margin:16px auto 28px;'>";
try {
    bnt_render_addon_page(
        $addonSlug,
        $addonView,
        array(
            'addon' => $addon,
            'view' => $addonView,
            'playerinfo' => bnt_get_current_playerinfo(),
        )
    );
} catch (Throwable $exception) {
    echo "<div style='border-left:3px solid #ff3355; background:rgba(255,51,85,0.08); color:#ff95a8; padding:12px 14px;'>Addon page failed to load: "
        . htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8')
        . "</div>";
}
echo bnt_nav_button_html('main.php', '< Cockpit');
echo "</div>";

include 'footer.php';
