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
// File: includes/contacts.php

function bnt_ensure_contacts_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$db->prefix}contacts (" .
        "owner_id int unsigned NOT NULL," .
        "contact_id int unsigned NOT NULL," .
        "nickname varchar(60) NOT NULL default ''," .
        "notes varchar(255) NOT NULL default ''," .
        "created_at datetime NOT NULL," .
        "PRIMARY KEY (owner_id, contact_id)" .
        ")"
    );

    $initialized = true;
}

function bnt_get_contacts(int $ownerId): array
{
    global $db;

    bnt_ensure_contacts_table();

    $result = $db->Execute(
        "SELECT c.owner_id, c.contact_id, c.nickname, c.notes, c.created_at,
                s.character_name, s.ship_name, s.team, s.last_login, s.ship_destroyed
           FROM {$db->prefix}contacts c
      LEFT JOIN {$db->prefix}ships s ON s.ship_id = c.contact_id
          WHERE c.owner_id=?
       ORDER BY COALESCE(NULLIF(c.nickname, ''), s.character_name) ASC",
        array($ownerId)
    );

    $contacts = array();
    if (!$result) {
        return $contacts;
    }

    while (!$result->EOF) {
        $contacts[] = $result->fields;
        $result->MoveNext();
    }

    return $contacts;
}

function bnt_is_contact(int $ownerId, int $contactId): bool
{
    global $db;

    bnt_ensure_contacts_table();
    $result = $db->Execute(
        "SELECT contact_id FROM {$db->prefix}contacts WHERE owner_id=? AND contact_id=? LIMIT 1",
        array($ownerId, $contactId)
    );

    return (bool) ($result && !$result->EOF);
}

function bnt_add_contact(int $ownerId, int $contactId, string $nickname = '', string $notes = ''): bool
{
    global $db;

    bnt_ensure_contacts_table();
    if ($ownerId <= 0 || $contactId <= 0 || $ownerId === $contactId) {
        return false;
    }

    $exists = $db->Execute(
        "SELECT ship_id FROM {$db->prefix}ships WHERE ship_id=? AND ship_destroyed='N' LIMIT 1",
        array($contactId)
    );
    if (!$exists || $exists->EOF) {
        return false;
    }

    $result = $db->Execute(
        "INSERT INTO {$db->prefix}contacts (owner_id, contact_id, nickname, notes, created_at)
         VALUES (?, ?, ?, ?, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE nickname=VALUES(nickname), notes=VALUES(notes)",
        array($ownerId, $contactId, trim($nickname), trim($notes))
    );

    return db_op_result($db, $result, __LINE__, __FILE__) === true;
}

function bnt_remove_contact(int $ownerId, int $contactId): bool
{
    global $db;

    bnt_ensure_contacts_table();
    $result = $db->Execute(
        "DELETE FROM {$db->prefix}contacts WHERE owner_id=? AND contact_id=?",
        array($ownerId, $contactId)
    );

    return db_op_result($db, $result, __LINE__, __FILE__) === true;
}

function bnt_update_contact(int $ownerId, int $contactId, string $nickname, string $notes): bool
{
    global $db;

    bnt_ensure_contacts_table();
    $result = $db->Execute(
        "UPDATE {$db->prefix}contacts SET nickname=?, notes=? WHERE owner_id=? AND contact_id=?",
        array(trim($nickname), trim($notes), $ownerId, $contactId)
    );

    return db_op_result($db, $result, __LINE__, __FILE__) === true;
}

function bnt_find_contact_candidates(string $search, int $ownerId, int $limit = 25): array
{
    global $db;

    $search = trim($search);
    if ($search === '') {
        return array();
    }

    $limit = max(1, min(50, $limit));
    $result = $db->Execute(
        "SELECT ship_id, character_name, ship_name, turns_used, last_login
           FROM {$db->prefix}ships
          WHERE ship_destroyed='N'
            AND ship_id<>?
            AND email NOT LIKE '%@xenobe'
            AND character_name LIKE ?
       ORDER BY character_name ASC
          LIMIT {$limit}",
        array($ownerId, '%' . $search . '%')
    );

    $matches = array();
    if (!$result) {
        return $matches;
    }

    while (!$result->EOF) {
        $matches[] = $result->fields;
        $result->MoveNext();
    }

    return $matches;
}

function bnt_get_contact_names_map(int $ownerId): array
{
    $map = array();
    foreach (bnt_get_contacts($ownerId) as $contact) {
        if (!empty($contact['character_name'])) {
            $map[(string) $contact['character_name']] = (int) $contact['contact_id'];
        }
    }

    return $map;
}

