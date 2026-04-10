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
// File: includes/notifications.php

function bnt_ensure_notifications_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$db->prefix}notification_state (" .
        "ship_id int unsigned NOT NULL," .
        "last_viewed_at datetime NULL," .
        "PRIMARY KEY (ship_id)" .
        ")"
    );

    $initialized = true;
}

function bnt_get_notification_state(int $shipId): array
{
    global $db;

    bnt_ensure_notifications_table();

    $result = $db->Execute(
        "SELECT ship_id, last_viewed_at FROM {$db->prefix}notification_state WHERE ship_id=? LIMIT 1",
        array($shipId)
    );

    if (!$result || $result->EOF) {
        return array(
            'ship_id' => $shipId,
            'last_viewed_at' => null,
        );
    }

    return $result->fields;
}

function bnt_mark_notifications_viewed(int $shipId): void
{
    global $db;

    bnt_ensure_notifications_table();

    $db->Execute(
        "INSERT INTO {$db->prefix}notification_state (ship_id, last_viewed_at) VALUES (?, UTC_TIMESTAMP()) " .
        "ON DUPLICATE KEY UPDATE last_viewed_at=VALUES(last_viewed_at)",
        array($shipId)
    );

    $db->Execute("UPDATE {$db->prefix}messages SET notified='Y' WHERE recp_id=?", array($shipId));
}

function bnt_get_notification_counts(int $shipId): array
{
    global $db;

    bnt_ensure_messages_schema();
    $state = bnt_get_notification_state($shipId);
    $lastViewedAt = $state['last_viewed_at'] ?? null;

    $messageResult = $db->Execute(
        "SELECT COUNT(*) AS total FROM {$db->prefix}messages WHERE recp_id=? AND notified='N'",
        array($shipId)
    );
    $unreadMessages = ($messageResult && !$messageResult->EOF) ? (int) $messageResult->fields['total'] : 0;

    if (!empty($lastViewedAt)) {
        $logResult = $db->Execute(
            "SELECT COUNT(*) AS total FROM {$db->prefix}logs WHERE ship_id=? AND time > ?",
            array($shipId, $lastViewedAt)
        );
    } else {
        $logResult = $db->Execute(
            "SELECT COUNT(*) AS total FROM {$db->prefix}logs WHERE ship_id=?",
            array($shipId)
        );
    }

    $unreadLogs = ($logResult && !$logResult->EOF) ? (int) $logResult->fields['total'] : 0;

    return array(
        'messages' => $unreadMessages,
        'activity' => $unreadLogs,
        'total' => $unreadMessages + $unreadLogs,
        'last_viewed_at' => $lastViewedAt,
    );
}

function bnt_get_recent_notifications(int $shipId, int $limit = 40, string $filter = 'all'): array
{
    global $db;

    bnt_ensure_messages_schema();
    $limit = max(1, min(100, $limit));
    $counts = bnt_get_notification_counts($shipId);
    $lastViewedAt = $counts['last_viewed_at'] ?? null;
    $items = array();

    if ($filter === 'all' || $filter === 'messages' || $filter === 'unread') {
        $sql = "SELECT m.ID, m.sender_id, m.subject, m.message, m.sent, m.notified, s.character_name, s.ship_name
                FROM {$db->prefix}messages m
                LEFT JOIN {$db->prefix}ships s ON s.ship_id = m.sender_id
                WHERE m.recp_id=?";
        $params = array($shipId);
        if ($filter === 'unread') {
            $sql .= " AND m.notified='N'";
        }
        $sql .= " ORDER BY m.sent DESC LIMIT {$limit}";
        $result = $db->Execute($sql, $params);
        if ($result) {
            while (!$result->EOF) {
                $row = $result->fields;
                $items[] = array(
                    'source' => 'message',
                    'time' => (string) $row['sent'],
                    'is_new' => ((string) $row['notified'] === 'N'),
                    'title' => trim((string) ($row['subject'] ?? '')) !== '' ? (string) $row['subject'] : 'Message received',
                    'summary' => trim((string) ($row['message'] ?? '')),
                    'meta' => 'From ' . trim((string) ($row['character_name'] ?? 'Unknown sender')),
                    'url' => 'readmail.php',
                );
                $result->MoveNext();
            }
        }
    }

    if ($filter === 'all' || $filter === 'activity' || $filter === 'unread') {
        $sql = "SELECT log_id, type, time, data FROM {$db->prefix}logs WHERE ship_id=?";
        $params = array($shipId);
        if ($filter === 'unread') {
            if (!empty($lastViewedAt)) {
                $sql .= " AND time > ?";
                $params[] = $lastViewedAt;
            }
        }
        $sql .= " ORDER BY time DESC LIMIT {$limit}";

        $result = $db->Execute($sql, $params);
        if ($result) {
            while (!$result->EOF) {
                $row = $result->fields;
                $formatted = bnt_format_log_entry_plain($row);
                $isNew = empty($lastViewedAt) ? true : ((string) $row['time'] > (string) $lastViewedAt);
                $items[] = array(
                    'source' => 'activity',
                    'time' => (string) $row['time'],
                    'is_new' => $isNew,
                    'title' => $formatted['title'],
                    'summary' => $formatted['text'],
                    'meta' => 'Captain log entry',
                    'url' => 'log.php',
                );
                $result->MoveNext();
            }
        }
    }

    usort(
        $items,
        static function (array $left, array $right): int {
            return strcmp((string) ($right['time'] ?? ''), (string) ($left['time'] ?? ''));
        }
    );

    return array_slice($items, 0, $limit);
}
