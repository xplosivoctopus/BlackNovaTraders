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
// File: includes/addons.php

function bnt_addons_root(): string
{
    return dirname(__DIR__, 2) . '/addons';
}

function bnt_addon_slug_is_valid(string $slug): bool
{
    return (bool) preg_match('/^[a-z0-9][a-z0-9_-]{0,63}$/', $slug);
}

function bnt_addon_safe_relative_path(string $path): ?string
{
    $path = trim(str_replace('\\', '/', $path));
    if ($path === '' || strpos($path, '..') !== false || str_starts_with($path, '/')) {
        return null;
    }

    return $path;
}

function bnt_ensure_addons_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized || !isset($db)) {
        return;
    }

    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$db->prefix}addons (" .
        "addon_key varchar(64) NOT NULL," .
        "enabled enum('Y','N') NOT NULL default 'N'," .
        "updated_at datetime NULL," .
        "PRIMARY KEY (addon_key)" .
        ")"
    );

    $initialized = true;
}

function bnt_get_addon_state_map(): array
{
    global $db;

    bnt_ensure_addons_table();

    $stateMap = array();
    $result = $db->Execute("SELECT addon_key, enabled FROM {$db->prefix}addons");
    if (!$result) {
        return $stateMap;
    }

    while (!$result->EOF) {
        $row = $result->fields;
        $stateMap[(string) $row['addon_key']] = ((string) $row['enabled'] === 'Y');
        $result->MoveNext();
    }

    return $stateMap;
}

function bnt_default_addon_name(string $slug): string
{
    return ucwords(str_replace(array('-', '_'), ' ', $slug));
}

function bnt_read_addon_manifest(string $directory, array $stateMap): ?array
{
    $slug = basename($directory);
    if (!bnt_addon_slug_is_valid($slug)) {
        return null;
    }

    $manifestPath = $directory . '/addon.json';
    if (!is_file($manifestPath)) {
        return null;
    }

    $raw = @file_get_contents($manifestPath);
    if ($raw === false) {
        return array(
            'slug' => $slug,
            'name' => bnt_default_addon_name($slug),
            'version' => '0.0.0',
            'author' => 'Unknown',
            'description' => '',
            'directory' => $directory,
            'manifest_path' => $manifestPath,
            'bootstrap_rel' => null,
            'bootstrap_path' => null,
            'enabled' => !empty($stateMap[$slug]),
            'is_valid' => false,
            'warnings' => array('Unable to read addon.json.'),
        );
    }

    $manifest = json_decode($raw, true);
    if (!is_array($manifest)) {
        return array(
            'slug' => $slug,
            'name' => bnt_default_addon_name($slug),
            'version' => '0.0.0',
            'author' => 'Unknown',
            'description' => '',
            'directory' => $directory,
            'manifest_path' => $manifestPath,
            'bootstrap_rel' => null,
            'bootstrap_path' => null,
            'enabled' => !empty($stateMap[$slug]),
            'is_valid' => false,
            'warnings' => array('addon.json is not valid JSON.'),
        );
    }

    $warnings = array();
    $manifestSlug = trim((string) ($manifest['slug'] ?? $slug));
    if ($manifestSlug !== $slug) {
        $warnings[] = 'Manifest slug must match the folder name.';
    }

    $name = trim((string) ($manifest['name'] ?? ''));
    if ($name === '') {
        $name = bnt_default_addon_name($slug);
    }

    $description = trim((string) ($manifest['description'] ?? ''));
    $version = trim((string) ($manifest['version'] ?? '0.0.0'));
    $author = trim((string) ($manifest['author'] ?? 'Unknown'));
    $bootstrapRel = trim((string) ($manifest['bootstrap'] ?? 'bootstrap.php'));
    if ($bootstrapRel === '') {
        $bootstrapRel = 'bootstrap.php';
    }

    $bootstrapRel = bnt_addon_safe_relative_path($bootstrapRel);
    $bootstrapPath = null;
    if ($bootstrapRel === null) {
        $warnings[] = 'Bootstrap path is invalid.';
    } else {
        $bootstrapPath = $directory . '/' . $bootstrapRel;
        if (!is_file($bootstrapPath)) {
            $warnings[] = 'Bootstrap file is missing.';
        }
    }

    return array(
        'slug' => $slug,
        'name' => $name,
        'version' => $version,
        'author' => $author,
        'description' => $description,
        'directory' => $directory,
        'manifest_path' => $manifestPath,
        'bootstrap_rel' => $bootstrapRel,
        'bootstrap_path' => $bootstrapPath,
        'enabled' => !empty($stateMap[$slug]),
        'is_valid' => count($warnings) === 0,
        'warnings' => $warnings,
    );
}

function bnt_discover_addons(bool $refresh = false): array
{
    static $cache = null;

    if ($refresh || $cache === null) {
        $cache = array();
        $root = bnt_addons_root();
        if (!is_dir($root)) {
            return $cache;
        }

        $stateMap = bnt_get_addon_state_map();
        $entries = scandir($root);
        if (!is_array($entries)) {
            return $cache;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $directory = $root . '/' . $entry;
            if (!is_dir($directory)) {
                continue;
            }

            $addon = bnt_read_addon_manifest($directory, $stateMap);
            if ($addon === null) {
                continue;
            }

            $cache[$addon['slug']] = $addon;
        }

        uasort(
            $cache,
            static function (array $left, array $right): int {
                return strcasecmp($left['name'], $right['name']);
            }
        );
    }

    return $cache;
}

function bnt_set_addon_enabled(string $slug, bool $enabled): bool
{
    global $db;

    if (!bnt_addon_slug_is_valid($slug)) {
        return false;
    }

    bnt_ensure_addons_table();
    $state = $enabled ? 'Y' : 'N';
    $result = $db->Execute(
        "INSERT INTO {$db->prefix}addons (addon_key, enabled, updated_at) VALUES (?, ?, NOW()) " .
        "ON DUPLICATE KEY UPDATE enabled=VALUES(enabled), updated_at=VALUES(updated_at)",
        array($slug, $state)
    );

    return db_op_result($db, $result, __LINE__, __FILE__) === true;
}

function bnt_addon_register_hook(string $hook, callable $callback): void
{
    if (!isset($GLOBALS['bnt_addon_hooks']) || !is_array($GLOBALS['bnt_addon_hooks'])) {
        $GLOBALS['bnt_addon_hooks'] = array();
    }

    if (!isset($GLOBALS['bnt_addon_hooks'][$hook])) {
        $GLOBALS['bnt_addon_hooks'][$hook] = array();
    }

    $GLOBALS['bnt_addon_hooks'][$hook][] = array(
        'callback' => $callback,
        'slug' => $GLOBALS['bnt_current_addon']['slug'] ?? null,
    );
}

function bnt_addon_url(string $slug, string $view = 'index', array $params = array()): string
{
    $query = array_merge(
        array(
            'addon' => $slug,
            'view' => $view,
        ),
        $params
    );

    return 'addon.php?' . http_build_query($query);
}

function bnt_addon_register_page(string $view, callable $callback, array $options = array()): void
{
    $slug = $GLOBALS['bnt_current_addon']['slug'] ?? null;
    if (!is_string($slug) || $slug === '') {
        return;
    }

    if (!isset($GLOBALS['bnt_addon_pages']) || !is_array($GLOBALS['bnt_addon_pages'])) {
        $GLOBALS['bnt_addon_pages'] = array();
    }

    if (!isset($GLOBALS['bnt_addon_pages'][$slug])) {
        $GLOBALS['bnt_addon_pages'][$slug] = array();
    }

    $GLOBALS['bnt_addon_pages'][$slug][$view] = array(
        'callback' => $callback,
        'title' => (string) ($options['title'] ?? ($GLOBALS['bnt_current_addon']['name'] ?? 'Addon')),
        'requires_admin' => !empty($options['requires_admin']),
        'requires_login' => !array_key_exists('requires_login', $options) || (bool) $options['requires_login'],
        'label' => (string) ($options['label'] ?? ''),
    );
}

function bnt_get_addon_page(string $slug, string $view = 'index'): ?array
{
    $pages = $GLOBALS['bnt_addon_pages'][$slug] ?? null;
    if (!is_array($pages) || !isset($pages[$view])) {
        return null;
    }

    return $pages[$view];
}

function bnt_addon_register_nav_link(array $definition): void
{
    $slug = $GLOBALS['bnt_current_addon']['slug'] ?? null;
    if (!is_string($slug) || $slug === '') {
        return;
    }

    $scope = (string) ($definition['scope'] ?? 'player');
    if ($scope !== 'player' && $scope !== 'admin') {
        return;
    }

    $view = (string) ($definition['view'] ?? 'index');
    $label = trim((string) ($definition['label'] ?? ''));
    if ($label === '') {
        return;
    }

    if (!isset($GLOBALS['bnt_addon_nav_links']) || !is_array($GLOBALS['bnt_addon_nav_links'])) {
        $GLOBALS['bnt_addon_nav_links'] = array();
    }

    if (!isset($GLOBALS['bnt_addon_nav_links'][$scope])) {
        $GLOBALS['bnt_addon_nav_links'][$scope] = array();
    }

    $GLOBALS['bnt_addon_nav_links'][$scope][] = array(
        'slug' => $slug,
        'label' => $label,
        'view' => $view,
        'url' => (string) ($definition['url'] ?? bnt_addon_url($slug, $view, (array) ($definition['params'] ?? array()))),
        'order' => (int) ($definition['order'] ?? 100),
    );
}

function bnt_get_addon_nav_links(string $scope): array
{
    $links = $GLOBALS['bnt_addon_nav_links'][$scope] ?? array();
    if (!is_array($links)) {
        return array();
    }

    usort(
        $links,
        static function (array $left, array $right): int {
            $order = ($left['order'] ?? 100) <=> ($right['order'] ?? 100);
            if ($order !== 0) {
                return $order;
            }

            return strcasecmp((string) ($left['label'] ?? ''), (string) ($right['label'] ?? ''));
        }
    );

    return $links;
}

function bnt_render_addon_page(string $slug, string $view, array $context = array()): void
{
    $page = bnt_get_addon_page($slug, $view);
    if ($page === null) {
        throw new RuntimeException('Addon page not found.');
    }

    $callback = $page['callback'] ?? null;
    if (!is_callable($callback)) {
        throw new RuntimeException('Addon page is not callable.');
    }

    $result = call_user_func($callback, $context);
    if (is_string($result) && $result !== '') {
        echo $result;
    }
}

function bnt_render_addon_hook(string $hook, array $context = array()): void
{
    $hooks = $GLOBALS['bnt_addon_hooks'][$hook] ?? array();
    if (!is_array($hooks) || empty($hooks)) {
        return;
    }

    foreach ($hooks as $entry) {
        $callback = $entry['callback'] ?? null;
        if (!is_callable($callback)) {
            continue;
        }

        $payload = $context;
        if (!isset($payload['addon_slug']) && !empty($entry['slug'])) {
            $payload['addon_slug'] = $entry['slug'];
        }

        $result = call_user_func($callback, $payload);
        if (is_string($result) && $result !== '') {
            echo $result;
        }
    }
}

function bnt_get_enabled_addons(): array
{
    $enabled = array();
    foreach (bnt_discover_addons() as $slug => $addon) {
        if (!empty($addon['enabled']) && !empty($addon['is_valid'])) {
            $enabled[$slug] = $addon;
        }
    }

    return $enabled;
}

function bnt_load_enabled_addons(): void
{
    static $loaded = false;

    if ($loaded) {
        return;
    }

    if (!defined('BNT_ADDON_RUNTIME')) {
        define('BNT_ADDON_RUNTIME', true);
    }

    foreach (bnt_get_enabled_addons() as $addon) {
        $bootstrapPath = $addon['bootstrap_path'] ?? null;
        if (!is_string($bootstrapPath) || !is_file($bootstrapPath)) {
            continue;
        }

        $GLOBALS['bnt_current_addon'] = $addon;
        include_once $bootstrapPath;
        $GLOBALS['bnt_loaded_addons'][$addon['slug']] = $addon;
    }

    unset($GLOBALS['bnt_current_addon']);
    $loaded = true;
}
