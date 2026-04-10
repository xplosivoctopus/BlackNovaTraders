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
// File: includes/log_helpers.php

function bnt_log_type_list(): array
{
    static $types = array(
        null,
        'LOG_LOGIN', 'LOG_LOGOUT', 'LOG_ATTACK_OUTMAN', 'LOG_ATTACK_OUTSCAN', 'LOG_ATTACK_EWD', 'LOG_ATTACK_EWDFAIL', 'LOG_ATTACK_LOSE', 'LOG_ATTACKED_WIN', 'LOG_TOLL_PAID', 'LOG_HIT_MINES',
        'LOG_SHIP_DESTROYED_MINES', 'LOG_PLANET_DEFEATED_D', 'LOG_PLANET_DEFEATED', 'LOG_PLANET_NOT_DEFEATED', 'LOG_RAW', 'LOG_TOLL_RECV', 'LOG_DEFS_DESTROYED', 'LOG_PLANET_EJECT', 'LOG_BADLOGIN', 'LOG_PLANET_SCAN',
        'LOG_PLANET_SCAN_FAIL', 'LOG_PLANET_CAPTURE', 'LOG_SHIP_SCAN', 'LOG_SHIP_SCAN_FAIL', 'LOG_Xenobe_ATTACK', 'LOG_STARVATION', 'LOG_TOW', 'LOG_DEFS_DESTROYED_F', 'LOG_DEFS_KABOOM', 'LOG_HARAKIRI',
        'LOG_TEAM_REJECT', 'LOG_TEAM_RENAME', 'LOG_TEAM_M_RENAME', 'LOG_TEAM_KICK', 'LOG_TEAM_CREATE', 'LOG_TEAM_LEAVE', 'LOG_TEAM_NEWLEAD', 'LOG_TEAM_LEAD', 'LOG_TEAM_JOIN', 'LOG_TEAM_NEWMEMBER',
        'LOG_TEAM_INVITE', 'LOG_TEAM_NOT_LEAVE', 'LOG_ADMIN_HARAKIRI', 'LOG_ADMIN_PLANETDEL', 'LOG_DEFENCE_DEGRADE', 'LOG_PLANET_CAPTURED', 'LOG_BOUNTY_CLAIMED', 'LOG_BOUNTY_PAID', 'LOG_BOUNTY_CANCELLED', 'LOG_SPACE_PLAGUE',
        'LOG_PLASMA_STORM', 'LOG_BOUNTY_FEDBOUNTY', 'LOG_PLANET_BOMBED', 'LOG_ADMIN_ILLEGVALUE'
    );

    return $types;
}

function bnt_get_log_info($id = null, &$title = null, &$text = null): void
{
    $title = null;
    $text = null;
    $logTypes = bnt_log_type_list();

    if (!is_numeric($id) || (int) $id < 0 || (int) $id >= count($logTypes)) {
        return;
    }

    $logKey = $logTypes[(int) $id];
    if ($logKey === null) {
        return;
    }

    $titleKey = 'l_log_title_' . $logKey;
    $textKey = 'l_log_text_' . $logKey;

    if (array_key_exists($titleKey, $GLOBALS)) {
        $title = (string) $GLOBALS[$titleKey];
    }

    if (array_key_exists($textKey, $GLOBALS)) {
        $text = (string) $GLOBALS[$textKey];
    }
}

function bnt_render_log_text_template(string $template, string $data): string
{
    $parts = ($data === '') ? array() : explode('|', $data);
    $index = 0;

    return preg_replace_callback(
        '/\[[a-z0-9_]+\]/i',
        static function () use (&$parts, &$index): string {
            if (!array_key_exists($index, $parts)) {
                return '';
            }

            return trim((string) $parts[$index++]);
        },
        $template
    ) ?? $template;
}

function bnt_format_log_entry_plain(array $entry): array
{
    $title = null;
    $text = null;
    bnt_get_log_info($entry['type'] ?? null, $title, $text);

    $title = trim((string) $title);
    $text = trim((string) $text);
    $data = trim((string) ($entry['data'] ?? ''));

    if ($title === '') {
        $title = 'Ship Activity';
    }

    if ($text !== '') {
        $text = bnt_render_log_text_template($text, $data);
    } else {
        $text = $data;
    }

    $text = preg_replace('/\s+/', ' ', trim(strip_tags($text))) ?? $text;
    if ($text === '') {
        $text = 'System activity recorded.';
    }

    return array(
        'title' => $title,
        'text' => $text,
    );
}

