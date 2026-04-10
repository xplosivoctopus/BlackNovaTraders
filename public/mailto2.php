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
// File: mailto2.php

include "config/config.php";
updatecookie();

load_languages($db, $lang, array('mailto2', 'common', 'global_includes', 'global_funcs', 'footer', 'planet_report'), $langvars, $db_logging);

$title = $l_sendm_title;
$body_class = 'mail-compose';

if (checklogin()) {
    die();
}

$name = array_key_exists('name', $_GET) ? (string) $_GET['name'] : null;
$content = array_key_exists('content', $_POST) ? (string) $_POST['content'] : null;
$subject = array_key_exists('subject', $_REQUEST) ? (string) $_REQUEST['subject'] : null;
$to = array_key_exists('to', $_POST) ? (string) $_POST['to'] : '';
$thread = array_key_exists('thread', $_REQUEST) ? trim((string) $_REQUEST['thread']) : '';
$replyTo = array_key_exists('reply_to', $_REQUEST) ? (int) $_REQUEST['reply_to'] : null;

$res = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE email=?;", array($username));
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
$playerinfo = $res->fields;
$contactNameMap = bnt_get_contact_names_map((int) $playerinfo['ship_id']);
$contacts = bnt_get_contacts((int) $playerinfo['ship_id']);

$sendStatus = null;
$sendStatusClass = 'ok';

if (!is_null($content)) {
    bnt_require_csrf();
}

if ($subject !== null) {
    $subject = bnt_mail_reply_subject($subject);
} else {
    $subject = '';
}

include "header.php";

echo <<<HTML
<style>
.mail-compose-shell {
  width: min(1120px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.mail-compose-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.mail-compose-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.mail-compose-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.mail-compose-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.mail-compose-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 18px;
}
.mail-compose-toolbar a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
}
.mail-compose-panel {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
}
.mail-compose-alert {
  padding: 12px 14px;
  margin-bottom: 16px;
  border-left: 3px solid;
}
.mail-compose-alert--ok { border-color: #00ff88; color: #7cf0b6; background: rgba(0,255,136,0.07); }
.mail-compose-alert--err { border-color: #ff3355; color: #ff96a8; background: rgba(255,51,85,0.07); }
.mail-compose-form {
  display: grid;
  gap: 14px;
}
.mail-compose-row {
  display: grid;
  gap: 6px;
}
.mail-compose-row label {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}
.mail-compose-form select,
.mail-compose-form input,
.mail-compose-form textarea {
  width: 100%;
  box-sizing: border-box;
  padding: 11px 12px;
  background: rgba(6, 20, 34, 0.95);
  border: 1px solid rgba(0, 238, 255, 0.14);
  color: #eefbff;
}
.mail-compose-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
.mail-compose-actions button,
.mail-compose-actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 10px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
  cursor: pointer;
}
.mail-compose-side-note {
  margin-top: 16px;
  color: rgba(170, 200, 225, 0.74);
  line-height: 1.6;
}
</style>
HTML;

echo "<div class='mail-compose-shell'>";
echo "<section class='mail-compose-hero'>";
echo "<div class='mail-compose-eyebrow'>Subspace Mail</div>";
echo "<h1 class='mail-compose-title'>Compose Message</h1>";
echo "<p class='mail-compose-copy'>Use the same threaded mail system as the inbox. Replies stay grouped together, contacts float to the top, and team mail still works from the same screen.</p>";
echo "</section>";

echo "<nav class='mail-compose-toolbar'>";
echo "<a href='readmail.php'>Threaded Inbox</a>";
echo "<a href='notifications.php'>Notifications</a>";
echo "<a href='contacts.php'>Contacts</a>";
echo "</nav>";

echo "<section class='mail-compose-panel'>";

if (is_null($content)) {
    $res = $db->Execute("SELECT character_name FROM {$db->prefix}ships WHERE email NOT LIKE '%@Xenobe' AND ship_destroyed ='N' AND turns_used > 0 AND ship_id <> {$playerinfo['ship_id']} ORDER BY character_name ASC");
    db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
    $res2 = $db->Execute("SELECT team_name FROM {$db->prefix}teams WHERE admin ='N' ORDER BY team_name ASC");
    db_op_result($db, $res2, __LINE__, __FILE__, $db_logging);

    echo "<form class='mail-compose-form' action='mailto2.php' method='post'>";
    echo bnt_csrf_input();
    if ($thread !== '') {
        echo "<input type='hidden' name='thread' value='" . htmlspecialchars($thread, ENT_QUOTES, 'UTF-8') . "'>";
    }
    if (!empty($replyTo)) {
        echo "<input type='hidden' name='reply_to' value='" . (int) $replyTo . "'>";
    }
    echo "<div class='mail-compose-row'>";
    echo "<label for='to'>{$l_sendm_to}</label>";
    echo "<select id='to' name='to'>";
    echo "<option" . (($playerinfo['character_name'] == $name) ? " selected" : "") . ">{$playerinfo['character_name']}</option>";

    if (!empty($contacts)) {
        echo "<optgroup label='Contacts'>";
        foreach ($contacts as $contact) {
            $contactName = (string) $contact['character_name'];
            if ($contactName === '') {
                continue;
            }
            $displayName = trim((string) ($contact['nickname'] ?? ''));
            if ($displayName === '') {
                $displayName = $contactName;
            }
            echo "<option value='" . htmlspecialchars($contactName, ENT_QUOTES, 'UTF-8') . "'" . (($contactName == $name) ? " selected" : "") . ">" . htmlspecialchars($displayName . ' [Contact]', ENT_QUOTES, 'UTF-8') . "</option>";
        }
        echo "</optgroup>";
    }

    echo "<optgroup label='All Pilots'>";
    while (!$res->EOF) {
        $row = $res->fields;
        if (isset($contactNameMap[$row['character_name']])) {
            $res->MoveNext();
            continue;
        }
        echo "<option" . (($row['character_name'] == $name) ? " selected" : "") . ">{$row['character_name']}</option>";
        $res->MoveNext();
    }
    echo "</optgroup>";

    while (!$res2->EOF && $res2->fields != null) {
        $row2 = $res2->fields;
        echo "<option>{$l_sendm_ally} {$row2['team_name']}</option>";
        $res2->MoveNext();
    }
    echo "</select>";
    echo "</div>";

    echo "<div class='mail-compose-row'>";
    echo "<label>{$l_sendm_from}</label>";
    echo "<input disabled type='text' value=\"" . htmlspecialchars((string) $playerinfo['character_name'], ENT_QUOTES, 'UTF-8') . "\">";
    echo "</div>";

    echo "<div class='mail-compose-row'>";
    echo "<label for='subject'>{$l_sendm_subj}</label>";
    echo "<input id='subject' type='text' name='subject' maxlength='250' value=\"" . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') . "\">";
    echo "</div>";

    echo "<div class='mail-compose-row'>";
    echo "<label for='content'>{$l_sendm_mess}</label>";
    echo "<textarea id='content' name='content' rows='10'></textarea>";
    echo "</div>";

    echo "<div class='mail-compose-actions'>";
    echo "<button type='submit'>{$l_sendm_send}</button>";
    echo "<a href='readmail.php'>Cancel</a>";
    echo "</div>";
    echo "</form>";
    echo "<div class='mail-compose-side-note'>Replies with the same subject stay in the same thread. Team messages still fan out to all team members.</div>";
} else {
    $sendStatus = 'Message sent.';
    $sendStatusClass = 'ok';
    if (strpos($to, $l_sendm_ally) === false) {
        $res = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE character_name=?;", array($to));
        db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
        $target_info = $res->fields;
        $sentOk = bnt_mail_send_message(
            (int) $playerinfo['ship_id'],
            (int) $target_info['ship_id'],
            $subject,
            $content,
            $thread,
            $replyTo
        );
        if (!$sentOk) {
            $sendStatus = 'Message failed to send.';
            $sendStatusClass = 'err';
        }
    } else {
        $to = str_replace($l_sendm_ally, "", $to);
        $to = trim($to);
        $res = $db->Execute("SELECT id FROM {$db->prefix}teams WHERE team_name=?;", array($to));
        db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
        $row = $res->fields;

        $res2 = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE team=?;", array($row['id']));
        db_op_result($db, $res2, __LINE__, __FILE__, $db_logging);

        while (!$res2->EOF) {
            $row2 = $res2->fields;
            bnt_mail_send_message(
                (int) $playerinfo['ship_id'],
                (int) $row2['ship_id'],
                $subject,
                $content,
                $thread,
                $replyTo
            );
            $res2->MoveNext();
        }
    }

    echo "<div class='mail-compose-alert mail-compose-alert--{$sendStatusClass}'>" . htmlspecialchars($sendStatus, ENT_QUOTES, 'UTF-8') . "</div>";
    echo "<div class='mail-compose-actions'>";
    echo "<a href='readmail.php'>Back to Inbox</a>";
    echo "<a href='mailto2.php'>Write Another</a>";
    if ($thread !== '') {
        echo "<a href='readmail.php?thread=" . rawurlencode($thread) . "'>Return to Thread</a>";
    }
    echo "</div>";
}

echo "</section>";
echo "</div>";

TEXT_GOTOMAIN();
include "footer.php";
?>
