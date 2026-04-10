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
// File: includes/mailbox.php

function bnt_ensure_messages_schema(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $table = $db->prefix . 'messages';
    $columns = array();
    $result = $db->Execute("SHOW COLUMNS FROM {$table}");
    if ($result) {
        while (!$result->EOF) {
            $columns[(string) $result->fields['Field']] = true;
            $result->MoveNext();
        }
    }

    if (!isset($columns['thread_key'])) {
        $db->Execute("ALTER TABLE {$table} ADD COLUMN thread_key varchar(64) NOT NULL default '' AFTER recp_id");
    }
    if (!isset($columns['reply_to_id'])) {
        $db->Execute("ALTER TABLE {$table} ADD COLUMN reply_to_id int NULL AFTER thread_key");
    }
    if (!isset($columns['read_at'])) {
        $db->Execute("ALTER TABLE {$table} ADD COLUMN read_at datetime NULL AFTER notified");
    }
    if (!isset($columns['last_reply_at'])) {
        $db->Execute("ALTER TABLE {$table} ADD COLUMN last_reply_at varchar(19) NULL AFTER sent");
    }

    $db->Execute("UPDATE {$table} SET thread_key='' WHERE thread_key IS NULL");
    $db->Execute("UPDATE {$table} SET last_reply_at=sent WHERE last_reply_at IS NULL OR last_reply_at=''");

    $seed = $db->Execute("SELECT ID, subject FROM {$table} WHERE thread_key='' OR thread_key IS NULL");
    if ($seed) {
        while (!$seed->EOF) {
            $row = $seed->fields;
            $threadKey = bnt_mail_thread_key((string) ($row['subject'] ?? ''));
            $db->Execute(
                "UPDATE {$table} SET thread_key=? WHERE ID=?",
                array($threadKey, (int) $row['ID'])
            );
            $seed->MoveNext();
        }
    }

    $initialized = true;
}

function bnt_mail_subject_base(string $subject): string
{
    $subject = trim($subject);
    if ($subject === '') {
        return 'No Subject';
    }

    do {
        $previous = $subject;
        $subject = preg_replace('/^\s*(re|fw|fwd)\s*:\s*/i', '', $subject) ?? $subject;
    } while ($subject !== $previous);

    $subject = trim($subject);
    return ($subject === '') ? 'No Subject' : $subject;
}

function bnt_mail_reply_subject(string $subject): string
{
    $base = bnt_mail_subject_base($subject);
    if (stripos($subject, 're:') === 0) {
        return $subject;
    }

    return 'RE: ' . $base;
}

function bnt_mail_thread_key(string $subject): string
{
    return sha1(mb_strtolower(bnt_mail_subject_base($subject), 'UTF-8'));
}

function bnt_mail_send_message(int $senderId, int $recipientId, string $subject, string $message, ?string $threadKey = null, ?int $replyToId = null): bool
{
    global $db;

    bnt_ensure_messages_schema();

    $subject = trim($subject);
    $message = trim($message);
    if ($subject === '') {
        $subject = 'No Subject';
    }

    $sent = gmdate('Y-m-d H:i:s');
    $threadKey = $threadKey ?: bnt_mail_thread_key($subject);

    $result = $db->Execute(
        "INSERT INTO {$db->prefix}messages (sender_id, recp_id, thread_key, reply_to_id, subject, sent, last_reply_at, message, notified, read_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'N', NULL)",
        array($senderId, $recipientId, $threadKey, $replyToId, $subject, $sent, $sent, $message)
    );

    return db_op_result($db, $result, __LINE__, __FILE__) === true;
}

function bnt_mail_mark_thread_read(int $recipientId, string $threadKey): void
{
    global $db;

    bnt_ensure_messages_schema();
    $now = gmdate('Y-m-d H:i:s');
    $db->Execute(
        "UPDATE {$db->prefix}messages
            SET notified='Y', read_at=?
          WHERE recp_id=? AND thread_key=?",
        array($now, $recipientId, $threadKey)
    );
}

function bnt_mail_mark_thread_unread(int $recipientId, string $threadKey): void
{
    global $db;

    bnt_ensure_messages_schema();
    $db->Execute(
        "UPDATE {$db->prefix}messages
            SET notified='N', read_at=NULL
          WHERE recp_id=? AND thread_key=?",
        array($recipientId, $threadKey)
    );
}

function bnt_mail_mark_all_read(int $recipientId): void
{
    global $db;

    bnt_ensure_messages_schema();
    $now = gmdate('Y-m-d H:i:s');
    $db->Execute(
        "UPDATE {$db->prefix}messages SET notified='Y', read_at=? WHERE recp_id=?",
        array($now, $recipientId)
    );
}

function bnt_mail_delete_thread(int $recipientId, string $threadKey): void
{
    global $db;

    $db->Execute(
        "DELETE FROM {$db->prefix}messages WHERE recp_id=? AND thread_key=?",
        array($recipientId, $threadKey)
    );
}

function bnt_mail_get_threads(int $recipientId, string $filter = 'all', string $query = ''): array
{
    global $db;

    bnt_ensure_messages_schema();

    $sql = "SELECT m.thread_key,
                   MAX(m.last_reply_at) AS last_reply_at,
                   MAX(m.sent) AS last_sent,
                   MAX(m.ID) AS latest_id,
                   SUM(CASE WHEN m.notified='N' THEN 1 ELSE 0 END) AS unread_count,
                   COUNT(*) AS message_count
              FROM {$db->prefix}messages m
             WHERE m.recp_id=?";
    $params = array($recipientId);

    if ($filter === 'unread') {
        $sql .= " AND m.notified='N'";
    }

    if ($query !== '') {
        $sql .= " AND (m.subject LIKE ? OR m.message LIKE ?)";
        $params[] = '%' . $query . '%';
        $params[] = '%' . $query . '%';
    }

    $sql .= " GROUP BY m.thread_key ORDER BY MAX(m.last_reply_at) DESC, MAX(m.ID) DESC";

    $result = $db->Execute($sql, $params);
    if (!$result) {
        return array();
    }

    $threads = array();
    while (!$result->EOF) {
        $thread = $result->fields;
        $latest = $db->Execute(
            "SELECT m.*, s.character_name, s.ship_name
               FROM {$db->prefix}messages m
          LEFT JOIN {$db->prefix}ships s ON s.ship_id = m.sender_id
              WHERE m.ID=? LIMIT 1",
            array((int) $thread['latest_id'])
        );
        if ($latest && !$latest->EOF) {
            $thread['latest'] = $latest->fields;
        } else {
            $thread['latest'] = array();
        }
        $threads[] = $thread;
        $result->MoveNext();
    }

    return $threads;
}

function bnt_mail_get_thread_messages(int $recipientId, string $threadKey): array
{
    global $db;

    bnt_ensure_messages_schema();

    $result = $db->Execute(
        "SELECT m.*, s.character_name, s.ship_name
           FROM {$db->prefix}messages m
      LEFT JOIN {$db->prefix}ships s ON s.ship_id = m.sender_id
          WHERE m.recp_id=? AND m.thread_key=?
       ORDER BY m.sent ASC, m.ID ASC",
        array($recipientId, $threadKey)
    );

    $messages = array();
    if (!$result) {
        return $messages;
    }

    while (!$result->EOF) {
        $messages[] = $result->fields;
        $result->MoveNext();
    }

    return $messages;
}

