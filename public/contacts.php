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
// File: contacts.php

include "config/config.php";
updatecookie();

load_languages($db, $lang, array('common', 'global_includes', 'global_funcs', 'footer', 'news'), $langvars, $db_logging);

$title = 'Contacts';
$body_class = 'contacts';

if (checklogin()) {
    die();
}

$res = $db->Execute("SELECT ship_id, character_name FROM {$db->prefix}ships WHERE email=? LIMIT 1", array($username));
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
$playerinfo = $res->fields;
$ownerId = (int) $playerinfo['ship_id'];

$message = null;
$messageClass = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    bnt_require_csrf();
    $action = (string) ($_POST['contact_action'] ?? '');
    $contactId = (int) ($_POST['contact_id'] ?? 0);

    if ($action === 'add') {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (bnt_add_contact($ownerId, $contactId, $nickname, $notes)) {
            $message = 'Contact added.';
        } else {
            $message = 'Unable to add that contact.';
            $messageClass = 'err';
        }
    } elseif ($action === 'remove') {
        if (bnt_remove_contact($ownerId, $contactId)) {
            $message = 'Contact removed.';
            $messageClass = 'warn';
        } else {
            $message = 'Unable to remove that contact.';
            $messageClass = 'err';
        }
    } elseif ($action === 'update') {
        $nickname = trim((string) ($_POST['nickname'] ?? ''));
        $notes = trim((string) ($_POST['notes'] ?? ''));
        if (bnt_update_contact($ownerId, $contactId, $nickname, $notes)) {
            $message = 'Contact updated.';
        } else {
            $message = 'Unable to update that contact.';
            $messageClass = 'err';
        }
    }
}

$search = trim((string) ($_GET['search'] ?? ''));
$matches = bnt_find_contact_candidates($search, $ownerId);
$contacts = bnt_get_contacts($ownerId);

include "header.php";

echo <<<HTML
<style>
.contacts-shell {
  width: min(1120px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.contacts-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.contacts-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.contacts-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.contacts-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.contacts-grid {
  display: grid;
  grid-template-columns: 0.95fr 1.35fr;
  gap: 16px;
}
.contacts-panel {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
}
.contacts-panel h2 {
  margin: 0 0 14px;
  color: #eefbff;
  font-size: 20px;
}
.contacts-form {
  display: grid;
  gap: 12px;
}
.contacts-form input,
.contacts-form textarea {
  width: 100%;
  box-sizing: border-box;
  padding: 10px 12px;
  background: rgba(6, 20, 34, 0.95);
  border: 1px solid rgba(0, 238, 255, 0.14);
  color: #eefbff;
}
.contacts-form button,
.contacts-action,
.contacts-inline button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
  cursor: pointer;
}
.contacts-inline .danger {
  border-color: rgba(255, 51, 85, 0.2);
  color: #ff9dad;
}
.contacts-alert {
  padding: 12px 14px;
  margin-bottom: 16px;
  border-left: 3px solid;
}
.contacts-alert--ok { border-color: #00ff88; color: #7cf0b6; background: rgba(0,255,136,0.07); }
.contacts-alert--warn { border-color: #f59e0b; color: #f8c765; background: rgba(245,158,11,0.07); }
.contacts-alert--err { border-color: #ff3355; color: #ff96a8; background: rgba(255,51,85,0.07); }
.contacts-results,
.contacts-list {
  display: grid;
  gap: 12px;
}
.contact-card {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(5, 16, 29, 0.96);
  padding: 14px 16px;
}
.contact-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  align-items: flex-start;
  margin-bottom: 8px;
}
.contact-card__name {
  margin: 0;
  color: #eefbff;
  font-size: 20px;
}
.contact-card__ship {
  color: #7edfff;
  font-size: 13px;
}
.contact-card__meta {
  color: rgba(170, 200, 225, 0.74);
  font-size: 12px;
  margin-bottom: 10px;
}
.contacts-inline {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-top: 10px;
}
.contacts-inline form {
  margin: 0;
}
.contacts-empty {
  color: rgba(170, 200, 225, 0.74);
  font-style: italic;
}
@media (max-width: 920px) {
  .contacts-grid {
    grid-template-columns: 1fr;
  }
}
</style>
HTML;

echo "<div class='contacts-shell'>";
echo "<section class='contacts-hero'>";
echo "<div class='contacts-eyebrow'>Social Systems</div>";
echo "<h1 class='contacts-title'>Friend / Contact List</h1>";
echo "<p class='contacts-copy'>Keep a persistent roster of pilots you care about, add private notes, and jump straight into messages without hunting through the full player directory.</p>";
echo "</section>";

if ($message !== null) {
    echo "<div class='contacts-alert contacts-alert--{$messageClass}'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</div>";
}

echo "<div class='contacts-grid'>";
echo "<section class='contacts-panel'>";
echo "<h2>Find Pilots</h2>";
echo "<form class='contacts-form' method='get' action='contacts.php'>";
echo "<input type='text' name='search' placeholder='Search by character name' value='" . htmlspecialchars($search, ENT_QUOTES, 'UTF-8') . "'>";
echo "<button type='submit'>Search</button>";
echo "</form>";

if ($search !== '') {
    echo "<div class='contacts-results' style='margin-top:16px;'>";
    if (empty($matches)) {
        echo "<div class='contacts-empty'>No pilots matched that search.</div>";
    } else {
        foreach ($matches as $match) {
            $alreadyContact = bnt_is_contact($ownerId, (int) $match['ship_id']);
            echo "<article class='contact-card'>";
            echo "<div class='contact-card__top'>";
            echo "<div>";
            echo "<h3 class='contact-card__name'><a class='new_link' href='profile.php?ship_id=" . (int) $match['ship_id'] . "'>" . htmlspecialchars((string) $match['character_name'], ENT_QUOTES, 'UTF-8') . "</a></h3>";
            echo "<div class='contact-card__ship'>" . htmlspecialchars((string) $match['ship_name'], ENT_QUOTES, 'UTF-8') . "</div>";
            echo "</div>";
            echo "<a class='contacts-action' href='mailto2.php?name=" . rawurlencode((string) $match['character_name']) . "'>Message</a>";
            echo "</div>";
            echo "<div class='contact-card__meta'>Turns used: " . (int) $match['turns_used'] . "</div>";
            if ($alreadyContact) {
                echo "<div class='contacts-empty'>Already in your contacts.</div>";
            } else {
                echo "<form class='contacts-form' method='post' action='contacts.php?search=" . rawurlencode($search) . "'>";
                echo bnt_csrf_input();
                echo "<input type='hidden' name='contact_action' value='add'>";
                echo "<input type='hidden' name='contact_id' value='" . (int) $match['ship_id'] . "'>";
                echo "<input type='text' name='nickname' maxlength='60' placeholder='Nickname (optional)'>";
                echo "<textarea name='notes' rows='3' maxlength='255' placeholder='Private note (optional)'></textarea>";
                echo "<button type='submit'>Add Contact</button>";
                echo "</form>";
            }
            echo "</article>";
        }
    }
    echo "</div>";
}
echo "</section>";

echo "<section class='contacts-panel'>";
echo "<h2>Your Contacts</h2>";
if (empty($contacts)) {
    echo "<div class='contacts-empty'>You have no saved contacts yet.</div>";
} else {
    echo "<div class='contacts-list'>";
    foreach ($contacts as $contact) {
        $displayName = trim((string) ($contact['nickname'] ?? '')) !== '' ? (string) $contact['nickname'] : (string) $contact['character_name'];
        $shipName = (string) ($contact['ship_name'] ?? 'Unknown ship');
        $status = ((string) ($contact['ship_destroyed'] ?? 'N') === 'Y') ? 'Destroyed' : 'Active';
        echo "<article class='contact-card'>";
        echo "<div class='contact-card__top'>";
        echo "<div>";
        echo "<h3 class='contact-card__name'><a class='new_link' href='profile.php?ship_id=" . (int) $contact['contact_id'] . "'>" . htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') . "</a></h3>";
        echo "<div class='contact-card__ship'>" . htmlspecialchars((string) $contact['character_name'], ENT_QUOTES, 'UTF-8') . " aboard " . htmlspecialchars($shipName, ENT_QUOTES, 'UTF-8') . "</div>";
        echo "</div>";
        echo "<a class='contacts-action' href='mailto2.php?name=" . rawurlencode((string) $contact['character_name']) . "'>Message</a>";
        echo "</div>";
        echo "<div class='contact-card__meta'>Status: " . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . " · Added " . htmlspecialchars((string) $contact['created_at'], ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<form class='contacts-form' method='post' action='contacts.php'>";
        echo bnt_csrf_input();
        echo "<input type='hidden' name='contact_action' value='update'>";
        echo "<input type='hidden' name='contact_id' value='" . (int) $contact['contact_id'] . "'>";
        echo "<input type='text' name='nickname' maxlength='60' placeholder='Nickname' value='" . htmlspecialchars((string) ($contact['nickname'] ?? ''), ENT_QUOTES, 'UTF-8') . "'>";
        echo "<textarea name='notes' rows='3' maxlength='255' placeholder='Private note'>" . htmlspecialchars((string) ($contact['notes'] ?? ''), ENT_QUOTES, 'UTF-8') . "</textarea>";
        echo "<div class='contacts-inline'>";
        echo "<button type='submit'>Save</button>";
        echo "</form>";
        echo "<form method='post' action='contacts.php'>";
        echo bnt_csrf_input();
        echo "<input type='hidden' name='contact_action' value='remove'>";
        echo "<input type='hidden' name='contact_id' value='" . (int) $contact['contact_id'] . "'>";
        echo "<button class='danger' type='submit'>Remove</button>";
        echo "</form>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
}
echo "</section>";
echo "</div>";
echo "</div>";

TEXT_GOTOMAIN();
include "footer.php";
?>
