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
// File: includes/motd.php

function bnt_ensure_motd_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$db->prefix}motd (" .
        "motd_id tinyint unsigned NOT NULL," .
        "headline varchar(150) NOT NULL default ''," .
        "body text NOT NULL," .
        "is_active enum('Y','N') NOT NULL default 'N'," .
        "updated_at datetime NULL," .
        "updated_by int NULL," .
        "PRIMARY KEY (motd_id)" .
        ")"
    );

    $initialized = true;
}

function bnt_get_motd(): array
{
    global $db;

    bnt_ensure_motd_table();

    $result = $db->Execute("SELECT * FROM {$db->prefix}motd WHERE motd_id=1 LIMIT 1");
    if (!$result || $result->EOF) {
        return [
            'motd_id' => 1,
            'headline' => '',
            'body' => '',
            'is_active' => 'N',
            'updated_at' => null,
            'updated_by' => null,
        ];
    }

    return $result->fields;
}

function bnt_save_motd(string $headline, string $body, bool $isActive, ?int $updatedBy = null): void
{
    global $db;

    bnt_ensure_motd_table();

    $existing = $db->Execute("SELECT motd_id FROM {$db->prefix}motd WHERE motd_id=1 LIMIT 1");
    $activeValue = $isActive ? 'Y' : 'N';

    if ($existing && !$existing->EOF) {
        $db->Execute(
            "UPDATE {$db->prefix}motd SET headline=?, body=?, is_active=?, updated_at=NOW(), updated_by=? WHERE motd_id=1",
            array($headline, $body, $activeValue, $updatedBy)
        );
        return;
    }

    $db->Execute(
        "INSERT INTO {$db->prefix}motd (motd_id, headline, body, is_active, updated_at, updated_by) VALUES (1, ?, ?, ?, NOW(), ?)",
        array($headline, $body, $activeValue, $updatedBy)
    );
}

function bnt_render_motd(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    if (empty($_SESSION['logged_in'])) {
        return;
    }

    $motd = bnt_get_motd();
    if (($motd['is_active'] ?? 'N') !== 'Y') {
        return;
    }

    $headline = trim((string) ($motd['headline'] ?? ''));
    $body = trim((string) ($motd['body'] ?? ''));
    if ($headline === '' && $body === '') {
        return;
    }

    echo "<section class='bnt-motd' role='status' aria-label='Message of the day'>";
    echo "<div class='bnt-motd__inner'>";
    echo "<div class='bnt-motd__eyebrow'>MESSAGE OF THE DAY</div>";
    if ($headline !== '') {
        echo "<h2 class='bnt-motd__title'>" . htmlspecialchars($headline, ENT_QUOTES, 'UTF-8') . "</h2>";
    }
    if ($body !== '') {
        echo "<div class='bnt-motd__body'>" . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . "</div>";
    }
    echo "</div>";
    echo "</section>";
}

