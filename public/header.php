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
// File: header.php

header("Content-type: text/html; charset=utf-8");
header("X-UA-Compatible: IE=Edge, chrome=1");
header("Cache-Control: public"); // Tell the client (and any caches) that this information can be stored in public caches.
header("Connection: Keep-Alive"); // Tell the client to keep going until it gets all data, please.
header("Vary: Accept-Encoding, Accept-Language");
header("Keep-Alive: timeout=15, max=100");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: same-origin");
if (!isset($body_class))
{
    $body_class = "bnt";
}

if (!defined('BNT_HEADER_RENDERED'))
{
    define('BNT_HEADER_RENDERED', true);
}

?>
<!DOCTYPE html>
<html lang="<?php echo $l->get('l_lang_attribute'); ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="Description" content="A free online game - Open source, web game, with multiplayer space exploration">
<meta name="Keywords" content="Free, online, game, Open source, web game, multiplayer, space, exploration, blacknova, traders">
<meta name="Rating" content="General">
<link rel="shortcut icon" href="images/bntfavicon.ico">
<title><?php global $title; echo $title; ?></title>
<link rel='stylesheet' type='text/css' href='templates/classic/styles/main.css'>
<style>
.bnt-motd {
  width: min(1100px, calc(100% - 24px));
  margin: 12px auto 16px;
  border: 1px solid #785400;
  background: linear-gradient(180deg, rgba(56, 29, 0, 0.94), rgba(18, 10, 0, 0.98));
  box-shadow: 0 0 18px rgba(255, 178, 0, 0.18);
}

.bnt-motd__inner {
  padding: 12px 16px 14px;
  border-left: 4px solid #ffb200;
}

.bnt-motd__eyebrow {
  color: #ffcc4d;
  font-size: 11px;
  letter-spacing: 0.18em;
  font-weight: bold;
  margin-bottom: 6px;
}

.bnt-motd__title {
  margin: 0 0 8px;
  color: #fff1b5;
  font-size: 22px;
}

.bnt-motd__body {
  color: #fff8e1;
  line-height: 1.5;
  font-size: 14px;
}

.bnt-nav-actions {
  margin: 18px 0 8px;
}

.bnt-nav-button {
  display: inline-block;
  padding: 8px 12px;
  border: 1px solid rgba(90, 185, 255, 0.35);
  background: rgba(14, 35, 61, 0.92);
  color: #eaf8ff;
  text-decoration: none;
  font-size: 13px;
}

.bnt-nav-button:hover {
  background: rgba(29, 63, 105, 0.96);
}
</style>
<?php bnt_render_addon_hook('page_head', array('page' => basename($_SERVER['PHP_SELF'] ?? ''))); ?>
</head>
<body class="<?php echo $body_class; ?>">
<?php bnt_render_motd(); ?>
<?php bnt_render_addon_hook('page_top', array('page' => basename($_SERVER['PHP_SELF'] ?? ''))); ?>
<div class="bnt-page">
