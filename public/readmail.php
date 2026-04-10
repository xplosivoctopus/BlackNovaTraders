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
// File: readmail.php

include "config/config.php";
updatecookie();

load_languages($db, $lang, array('readmail', 'common', 'global_includes', 'global_funcs', 'footer', 'planet_report'), $langvars, $db_logging);

$title = $l_readm_title;
$body_class = 'mailbox';

if (checklogin()) {
    die();
}

$res = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE email=? LIMIT 1", array($username));
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
$playerinfo = $res->fields;
$recipientId = (int) $playerinfo['ship_id'];

$threadKey = trim((string) ($_REQUEST['thread'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? 'all'));
$query = trim((string) ($_GET['q'] ?? ''));
$allowedFilters = array('all', 'unread');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

$message = null;
$messageClass = 'ok';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    bnt_require_csrf();
    $action = trim((string) ($_POST['mail_action'] ?? ''));
    $threadKey = trim((string) ($_POST['thread'] ?? $threadKey));

    if ($action === 'mark_all_read') {
        bnt_mail_mark_all_read($recipientId);
        bnt_mark_notifications_viewed($recipientId);
        $message = 'All inbox threads marked as read.';
    } elseif ($threadKey !== '') {
        if ($action === 'delete_thread') {
            bnt_mail_delete_thread($recipientId, $threadKey);
            $message = 'Thread deleted.';
            $messageClass = 'warn';
            $threadKey = '';
        } elseif ($action === 'mark_thread_read') {
            bnt_mail_mark_thread_read($recipientId, $threadKey);
            bnt_mark_notifications_viewed($recipientId);
            $message = 'Thread marked as read.';
        } elseif ($action === 'mark_thread_unread') {
            bnt_mail_mark_thread_unread($recipientId, $threadKey);
            $message = 'Thread marked as unread.';
            $threadKey = '';
        }
    }
}

$threads = bnt_mail_get_threads($recipientId, $filter, $query);
$threadMessages = array();
$currentThread = null;

if ($threadKey !== '') {
    $threadMessages = bnt_mail_get_thread_messages($recipientId, $threadKey);
    if (!empty($threadMessages)) {
        $currentThread = $threadMessages[count($threadMessages) - 1];
        bnt_mail_mark_thread_read($recipientId, $threadKey);
        bnt_mark_notifications_viewed($recipientId);
    } else {
        $threadKey = '';
    }
}

include "header.php";

echo <<<HTML
<style>
.mailbox-shell {
  width: min(1180px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.mailbox-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.mailbox-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.mailbox-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.mailbox-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.mailbox-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
  margin-bottom: 18px;
}
.mailbox-toolbar form {
  margin: 0;
}
.mailbox-filter,
.mailbox-toolbar button,
.mailbox-toolbar a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
}
.mailbox-filter--active {
  background: #00eeff;
  color: #001620;
  border-color: rgba(0, 238, 255, 0.45);
}
.mailbox-search {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.mailbox-search input {
  min-width: 260px;
  padding: 10px 12px;
  background: rgba(6, 20, 34, 0.95);
  border: 1px solid rgba(0, 238, 255, 0.14);
  color: #eefbff;
}
.mailbox-grid {
  display: grid;
  grid-template-columns: 0.9fr 1.35fr;
  gap: 16px;
}
.mailbox-panel {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
}
.mailbox-panel h2 {
  margin: 0 0 14px;
  color: #eefbff;
  font-size: 20px;
}
.mailbox-list {
  display: grid;
  gap: 12px;
}
.mailbox-thread {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(5, 16, 29, 0.96);
  padding: 14px 16px;
}
.mailbox-thread--active {
  border-color: rgba(0, 238, 255, 0.32);
  box-shadow: 0 0 0 1px rgba(0, 238, 255, 0.16) inset;
}
.mailbox-thread--unread {
  border-left: 3px solid #00eeff;
}
.mailbox-thread__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 6px;
}
.mailbox-thread__subject {
  margin: 0;
  font-size: 18px;
  color: #eefbff;
}
.mailbox-thread__time {
  font-size: 12px;
  color: rgba(170, 200, 225, 0.72);
  white-space: nowrap;
}
.mailbox-thread__meta {
  font-size: 12px;
  color: #7edfff;
  margin-bottom: 8px;
}
.mailbox-thread__excerpt {
  color: rgba(220, 238, 248, 0.88);
  line-height: 1.55;
  margin-bottom: 10px;
}
.mailbox-thread__actions,
.mailbox-message__actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.mailbox-thread__actions form,
.mailbox-message__actions form {
  margin: 0;
}
.mailbox-message {
  border-top: 1px solid rgba(0, 238, 255, 0.08);
  padding-top: 14px;
  margin-top: 14px;
}
.mailbox-message:first-child {
  border-top: 0;
  padding-top: 0;
  margin-top: 0;
}
.mailbox-message__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 6px;
}
.mailbox-message__sender {
  color: #eefbff;
  font-size: 18px;
  margin: 0;
}
.mailbox-message__meta {
  color: #7edfff;
  font-size: 12px;
  margin-bottom: 8px;
}
.mailbox-message__body {
  color: rgba(220, 238, 248, 0.92);
  line-height: 1.65;
  margin-bottom: 12px;
}
.mailbox-empty {
  color: rgba(170, 200, 225, 0.72);
  font-style: italic;
}
.mailbox-alert {
  padding: 12px 14px;
  margin-bottom: 16px;
  border-left: 3px solid;
}
.mailbox-alert--ok { border-color: #00ff88; color: #7cf0b6; background: rgba(0,255,136,0.07); }
.mailbox-alert--warn { border-color: #f59e0b; color: #f8c765; background: rgba(245,158,11,0.07); }
@media (max-width: 980px) {
  .mailbox-grid {
    grid-template-columns: 1fr;
  }
}
</style>
HTML;

echo "<div class='mailbox-shell'>";
echo "<section class='mailbox-hero'>";
echo "<div class='mailbox-eyebrow'>Subspace Mail</div>";
echo "<h1 class='mailbox-title'>Threaded Inbox</h1>";
echo "<p class='mailbox-copy'>Messages are now grouped into conversations, with quick unread controls and inbox filtering so you can keep track of real conversations instead of scanning one giant list.</p>";
echo "</section>";

if ($message !== null) {
    echo "<div class='mailbox-alert mailbox-alert--{$messageClass}'>" . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</div>";
}

echo "<div class='mailbox-toolbar'>";
echo "<a class='mailbox-filter" . ($filter === 'all' ? " mailbox-filter--active" : "") . "' href='readmail.php?filter=all" . ($query !== '' ? "&q=" . rawurlencode($query) : '') . "'>All Threads</a>";
echo "<a class='mailbox-filter" . ($filter === 'unread' ? " mailbox-filter--active" : "") . "' href='readmail.php?filter=unread" . ($query !== '' ? "&q=" . rawurlencode($query) : '') . "'>Unread</a>";
echo "<a class='mailbox-filter' href='mailto2.php'>Compose</a>";
echo "<form method='post' action='readmail.php" . ($filter !== 'all' ? "?filter=" . rawurlencode($filter) : '') . "'>";
echo bnt_csrf_input();
echo "<input type='hidden' name='mail_action' value='mark_all_read'>";
echo "<button type='submit'>Mark All Read</button>";
echo "</form>";
echo "<form class='mailbox-search' method='get' action='readmail.php'>";
echo "<input type='hidden' name='filter' value='" . htmlspecialchars($filter, ENT_QUOTES, 'UTF-8') . "'>";
echo "<input type='text' name='q' value='" . htmlspecialchars($query, ENT_QUOTES, 'UTF-8') . "' placeholder='Search subject or message text'>";
echo "<button type='submit'>Search</button>";
echo "</form>";
echo "</div>";

echo "<div class='mailbox-grid'>";
echo "<section class='mailbox-panel'>";
echo "<h2>Inbox</h2>";
if (empty($threads)) {
    echo "<div class='mailbox-empty'>No conversations match this filter.</div>";
} else {
    echo "<div class='mailbox-list'>";
    foreach ($threads as $thread) {
        $latest = $thread['latest'] ?? array();
        $threadSubject = bnt_mail_subject_base((string) ($latest['subject'] ?? 'No Subject'));
        $senderName = (string) ($latest['character_name'] ?? 'Unknown sender');
        $excerpt = trim(strip_tags((string) ($latest['message'] ?? '')));
        if (mb_strlen($excerpt, 'UTF-8') > 180) {
            $excerpt = mb_substr($excerpt, 0, 177, 'UTF-8') . '...';
        }

        $classes = 'mailbox-thread';
        if ($threadKey !== '' && $thread['thread_key'] === $threadKey) {
            $classes .= ' mailbox-thread--active';
        }
        if ((int) ($thread['unread_count'] ?? 0) > 0) {
            $classes .= ' mailbox-thread--unread';
        }

        echo "<article class='{$classes}'>";
        echo "<div class='mailbox-thread__top'>";
        echo "<h3 class='mailbox-thread__subject'><a class='new_link' href='readmail.php?filter=" . rawurlencode($filter) . "&thread=" . rawurlencode((string) $thread['thread_key']) . ($query !== '' ? "&q=" . rawurlencode($query) : '') . "'>" . htmlspecialchars($threadSubject, ENT_QUOTES, 'UTF-8') . "</a></h3>";
        echo "<div class='mailbox-thread__time'>" . htmlspecialchars((string) ($thread['last_reply_at'] ?? ''), ENT_QUOTES, 'UTF-8') . "</div>";
        echo "</div>";
        echo "<div class='mailbox-thread__meta'>Latest from <a class='new_link' href='profile.php?ship_id=" . (int) ($latest['sender_id'] ?? 0) . "'>" . htmlspecialchars($senderName, ENT_QUOTES, 'UTF-8') . "</a> · " . (int) ($thread['message_count'] ?? 0) . " messages";
        if ((int) ($thread['unread_count'] ?? 0) > 0) {
            echo " · " . (int) $thread['unread_count'] . " unread";
        }
        echo "</div>";
        echo "<div class='mailbox-thread__excerpt'>" . htmlspecialchars($excerpt, ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<div class='mailbox-thread__actions'>";
        echo "<a class='mailbox-filter' href='mailto2.php?name=" . rawurlencode($senderName) . "&subject=" . rawurlencode((string) ($latest['subject'] ?? '')) . "&thread=" . rawurlencode((string) $thread['thread_key']) . "&reply_to=" . (int) ($latest['ID'] ?? 0) . "'>Reply</a>";
        echo "<form method='post' action='readmail.php?filter=" . rawurlencode($filter) . ($query !== '' ? "&q=" . rawurlencode($query) : '') . "'>";
        echo bnt_csrf_input();
        echo "<input type='hidden' name='thread' value='" . htmlspecialchars((string) $thread['thread_key'], ENT_QUOTES, 'UTF-8') . "'>";
        if ((int) ($thread['unread_count'] ?? 0) > 0) {
            echo "<input type='hidden' name='mail_action' value='mark_thread_read'><button type='submit'>Mark Read</button>";
        } else {
            echo "<input type='hidden' name='mail_action' value='mark_thread_unread'><button type='submit'>Mark Unread</button>";
        }
        echo "</form>";
        echo "<form method='post' action='readmail.php?filter=" . rawurlencode($filter) . ($query !== '' ? "&q=" . rawurlencode($query) : '') . "'>";
        echo bnt_csrf_input();
        echo "<input type='hidden' name='thread' value='" . htmlspecialchars((string) $thread['thread_key'], ENT_QUOTES, 'UTF-8') . "'>";
        echo "<input type='hidden' name='mail_action' value='delete_thread'><button type='submit'>Delete</button>";
        echo "</form>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
}
echo "</section>";

echo "<section class='mailbox-panel'>";
echo "<h2>" . ($currentThread ? "Conversation" : "Select a Thread") . "</h2>";
if (!$currentThread) {
    echo "<div class='mailbox-empty'>Choose a thread from the inbox to read it here.</div>";
} else {
    echo "<div class='mailbox-thread__actions' style='margin-bottom:14px;'>";
    echo "<a class='mailbox-filter' href='mailto2.php?name=" . rawurlencode((string) ($currentThread['character_name'] ?? '')) . "&subject=" . rawurlencode((string) ($currentThread['subject'] ?? '')) . "&thread=" . rawurlencode($threadKey) . "&reply_to=" . (int) ($currentThread['ID'] ?? 0) . "'>Reply In Thread</a>";
    echo "<a class='mailbox-filter' href='contacts.php?search=" . rawurlencode((string) ($currentThread['character_name'] ?? '')) . "'>Add Contact</a>";
    echo "</div>";
    foreach ($threadMessages as $messageRow) {
        echo "<article class='mailbox-message'>";
        echo "<div class='mailbox-message__header'>";
        echo "<h3 class='mailbox-message__sender'><a class='new_link' href='profile.php?ship_id=" . (int) $messageRow['sender_id'] . "'>" . htmlspecialchars((string) ($messageRow['character_name'] ?? 'Unknown sender'), ENT_QUOTES, 'UTF-8') . "</a></h3>";
        echo "<div class='mailbox-thread__time'>" . htmlspecialchars((string) ($messageRow['sent'] ?? ''), ENT_QUOTES, 'UTF-8') . "</div>";
        echo "</div>";
        echo "<div class='mailbox-message__meta'>Aboard " . htmlspecialchars((string) ($messageRow['ship_name'] ?? 'Unknown ship'), ENT_QUOTES, 'UTF-8') . " · Subject " . htmlspecialchars((string) ($messageRow['subject'] ?? 'No Subject'), ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<div class='mailbox-message__body'>" . nl2br(htmlspecialchars((string) ($messageRow['message'] ?? ''), ENT_QUOTES, 'UTF-8')) . "</div>";
        echo "<div class='mailbox-message__actions'>";
        echo "<a class='mailbox-filter' href='mailto2.php?name=" . rawurlencode((string) ($messageRow['character_name'] ?? '')) . "&subject=" . rawurlencode((string) ($messageRow['subject'] ?? '')) . "&thread=" . rawurlencode($threadKey) . "&reply_to=" . (int) ($messageRow['ID'] ?? 0) . "'>Reply</a>";
        echo "</div>";
        echo "</article>";
    }
}
echo "</section>";
echo "</div>";
echo "</div>";

TEXT_GOTOMAIN();
include "footer.php";
?>
