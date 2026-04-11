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
// File: notifications.php

include "config/config.php";
updatecookie();

load_languages($db, $lang, array('readmail', 'log', 'common', 'global_includes', 'global_funcs', 'footer', 'news'), $langvars, $db_logging);

$title = 'Notifications Center';
$body_class = 'notifications';

if (checklogin()) {
    die();
}

$res = $db->Execute("SELECT ship_id, character_name FROM {$db->prefix}ships WHERE email=? LIMIT 1", array($username));
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);
$playerinfo = $res->fields;
$shipId = (int) $playerinfo['ship_id'];

$filter = (string) ($_GET['filter'] ?? 'all');
$allowedFilters = array('all', 'unread', 'messages', 'activity');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

if ($filter === 'all') {
    bnt_mark_notifications_viewed($shipId);
}

$counts = bnt_get_notification_counts($shipId);
$items = bnt_get_recent_notifications($shipId, 50, $filter);

include "header.php";

echo <<<HTML
<style>
.notif-shell {
  width: min(1120px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.notif-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.notif-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.notif-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.notif-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.notif-stats {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 18px;
}
.notif-stat {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 14px 16px;
}
.notif-stat__label {
  display: block;
  font-size: 10px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(122, 176, 204, 0.9);
  margin-bottom: 4px;
}
.notif-stat__value {
  display: block;
  font-family: var(--font-hud, monospace);
  font-size: 22px;
  color: #eefbff;
}
.notif-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 18px;
}
.notif-filter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(5, 16, 29, 0.9);
  color: #dcefff;
  text-decoration: none;
  font-size: 12px;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}
.notif-filter--active {
  color: #001620;
  background: #00eeff;
  border-color: rgba(0, 238, 255, 0.45);
}
.notif-list {
  display: grid;
  gap: 12px;
}
.notif-item {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 16px 18px;
}
.notif-item--new {
  border-left: 3px solid #00eeff;
}
.notif-item__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 6px;
}
.notif-item__title {
  margin: 0;
  font-size: 20px;
  color: #eefbff;
}
.notif-item__time {
  font-size: 12px;
  color: rgba(170, 200, 225, 0.74);
  white-space: nowrap;
}
.notif-item__meta {
  font-size: 11px;
  color: #7edfff;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.notif-item__summary {
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
  margin-bottom: 10px;
}
.notif-item__actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}
.notif-item__actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 8px 10px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
  font-size: 12px;
}
.notif-empty {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
  color: rgba(170, 200, 225, 0.74);
  font-style: italic;
}
@media (max-width: 840px) {
  .notif-stats {
    grid-template-columns: 1fr;
  }
  .notif-item__top {
    flex-direction: column;
  }
}
</style>
HTML;

echo "<div class='notif-shell'>";
echo "<section class='notif-hero'>";
echo "<div class='notif-eyebrow'>Unified Inbox</div>";
echo "<h1 class='notif-title'>Notifications Center</h1>";
echo "<p class='notif-copy'>Messages and captain-log activity are collected here so players can see what changed since their last check-in without jumping between mail and log screens.</p>";
echo "</section>";

echo "<div class='notif-stats'>";
echo "<div class='notif-stat'><span class='notif-stat__label'>New Messages</span><span class='notif-stat__value'>" . (int) $counts['messages'] . "</span></div>";
echo "<div class='notif-stat'><span class='notif-stat__label'>New Activity</span><span class='notif-stat__value'>" . (int) $counts['activity'] . "</span></div>";
echo "<div class='notif-stat'><span class='notif-stat__label'>Total Unread</span><span class='notif-stat__value'>" . (int) $counts['total'] . "</span></div>";
echo "</div>";

$filters = array(
    'all' => 'All',
    'unread' => 'Unread',
    'messages' => 'Messages',
    'activity' => 'Activity',
);

echo "<nav class='notif-toolbar'>";
foreach ($filters as $key => $label) {
    $class = ($filter === $key) ? 'notif-filter notif-filter--active' : 'notif-filter';
    echo "<a class='{$class}' href='notifications.php?filter=" . rawurlencode($key) . "'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</a>";
}
echo "<a class='notif-filter' href='readmail.php'>Open Mail</a>";
echo "<a class='notif-filter' href='log.php'>Open Captain Log</a>";
echo "</nav>";

if (empty($items)) {
    echo "<div class='notif-empty'>Nothing to show for this filter.</div>";
} else {
    echo "<div class='notif-list'>";
    foreach ($items as $item) {
        $newClass = !empty($item['is_new']) ? ' notif-item--new' : '';
        $sourceLabel = ($item['source'] === 'message') ? 'Message' : 'Activity';
        echo "<article class='notif-item{$newClass}'>";
        echo "<div class='notif-item__top'>";
        echo "<h2 class='notif-item__title'>" . htmlspecialchars((string) $item['title'], ENT_QUOTES, 'UTF-8') . "</h2>";
        echo "<div class='notif-item__time'>" . htmlspecialchars((string) $item['time'], ENT_QUOTES, 'UTF-8') . "</div>";
        echo "</div>";
        echo "<div class='notif-item__meta'>" . htmlspecialchars($sourceLabel . ' · ' . (string) $item['meta'], ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<div class='notif-item__summary'>" . nl2br(htmlspecialchars((string) $item['summary'], ENT_QUOTES, 'UTF-8')) . "</div>";
        echo "<div class='notif-item__actions'>";
        echo "<a href='" . htmlspecialchars((string) $item['url'], ENT_QUOTES, 'UTF-8') . "'>" . (($item['source'] === 'message') ? 'Open Mail' : 'Open Captain Log') . "</a>";
        echo "</div>";
        echo "</article>";
    }
    echo "</div>";
}

echo "</div>";

TEXT_GOTOMAIN();
include "footer.php";
