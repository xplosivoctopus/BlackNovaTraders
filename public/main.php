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
// File: main.php

include "config/config.php";

// New database driven language entries
load_languages($db, $lang, array('common', 'global_includes'), $langvars, $db_logging);

updatecookie();

if (checklogin())
{
    die();
}

$title = $l->get('l_main_title');
include "header.php";

$stylefontsize = "12Pt";
$picsperrow = 7;

$res = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE email=?", array($username));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
$playerinfo = $res->fields;

$liveRankingData = bnt_get_ranked_player_row((int) $playerinfo['ship_id'], false);
if ($liveRankingData !== null) {
    $playerinfo['score'] = (int) $liveRankingData['live_score'];
}

if ($playerinfo['cleared_defences'] > ' ')
{
    echo $l->get('l_incompletemove') . " <br>";
    echo "<a href=\"" . htmlspecialchars($playerinfo['cleared_defences'], ENT_QUOTES, 'UTF-8') . "\">" . $l->get('l_clicktocontinue') . "</a>";
    die();
}

$res = $db->Execute("SELECT * FROM {$db->prefix}universe WHERE sector_id=?", array($playerinfo['sector']));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
$sectorinfo = $res->fields;

if ($playerinfo['on_planet'] == "Y")
{
    $res2 = $db->Execute("SELECT planet_id, owner FROM {$db->prefix}planets WHERE planet_id=?", array($playerinfo['planet_id']));
    db_op_result ($db, $res2, __LINE__, __FILE__, $db_logging);
    if ($res2->RecordCount() != 0)
    {
        echo "<a href=planet.php?planet_id=$playerinfo[planet_id]>" . $l->get('l_clickme') . "</a> " . $l->get('l_toplanetmenu') . "    <br>";
        header("Location: planet.php?planet_id=" . $playerinfo['planet_id'] . "&id=" . $playerinfo['ship_id']);
        die();
    }
    else
    {
        $db->Execute("UPDATE {$db->prefix}ships SET on_planet='N' WHERE ship_id=?", array($playerinfo['ship_id']));
        echo "<br>" . $l->get('l_nonexistant_pl') . "<br><br>";
    }
}

$res = $db->Execute("SELECT * FROM {$db->prefix}links WHERE link_start=? ORDER BY link_dest ASC", array($playerinfo['sector']));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);

$i = 0;
if ($res != false)
{
    while (!$res->EOF)
    {
        $links[$i] = $res->fields['link_dest'];
        $i++;
        $res->MoveNext();
    }
}
$num_links = $i;

$res = $db->Execute("SELECT * FROM {$db->prefix}planets WHERE sector_id=?", array($playerinfo['sector']));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);

$i = 0;
if ($res != false)
{
    while (!$res->EOF)
    {
        $planets[$i] = $res->fields;
        $i++;
        $res->MoveNext();
    }
}
$num_planets = $i;

$res = $db->Execute("SELECT * FROM {$db->prefix}sector_defence,{$db->prefix}ships WHERE {$db->prefix}sector_defence.sector_id=?
                                                    AND {$db->prefix}ships.ship_id = {$db->prefix}sector_defence.ship_id ", array($playerinfo['sector']));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);

$i = 0;
if ($res != false)
{
    while (!$res->EOF)
    {
        $defences[$i] = $res->fields;
        $i++;
        $res->MoveNext();
    }
}
$num_defences = $i;

$res = $db->Execute("SELECT zone_id,zone_name FROM {$db->prefix}zones WHERE zone_id=?", array($sectorinfo['zone_id']));
db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
$zoneinfo = $res->fields;

$shiptypes[0]= "tinyship.png";
$shiptypes[1]= "smallship.png";
$shiptypes[2]= "mediumship.png";
$shiptypes[3]= "largeship.png";
$shiptypes[4]= "hugeship.png";

$planettypes[0]= "tinyplanet.png";
$planettypes[1]= "smallplanet.png";
$planettypes[2]= "mediumplanet.png";
$planettypes[3]= "largeplanet.png";
$planettypes[4]= "hugeplanet.png";

$signame = player_insignia_name ($db, $username);
$isAdminUser = bnt_is_admin_user($playerinfo);
$adminMenuLabel = 'ADMIN';
if ($isAdminUser)
{
    foreach ($admin_list as $admin)
    {
        if (isset($admin['character']) && strcasecmp($admin['character'], $playerinfo['character_name']) === 0)
        {
            $adminMenuLabel = strtoupper($admin['level'] ?? $adminMenuLabel);
            break;
        }
    }
}

// --- Pre-render data setup ---

// Normalize sector beacon
if (empty($sectorinfo['beacon']) || strlen(trim($sectorinfo['beacon'])) <= 0) {
    $sectorinfo['beacon'] = null;
}

// Resolve zone name
if ($zoneinfo['zone_id'] < 5) {
    $zonevar = "l_zname_" . $zoneinfo['zone_id'];
    $zoneinfo['zone_name'] = $$zonevar;
}

// Format stats
$ply_turns     = NUMBER($playerinfo['turns']);
$ply_turnsused = NUMBER($playerinfo['turns_used']);
$ply_score     = NUMBER($playerinfo['score']);
$ply_credits   = NUMBER($playerinfo['credits']);

// Unified notifications summary
$notification_counts = bnt_get_notification_counts((int) $playerinfo['ship_id']);
$notification_total = (int) ($notification_counts['total'] ?? 0);
$notification_summary = '';
if ($notification_total > 0) {
    $notification_parts = array();
    if (!empty($notification_counts['messages'])) {
        $notification_parts[] = (int) $notification_counts['messages'] . ' message' . (((int) $notification_counts['messages'] === 1) ? '' : 's');
    }
    if (!empty($notification_counts['activity'])) {
        $notification_parts[] = (int) $notification_counts['activity'] . ' activity update' . (((int) $notification_counts['activity'] === 1) ? '' : 's');
    }
    $notification_summary = implode(' and ', $notification_parts);
}

// Trade routes queries
$i = 0;
$num_traderoutes = 0;
$traderoutes = [];

$query = $db->Execute("SELECT * FROM {$db->prefix}traderoutes WHERE source_type=? AND source_id=? AND owner=? ORDER BY dest_id ASC;", array("P", $playerinfo['sector'], $playerinfo['ship_id']) );
db_op_result ($db, $query, __LINE__, __FILE__, $db_logging);
while (!$query->EOF) { $traderoutes[$i++] = $query->fields; $num_traderoutes++; $query->MoveNext(); }

$query = $db->Execute("SELECT * FROM {$db->prefix}traderoutes WHERE source_type='D' AND source_id=$playerinfo[sector] AND owner=$playerinfo[ship_id] ORDER BY dest_id ASC");
db_op_result ($db, $query, __LINE__, __FILE__, $db_logging);
while (!$query->EOF) { $traderoutes[$i++] = $query->fields; $num_traderoutes++; $query->MoveNext(); }

$query = $db->Execute("SELECT * FROM {$db->prefix}planets, {$db->prefix}traderoutes WHERE source_type='L' AND source_id={$db->prefix}planets.planet_id AND {$db->prefix}planets.sector_id=$playerinfo[sector] AND {$db->prefix}traderoutes.owner=$playerinfo[ship_id]");
db_op_result ($db, $query, __LINE__, __FILE__, $db_logging);
while (!$query->EOF) { $traderoutes[$i++] = $query->fields; $num_traderoutes++; $query->MoveNext(); }

$query = $db->Execute("SELECT * FROM {$db->prefix}planets, {$db->prefix}traderoutes WHERE source_type='C' AND source_id={$db->prefix}planets.planet_id AND {$db->prefix}planets.sector_id=$playerinfo[sector] AND {$db->prefix}traderoutes.owner=$playerinfo[ship_id]");
db_op_result ($db, $query, __LINE__, __FILE__, $db_logging);
while (!$query->EOF) { $traderoutes[$i++] = $query->fields; $num_traderoutes++; $query->MoveNext(); }

// Ships in sector scan
$ships_detected = 0;
$ship_detected = null;
if ($playerinfo['sector'] != 0) {
    $sql  = "SELECT {$db->prefix}ships.*, {$db->prefix}teams.team_name, {$db->prefix}teams.id ";
    $sql .= "FROM {$db->prefix}ships LEFT OUTER JOIN {$db->prefix}teams ON {$db->prefix}ships.team = {$db->prefix}teams.id ";
    $sql .= "WHERE {$db->prefix}ships.ship_id<>$playerinfo[ship_id] AND {$db->prefix}ships.sector=$playerinfo[sector] AND {$db->prefix}ships.on_planet='N' ";
    $sql .= "ORDER BY RAND();";
    $result4 = $db->Execute($sql);
    db_op_result ($db, $result4, __LINE__, __FILE__, $db_logging);
    if ($result4 != false) {
        while (!$result4->EOF) {
            $row = $result4->fields;
            $success = SCAN_SUCCESS($playerinfo['sensors'], $row['cloak']);
            if ($success < 5)  $success = 5;
            if ($success > 95) $success = 95;
            $roll = mt_rand(1, 100);
            if ($roll < $success) {
                $shipavg = get_avg_tech($row, "ship");
                if ($shipavg < 8)      $shiplevel = 0;
                elseif ($shipavg < 12) $shiplevel = 1;
                elseif ($shipavg < 16) $shiplevel = 2;
                elseif ($shipavg < 20) $shiplevel = 3;
                else                   $shiplevel = 4;
                $row['shiplevel'] = $shiplevel;
                $ship_detected[] = $row;
                $ships_detected++;
            }
            $result4->MoveNext();
        }
    }
}
?>
<style>
/* ============================================================
   BLACKNOVA TRADERS — COCKPIT COMMAND INTERFACE
   Main Dashboard v3.0
   ============================================================ */

.ck-wrap {
  width: 98%;
  max-width: 1440px;
  margin: 0 auto 16px;
  display: flex;
  flex-direction: column;
  gap: 6px;
}

/* ── HUD Top Bar ─────────────────────────────────────────── */
.ck-hud {
  display: flex;
  align-items: stretch;
  flex-wrap: wrap;
  background: linear-gradient(90deg, rgba(2,8,18,0.99), rgba(6,18,30,0.99));
  border: 1px solid var(--border-mid);
  border-top: 2px solid var(--cyan);
  box-shadow: 0 0 24px rgba(0,238,255,0.08), 0 4px 16px rgba(0,0,0,0.6);
  position: relative;
  overflow: hidden;
}

.ck-hud::after {
  content: '';
  position: absolute;
  inset: 0;
  background: repeating-linear-gradient(
    0deg, transparent, transparent 2px,
    rgba(0,238,255,0.014) 2px, rgba(0,238,255,0.014) 3px
  );
  pointer-events: none;
  z-index: 0;
}

.ck-hud > * { position: relative; z-index: 1; }

.ck-hud-identity {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: 10px;
  padding: 8px 16px;
  flex: 1;
  border-right: 1px solid var(--border);
  min-width: 0;
}

.ck-hud-insignia {
  font-family: var(--font-hud);
  font-size: 9px;
  color: var(--violet);
  letter-spacing: 0.1em;
  flex-shrink: 0;
}

.ck-hud-pipe { color: var(--text-muted); flex-shrink: 0; }

.ck-hud-character {
  font-family: var(--font-hud);
  font-size: 14px;
  font-weight: 700;
  color: var(--text-bright);
  letter-spacing: 0.04em;
  white-space: nowrap;
}

.ck-hud-aboard {
  font-family: var(--font-hud);
  font-size: 9px;
  letter-spacing: 0.18em;
  color: #7ab0cc;
  flex-shrink: 0;
}

.ck-hud-ship {
  font-family: var(--font-hud);
  font-size: 11px;
  font-weight: 600;
  color: var(--cyan);
  text-decoration: none;
  letter-spacing: 0.08em;
  flex: 1 1 220px;
  min-width: 0;
  white-space: normal;
  overflow-wrap: anywhere;
  line-height: 1.2;
  transition: color 0.2s, text-shadow 0.2s;
}

.ck-hud-ship:hover { color: #fff; text-shadow: 0 0 12px var(--cyan); }

.ck-admin-badge {
  font-family: var(--font-hud);
  font-size: 9px;
  color: #08111a;
  background: linear-gradient(135deg, #f7d046, #f59e0b);
  border: 1px solid rgba(255, 214, 10, 0.55);
  border-radius: 999px;
  padding: 3px 8px 2px;
  letter-spacing: 0.14em;
  font-weight: 800;
  box-shadow: 0 0 14px rgba(245,158,11,0.22);
  flex-shrink: 0;
}

.ck-hud-stats { display: flex; border-right: 1px solid var(--border); }

.ck-stat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4px 14px;
  border-right: 1px solid var(--border);
  min-width: 80px;
}
.ck-stat:last-child { border-right: none; }

.ck-stat-lbl {
  font-family: var(--font-hud);
  font-size: 9px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #7ab0cc;
  line-height: 1;
  margin-bottom: 2px;
}

.ck-stat-val {
  font-family: var(--font-hud);
  font-size: 15px;
  font-weight: 800;
  color: var(--cyan);
  text-shadow: 0 0 10px rgba(0,238,255,0.6);
  line-height: 1;
}

.ck-hud-loc {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 6px 16px;
}

.ck-hud-cargo {
  width: 100%;
  display: grid;
  grid-template-columns: repeat(5, minmax(0, 1fr));
  gap: 8px;
  padding: 8px 14px 10px;
  border-top: 1px solid var(--border);
  background: rgba(0, 238, 255, 0.03);
}

.ck-hud-cargo-item {
  display: flex;
  align-items: center;
  gap: 8px;
  min-width: 0;
  padding: 6px 8px;
  background: rgba(4, 18, 34, 0.72);
  border: 1px solid var(--border);
  border-radius: 6px;
}

.ck-hud-cargo-item img {
  width: 16px;
  height: 16px;
  flex-shrink: 0;
  opacity: 0.9;
}

.ck-hud-cargo-copy {
  min-width: 0;
  flex: 1;
}

.ck-hud-cargo-lbl {
  display: block;
  font-size: 9px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #cfe7f5;
  font-weight: 600;
}

.ck-hud-cargo-val {
  display: block;
  font-family: var(--font-hud);
  font-size: 11px;
  color: var(--text-prime);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.ck-loc-item { display: flex; flex-direction: column; align-items: center; }

.ck-loc-lbl {
  font-family: var(--font-hud);
  font-size: 9px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: #7ab0cc;
  line-height: 1;
}

.ck-loc-val {
  font-family: var(--font-hud);
  font-size: 20px;
  font-weight: 900;
  color: var(--amber);
  text-shadow: 0 0 12px rgba(245,158,11,0.55);
  line-height: 1.1;
}

.ck-beacon {
  font-family: var(--font-hud);
  font-size: 9px;
  color: var(--green-hot);
  letter-spacing: 0.1em;
  border: 1px solid rgba(0,255,136,0.25);
  border-radius: 3px;
  padding: 2px 7px;
  background: rgba(0,255,136,0.05);
  text-shadow: 0 0 8px rgba(0,255,136,0.4);
}

.ck-zone-link {
  font-family: var(--font-hud);
  font-size: 9px;
  color: var(--violet);
  text-decoration: none;
  letter-spacing: 0.1em;
  border: 1px solid var(--violet-dim);
  border-radius: 3px;
  padding: 3px 9px;
  background: var(--violet-dim);
  transition: all 0.2s;
  white-space: nowrap;
}
.ck-zone-link:hover { color: #fff; border-color: var(--violet-glow); box-shadow: 0 0 12px var(--violet-dim); }

.ck-msg-alert {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  padding: 6px 16px;
  background: rgba(191,95,255,0.08);
  border: 1px solid var(--violet-dim);
  font-family: var(--font-hud);
  font-size: 10px;
  letter-spacing: 0.12em;
  color: var(--violet);
  animation: borderBreath 2s ease-in-out infinite;
}
.ck-msg-alert a { color: var(--cyan); text-decoration: none; }
.ck-msg-alert a:hover { color: #fff; }

/* ── 3-Column Grid ───────────────────────────────────────── */
.ck-grid {
  display: grid;
  grid-template-columns: 188px 1fr 196px;
  gap: 6px;
  align-items: start;
}

.ck-side-stack {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

/* ── Panel Base ──────────────────────────────────────────── */
.ck-panel {
  background: linear-gradient(160deg, rgba(6,20,36,0.97), rgba(3,12,22,0.99));
  border: 1px solid var(--border-mid);
  box-shadow: 0 0 16px rgba(0,238,255,0.05), 0 6px 20px rgba(0,0,0,0.45);
  position: relative;
}

.ck-panel::before, .ck-panel::after {
  content: '';
  position: absolute;
  width: 10px;
  height: 10px;
  pointer-events: none;
  z-index: 1;
}
.ck-panel::before { top:0; left:0; border-top:2px solid var(--cyan-mid); border-left:2px solid var(--cyan-mid); }
.ck-panel::after  { bottom:0; right:0; border-bottom:2px solid var(--cyan-mid); border-right:2px solid var(--cyan-mid); }

/* ── Section Headers ─────────────────────────────────────── */
.ck-sec {
  font-family: var(--font-hud);
  font-size: 10px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #9ecbe3;
  padding: 5px 8px 4px 10px;
  background: linear-gradient(90deg, rgba(0,238,255,0.05) 0%, transparent 80%);
  border-bottom: 1px solid var(--border);
  border-left: 3px solid var(--cyan-mid);
}

/* ── Command Menu ────────────────────────────────────────── */
.ck-nav { padding: 3px 0; }

.ck-cmd {
  display: flex;
  align-items: center;
  padding: 4px 8px 4px 10px;
  font-family: var(--font-body);
  font-size: 11.5px;
  color: var(--text-prime);
  text-decoration: none;
  transition: background 0.12s, color 0.12s, padding-left 0.12s;
  white-space: nowrap;
  gap: 5px;
}
.ck-cmd::before { content:'›'; font-size:14px; line-height:1; color:rgba(0,238,255,0.35); flex-shrink:0; transition:color 0.12s; }
.ck-cmd:hover { background:rgba(0,238,255,0.05); color:var(--cyan); padding-left:14px; }
.ck-cmd:hover::before { color:var(--cyan); }

.ck-cmd-danger { color:#e06070; }
.ck-cmd-danger::before { color:rgba(255,51,85,0.4); }
.ck-cmd-danger:hover { background:rgba(255,51,85,0.06); color:var(--red-hot); }
.ck-cmd-danger:hover::before { color:var(--red-hot); }

.ck-divider { height:1px; background:var(--border); margin:3px 0; }

/* ── Trade Routes ────────────────────────────────────────── */
.ck-trade-item {
  display: block;
  padding: 3px 8px 3px 10px;
  font-size: 10.5px;
  color: var(--text-prime);
  text-decoration: none;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  transition: background 0.12s, color 0.12s;
}
.ck-trade-item:hover { background:rgba(0,238,255,0.05); color:var(--cyan); }
.ck-trade-none { padding:4px 10px; font-size:10px; color:var(--text-muted); font-style:italic; }

/* ── Center: Port ────────────────────────────────────────── */
.ck-port {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
  background: rgba(0,238,255,0.025);
}

.ck-port-lbl { font-family:var(--font-hud); font-size:9px; letter-spacing:0.12em; text-transform:uppercase; color:#7ab0cc; }
.ck-port-name { font-family:var(--font-hud); font-size:14px; font-weight:700; color:var(--green-hot); text-shadow:0 0 10px rgba(0,255,136,0.45); }
.ck-port-none { padding:7px 12px; font-size:10px; color:var(--text-muted); border-bottom:1px solid var(--border); }

.ck-port-img {
  width: 88px; height: 62px; object-fit: cover;
  border: 1px solid var(--border-mid); border-radius: 4px; cursor: pointer;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.ck-port-img:hover { border-color:var(--border-hot); box-shadow:0 0 16px rgba(0,238,255,0.2); }

/* ── Center: Sector Objects ──────────────────────────────── */
.ck-objects { padding: 8px 10px; border-bottom: 1px solid var(--border); }
.ck-objects:last-child { border-bottom: none; }

.ck-obj-hdr { display:flex; align-items:center; gap:8px; margin-bottom:6px; }
.ck-obj-hdr-lbl { font-family:var(--font-hud); font-size:10px; letter-spacing:0.12em; text-transform:uppercase; color:#9ecbe3; }
.ck-obj-hdr-count { font-family:var(--font-hud); font-size:9px; color:var(--cyan-mid); background:rgba(0,238,255,0.05); border:1px solid var(--border); border-radius:10px; padding:1px 7px; }

.ck-obj-grid { display:flex; flex-wrap:wrap; gap:5px; }

.ck-planet-card {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 6px 5px; background: rgba(4,14,26,0.75); border: 1px solid var(--border);
  border-radius: 5px; width: 80px; text-align: center; text-decoration: none;
  transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
}
.ck-planet-card:hover { border-color:var(--border-mid); background:rgba(6,23,38,0.9); box-shadow:0 0 12px rgba(0,238,255,0.07); }
.ck-planet-card img { width:50px; height:57px; object-fit:contain; display:block; }
.ck-planet-name { font-size:9px; color:var(--text-prime); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }
.ck-planet-owner { font-size:8px; color:var(--text-dim); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }

.ck-ships-detect-hdr {
  font-family: var(--font-hud); font-size: 8px; letter-spacing: 0.2em; color: var(--amber);
  padding: 3px 8px; background: rgba(245,158,11,0.05); border: 1px solid rgba(245,158,11,0.12);
  border-radius: 3px; margin-bottom: 6px;
}

.ck-ship-card {
  display: flex; flex-direction: column; align-items: center; gap: 2px;
  padding: 6px 5px; background: rgba(4,14,26,0.75); border: 1px solid var(--border);
  border-radius: 5px; width: 108px; text-align: center;
  transition: border-color 0.18s, background 0.18s;
}
.ck-ship-card:hover { border-color:var(--border-mid); background:rgba(6,23,38,0.9); }
.ck-ship-card img { width:64px; height:48px; object-fit:contain; }
.ck-ship-name { font-size:9px; color:var(--text-prime); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }
.ck-ship-pilot { font-size:8px; color:var(--amber); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }
.ck-ship-team  { font-size:8px; color:var(--green-hot); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; width:100%; }

.ck-def-card {
  display: flex; flex-direction: column; align-items: center; gap: 4px;
  padding: 6px; background: rgba(4,14,26,0.75); border: 1px solid rgba(255,51,85,0.18);
  border-radius: 5px; width: 108px; text-align: center;
}
.ck-def-card img { max-width:48px; height:auto; }
.ck-def-info { font-size:9px; color:var(--text-prime); }

.ck-obj-none { font-size:10px; color:var(--text-muted); font-style:italic; padding:2px 0 4px; }

/* ── Right: Nav Computer ─────────────────────────────────── */
.ck-nav-row { display:flex; align-items:center; justify-content:space-between; padding:3px 8px 3px 10px; gap:5px; }
.ck-nav-dest { font-family:var(--font-hud); font-size:11.5px; color:var(--cyan); text-decoration:none; transition:color 0.15s, text-shadow 0.15s; }
.ck-nav-dest:hover { color:#fff; text-shadow:0 0 10px var(--cyan); }
.ck-nav-set { font-size:9px; color:var(--text-muted); text-decoration:none; border:1px solid var(--border); border-radius:2px; padding:1px 5px; flex-shrink:0; transition:all 0.15s; }
.ck-nav-set:hover { color:var(--text-prime); border-color:var(--border-mid); }

/* ── Right: Warp Links ───────────────────────────────────── */
.ck-warp-row { display:flex; align-items:center; justify-content:space-between; padding:3px 8px 3px 10px; }
.ck-warp-dest { font-family:var(--font-hud); font-size:11.5px; color:var(--green-hot); text-decoration:none; transition:color 0.15s, text-shadow 0.15s; }
.ck-warp-dest:hover { color:#fff; text-shadow:0 0 10px var(--green-hot); }
.ck-warp-scan { font-size:9px; color:var(--text-muted); text-decoration:none; border:1px solid var(--border); border-radius:2px; padding:1px 5px; flex-shrink:0; transition:all 0.15s; }
.ck-warp-scan:hover { color:var(--cyan); border-color:var(--border-mid); }
.ck-warp-none { padding:4px 10px; font-size:10px; color:var(--text-muted); font-style:italic; }
.ck-fullscan { display:block; padding:5px 10px; font-size:10px; color:var(--violet); text-decoration:none; border-top:1px solid var(--border); transition:background 0.15s, color 0.15s; }
.ck-fullscan:hover { background:var(--violet-dim); color:#fff; }
@media (max-width: 1100px) {
  .ck-hud-cargo { grid-template-columns: repeat(3, minmax(0, 1fr)); }
}
@media (max-width: 720px) {
  .ck-hud-cargo { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
</style>

<?php

// =====================================================================
// COCKPIT HTML OUTPUT
// =====================================================================

echo "<div class='ck-wrap'>\n";

// ── HUD TOP BAR ──────────────────────────────────────────────────────
echo "<div class='ck-hud'>\n";

echo "  <div class='ck-hud-identity'>\n";
if ($signame) {
    echo "    <span class='ck-hud-insignia'>" . htmlspecialchars($signame) . "</span>\n";
    echo "    <span class='ck-hud-pipe'>|</span>\n";
}
echo "    <span class='ck-hud-character'>" . htmlspecialchars($playerinfo['character_name']) . "</span>\n";
if ($isAdminUser) {
    echo "    <span class='ck-admin-badge'>" . htmlspecialchars($adminMenuLabel) . "</span>\n";
}
echo "    <span class='ck-hud-aboard'>ABOARD</span>\n";
echo "    <a class='ck-hud-ship new_link' href='report.php'>" . htmlspecialchars($playerinfo['ship_name']) . "</a>\n";
echo "  </div>\n";

echo "  <div class='ck-hud-stats'>\n";
echo "    <div class='ck-stat'><span class='ck-stat-lbl'>" . $l->get('l_turns_have') . "</span><span class='ck-stat-val'>{$ply_turns}</span></div>\n";
echo "    <div class='ck-stat'><span class='ck-stat-lbl'>" . $l->get('l_turns_used') . "</span><span class='ck-stat-val'>{$ply_turnsused}</span></div>\n";
echo "    <div class='ck-stat'><span class='ck-stat-lbl'>" . $l->get('l_score') . "</span><span class='ck-stat-val'>{$ply_score}</span></div>\n";
echo "    <div class='ck-stat'><span class='ck-stat-lbl'>" . $l->get('l_credits') . "</span><span class='ck-stat-val'>{$ply_credits}</span></div>\n";
echo "  </div>\n";

echo "  <div class='ck-hud-loc'>\n";
echo "    <div class='ck-loc-item'>\n";
echo "      <span class='ck-loc-lbl'>" . $l->get('l_sector') . "</span>\n";
echo "      <span class='ck-loc-val'>{$playerinfo['sector']}</span>\n";
echo "    </div>\n";
if ($sectorinfo['beacon']) {
    echo "    <span class='ck-beacon'>" . htmlspecialchars($sectorinfo['beacon']) . "</span>\n";
}
echo "    <a class='ck-zone-link new_link' href='zoneinfo.php?zone={$zoneinfo['zone_id']}' data-modal-fetch='1' data-modal-title='" . htmlspecialchars($zoneinfo['zone_name']) . "'>" . htmlspecialchars($zoneinfo['zone_name']) . "</a>\n";
echo "  </div>\n";
echo "  <div class='ck-hud-cargo'>\n";
$cargo_items = [
    [$l->get('l_ore'),       'ore.png',       'ship_ore'],
    [$l->get('l_organics'),  'organics.png',  'ship_organics'],
    [$l->get('l_goods'),     'goods.png',     'ship_goods'],
    [$l->get('l_energy'),    'energy.png',    'ship_energy'],
    [$l->get('l_colonists'), 'colonists.png', 'ship_colonists'],
];
foreach ($cargo_items as [$clbl, $cicon, $cfield]) {
    $cval = NUMBER($playerinfo[$cfield]);
    echo "    <div class='ck-hud-cargo-item'>\n";
    echo "      <img src='images/{$cicon}' alt='" . htmlspecialchars($clbl) . "'>\n";
    echo "      <div class='ck-hud-cargo-copy'>\n";
    echo "        <span class='ck-hud-cargo-lbl'>{$clbl}</span>\n";
    echo "        <span class='ck-hud-cargo-val'>{$cval}</span>\n";
    echo "      </div>\n";
    echo "    </div>\n";
}
echo "  </div>\n";
echo "</div>\n"; // end ck-hud

if ($notification_total > 0) {
    echo "<div class='ck-msg-alert'>&#9888; You have {$notification_summary} waiting &mdash; <a href='notifications.php'>Open Notifications</a></div>\n";
}

// ── COCKPIT GRID ─────────────────────────────────────────────────────
echo "<div class='ck-grid'>\n";

// ======================================================
// LEFT PANEL — TACTICAL SYSTEMS
// ======================================================
echo "<div class='ck-side-stack'>\n";
echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>" . $l->get('l_commands') . "</div>\n";
echo "  <nav class='ck-nav'>\n";
echo "    <a class='ck-cmd' href='device.php'>" . $l->get('l_devices') . "</a>\n";
echo "    <a class='ck-cmd' href='planet_report.php'>" . $l->get('l_planets') . "</a>\n";
echo "    <a class='ck-cmd' href='log.php'>" . $l->get('l_log') . "</a>\n";
echo "    <a class='ck-cmd' href='defence_report.php'>" . $l->get('l_sector_def') . "</a>\n";
echo "    <a class='ck-cmd' href='ranking.php'>" . $l->get('l_rankings') . "</a>\n";
echo "    <a class='ck-cmd' href='teams.php'>" . $l->get('l_teams') . "</a>\n";
echo "    <a class='ck-cmd' href='navcomp.php'>" . $l->get('l_navcomp') . "</a>\n";
if ($ksm_allowed == true) {
    echo "    <a class='ck-cmd' href='galaxy.php'>" . $l->get('l_map') . "</a>\n";
}
echo "  </nav>\n";
echo "</div>\n";

echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>PERSONAL</div>\n";
echo "  <nav class='ck-nav'>\n";
echo "    <a class='ck-cmd' href='profile.php?ship_id=" . (int) $playerinfo['ship_id'] . "'>Profile</a>\n";
echo "    <a class='ck-cmd' href='options.php'>Edit Profile</a>\n";
echo "    <a class='ck-cmd' href='notifications.php'>Notifications" . ($notification_total > 0 ? " ({$notification_total})" : "") . "</a>\n";
echo "    <a class='ck-cmd' href='contacts.php'>Contacts</a>\n";
echo "    <a class='ck-cmd' href='readmail.php'>" . $l->get('l_read_msg') . "</a>\n";
echo "    <a class='ck-cmd' href='mailto2.php'>" . $l->get('l_send_msg') . "</a>\n";
echo "    <a class='ck-cmd' href='settings.php'>" . $l->get('l_settings') . "</a>\n";
echo "    <a class='ck-cmd' href='options.php'>" . $l->get('l_options') . "</a>\n";
echo "  </nav>\n";
echo "</div>\n";

$playerAddonLinks = bnt_get_addon_nav_links('player');
if (!empty($playerAddonLinks)) {
    echo "<div class='ck-panel'>\n";
    echo "  <div class='ck-sec'>ADDONS</div>\n";
    echo "  <nav class='ck-nav'>\n";
    foreach ($playerAddonLinks as $addonLink) {
        echo "    <a class='ck-cmd' href='" . htmlspecialchars((string) $addonLink['url'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string) $addonLink['label'], ENT_QUOTES, 'UTF-8') . "</a>\n";
    }
    echo "  </nav>\n";
    echo "</div>\n";
}

echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>INFO &amp; SUPPORT</div>\n";
echo "  <nav class='ck-nav'>\n";
echo "    <a class='ck-cmd' href='faq.php'>" . $l->get('l_faq') . "</a>\n";
echo "    <a class='ck-cmd' href='feedback.php'>" . $l->get('l_feedback') . "</a>\n";
if (!empty($link_forums)) {
    echo "    <a class='ck-cmd' href='" . htmlspecialchars($link_forums) . "'>" . $l->get('l_forums') . "</a>\n";
}
echo "  </nav>\n";
echo "</div>\n";

echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>" . $l->get('l_traderoutes') . "</div>\n";

if ($num_traderoutes == 0) {
    echo "  <span class='ck-trade-none'>" . $l->get('l_none') . "</span>\n";
} else {
    for ($ti = 0; $ti < $num_traderoutes; $ti++) {
        if ($traderoutes[$ti]['source_type'] == 'P') {
            $src_lbl = $l->get('l_port');
        } elseif ($traderoutes[$ti]['source_type'] == 'D') {
            $src_lbl = "Def's";
        } else {
            $qp = $db->Execute("SELECT name FROM {$db->prefix}planets WHERE planet_id=?;", array($traderoutes[$ti]['source_id']));
            db_op_result($db, $qp, __LINE__, __FILE__, $db_logging);
            if (!$qp || $qp->RecordCount() == 0) {
                $src_lbl = $l->get('l_unknown');
            } else {
                $pf = $qp->fields;
                $src_lbl = empty($pf['name']) ? $l->get('l_unnamed') : htmlspecialchars($pf['name']);
            }
        }
        if ($traderoutes[$ti]['dest_type'] == 'P') {
            $dst_lbl = $traderoutes[$ti]['dest_id'];
        } elseif ($traderoutes[$ti]['dest_type'] == 'D') {
            $dst_lbl = "Def's " . $traderoutes[$ti]['dest_id'];
        } else {
            $qd = $db->Execute("SELECT name FROM {$db->prefix}planets WHERE planet_id=" . $traderoutes[$ti]['dest_id']);
            db_op_result($db, $qd, __LINE__, __FILE__, $db_logging);
            if (!$qd || $qd->RecordCount() == 0) {
                $dst_lbl = $l->get('l_unknown');
            } else {
                $pf = $qd->fields;
                $dst_lbl = empty($pf['name']) ? $l->get('l_unnamed') : htmlspecialchars($pf['name']);
            }
        }
        $arrow = ($traderoutes[$ti]['circuit'] == '1') ? '=&gt;' : '&lt;=&gt;';
        echo "  <a class='ck-trade-item' href='traderoute.php?engage={$traderoutes[$ti]['traderoute_id']}'>{$src_lbl} {$arrow} {$dst_lbl}</a>\n";
    }
}

echo "  <a class='ck-cmd' href='traderoute.php'>" . $l->get('l_trade_control') . "</a>\n";
echo "</div>\n";

echo "<div class='ck-panel'>\n";
echo "  <nav class='ck-nav'>\n";
echo "    <a class='ck-cmd ck-cmd-danger' href='self_destruct.php'>" . $l->get('l_ohno') . "</a>\n";
echo "    <a class='ck-cmd ck-cmd-danger' href='logout.php'>" . $l->get('l_logout') . "</a>\n";
echo "  </nav>\n";
echo "</div>\n";
echo "</div>\n"; // end left column

// ======================================================
// CENTER PANEL — SECTOR VIEW
// ======================================================
echo "<div class='ck-panel'>\n";

if ($sectorinfo['port_type'] != "none" && strlen($sectorinfo['port_type']) > 0) {
    echo "  <div class='ck-port'>\n";
    echo "    <div>\n";
    echo "      <div class='ck-port-lbl'>" . $l->get('l_tradingport') . "</div>\n";
    echo "      <div class='ck-port-name'>" . ucfirst(t_port($sectorinfo['port_type'])) . "</div>\n";
    echo "    </div>\n";
    echo "    <a href='port.php' data-modal-fetch='1' data-modal-title='" . $l->get('l_tradingport') . "'>\n";
    echo "      <img class='ck-port-img mnu' src='images/space_station_port.png' alt='Space Station Port' title='Dock with Space Port'>\n";
    echo "    </a>\n";
    echo "  </div>\n";
} else {
    echo "  <div class='ck-port-none'>" . $l->get('l_tradingport') . " &mdash; " . $l->get('l_none') . "</div>\n";
}

// Planets
echo "  <div class='ck-objects'>\n";
echo "    <div class='ck-obj-hdr'>\n";
echo "      <span class='ck-obj-hdr-lbl'>" . $l->get('l_planet_in_sec') . " {$sectorinfo['sector_id']}</span>\n";
echo "      <span class='ck-obj-hdr-count'>{$num_planets}</span>\n";
echo "    </div>\n";
if ($num_planets > 0) {
    echo "  <div class='ck-obj-grid'>\n";
    for ($i = 0; $i < $num_planets; $i++) {
        if ($planets[$i]['owner'] != 0) {
            $result5 = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id=?;", array($planets[$i]['owner']));
            db_op_result($db, $result5, __LINE__, __FILE__, $db_logging);
            $planet_owner = $result5->fields;
            $planetavg = get_avg_tech($planet_owner, "planet");
            if ($planetavg < 8)      $planetlevel = 0;
            elseif ($planetavg < 12) $planetlevel = 1;
            elseif ($planetavg < 16) $planetlevel = 2;
            elseif ($planetavg < 20) $planetlevel = 3;
            else                     $planetlevel = 4;
        } else {
            $planetlevel = 0;
        }
        $pname  = empty($planets[$i]['name']) ? $l->get('l_unnamed') : htmlspecialchars($planets[$i]['name']);
        $pownerl = ($planets[$i]['owner'] == 0) ? $l->get('l_unowned') : htmlspecialchars($planet_owner['character_name']);
        echo "    <a class='ck-planet-card' href='planet.php?planet_id={$planets[$i]['planet_id']}'>\n";
        echo "      <img class='mnu' title='Interact with Planet' src='images/{$planettypes[$planetlevel]}' alt='planet'>\n";
        echo "      <span class='ck-planet-name'>{$pname}</span>\n";
        echo "      <span class='ck-planet-owner'>({$pownerl})</span>\n";
        echo "    </a>\n";
    }
    echo "  </div>\n";
} else {
    echo "  <span class='ck-obj-none'>" . $l->get('l_none') . "</span>\n";
}
echo "  </div>\n"; // end planets

// Ships in sector
echo "  <div class='ck-objects'>\n";
echo "    <div class='ck-obj-hdr'>\n";
echo "      <span class='ck-obj-hdr-lbl'>" . $l->get('l_ships_in_sec') . " {$sectorinfo['sector_id']}</span>\n";
if ($ships_detected > 0) {
    echo "      <span class='ck-obj-hdr-count'>{$ships_detected}</span>\n";
}
echo "    </div>\n";
if ($playerinfo['sector'] != 0) {
    if ($ships_detected <= 0) {
        echo "  <span class='ck-obj-none'>" . $l->get('l_none') . "</span>\n";
    } else {
        echo "  <div class='ck-ships-detect-hdr'>" . $l->get('l_main_ships_detected') . "</div>\n";
        echo "  <div class='ck-obj-grid'>\n";
        for ($iPlayer = 0; $iPlayer < $ships_detected; $iPlayer++) {
            $sn = htmlspecialchars($ship_detected[$iPlayer]['ship_name']);
            $cn = htmlspecialchars($ship_detected[$iPlayer]['character_name']);
            $tn = !empty($ship_detected[$iPlayer]['team_name']) ? htmlspecialchars($ship_detected[$iPlayer]['team_name']) : '';
            echo "    <div class='ck-ship-card'>\n";
            echo "      <a href='ship.php?ship_id={$ship_detected[$iPlayer]['ship_id']}'>\n";
            echo "        <img class='mnu' title='Interact with Ship' src='images/{$shiptypes[$ship_detected[$iPlayer]['shiplevel']]}' alt='Ship'>\n";
            echo "      </a>\n";
            echo "      <div class='ck-ship-name'>{$sn}</div>\n";
            echo "      <div class='ck-ship-pilot'>({$cn})</div>\n";
            if ($tn) { echo "      <div class='ck-ship-team'>({$tn})</div>\n"; }
            echo "    </div>\n";
        }
        echo "  </div>\n";
    }
} else {
    echo "  <span class='ck-obj-none'>" . $l->get('l_sector_0') . "</span>\n";
}
echo "  </div>\n"; // end ships

// Defences
if ($num_defences > 0) {
    echo "  <div class='ck-objects'>\n";
    echo "    <div class='ck-obj-hdr'>\n";
    echo "      <span class='ck-obj-hdr-lbl'>" . $l->get('l_sector_def') . "</span>\n";
    echo "      <span class='ck-obj-hdr-count'>{$num_defences}</span>\n";
    echo "    </div>\n";
    echo "    <div class='ck-obj-grid'>\n";
    for ($i = 0; $i < $num_defences; $i++) {
        $defence_id = $defences[$i]['defence_id'];
        if ($defences[$i]['defence_type'] == 'F') {
            $def_img = 'fighters.png';
            $mode = ($defences[$i]['fm_setting'] == 'attack') ? $l->get('l_md_attack') : $l->get('l_md_toll');
            $def_type = $l->get('l_fighters') . $mode;
        } else {
            $def_img = 'mines.png';
            $def_type = $l->get('l_mines');
        }
        $dcname = htmlspecialchars($defences[$i]['character_name']);
        $dqty   = $defences[$i]['quantity'];
        echo "      <div class='ck-def-card'>\n";
        echo "        <a class='new_link' href='modify_defences.php?defence_id={$defence_id}'><img src='images/{$def_img}' alt=''></a>\n";
        echo "        <div class='ck-def-info'>{$dcname}<br>({$dqty} {$def_type})</div>\n";
        echo "      </div>\n";
    }
    echo "    </div>\n";
    echo "  </div>\n";
}

echo "</div>\n"; // end center panel

// ======================================================
// RIGHT COLUMN — NAV + ADMIN
// ======================================================
echo "<div class='ck-side-stack'>\n";
echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>" . $l->get('l_realspace') . "</div>\n";
foreach ([1, 2, 3] as $pn) {
    $pd = $playerinfo["preset{$pn}"];
    echo "  <div class='ck-nav-row'>\n";
    echo "    <a class='ck-nav-dest' href='rsmove.php?engage=1&amp;destination={$pd}'>=&gt; {$pd}</a>\n";
    echo "    <a class='ck-nav-set' href='preset.php'>" . ucwords($l->get('l_set')) . "</a>\n";
    echo "  </div>\n";
}
echo "  <a class='ck-cmd' href='rsmove.php'>=&gt; " . $l->get('l_main_other') . "</a>\n";
echo "</div>\n";

echo "<div class='ck-panel'>\n";
echo "  <div class='ck-sec'>" . $l->get('l_main_warpto') . "</div>\n";
if (!$num_links) {
    echo "  <span class='ck-warp-none'>" . $l->get('l_no_warplink') . "</span>\n";
} else {
    for ($i = 0; $i < $num_links; $i++) {
        echo "  <div class='ck-warp-row'>\n";
        echo "    <a class='ck-warp-dest' href='move.php?sector={$links[$i]}'>=&gt; {$links[$i]}</a>\n";
        echo "    <a class='ck-warp-scan mnu' href='lrscan.php?sector={$links[$i]}' data-scan-sector='{$links[$i]}'>[" . $l->get('l_scan') . "]</a>\n";
        echo "  </div>\n";
    }
}
echo "  <a class='ck-fullscan dis' href='lrscan.php?sector=*' data-modal-fetch='1' data-modal-title='" . $l->get('l_fullscan') . "' data-refresh-on-close='1'>[" . $l->get('l_fullscan') . "]</a>\n";
echo "</div>\n"; // end right panel
if ($isAdminUser) {
    echo "<div class='ck-panel'>\n";
    echo "  <div class='ck-sec'>ADMIN CONSOLE</div>\n";
    echo "  <nav class='ck-nav'>\n";
    echo "    <a class='ck-cmd' href='admin.php'>Dashboard</a>\n";
    echo "    <a class='ck-cmd' href='setup_info.php'>Setup Info</a>\n";
    echo "    <a class='ck-cmd' href='scheduler.php'>Scheduler</a>\n";
    echo "    <a class='ck-cmd' href='perfmon.php'>Performance Monitor</a>\n";
    echo "    <a class='ck-cmd' href='ngai_control.php'>NGAI Control</a>\n";
    echo "    <a class='ck-cmd' href='xenobe_control.php'>Xenobe Control</a>\n";
    echo "    <a class='ck-cmd ck-cmd-danger' href='create_universe.php'>Universe Creation</a>\n";
    $adminAddonLinks = bnt_get_addon_nav_links('admin');
    if (!empty($adminAddonLinks)) {
        echo "    <div class='ck-divider'></div>\n";
        echo "    <div class='ck-sec'>ADDON TOOLS</div>\n";
        foreach ($adminAddonLinks as $addonLink) {
            echo "    <a class='ck-cmd' href='" . htmlspecialchars((string) $addonLink['url'], ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars((string) $addonLink['label'], ENT_QUOTES, 'UTF-8') . "</a>\n";
        }
    }
    echo "  </nav>\n";
    echo "</div>\n";
}
echo "</div>\n"; // end right column
echo "</div>\n"; // end ck-grid
echo "</div>\n"; // end ck-wrap

?>

<style>
#scan-modal .scan-modal-shell {
  position: relative;
  width: min(960px, 92vw);
  height: min(720px, 85vh);
  margin: 5vh auto 0;
  background: linear-gradient(135deg, rgba(10,33,60,0.98) 0%, rgba(5,18,35,0.98) 100%);
  border: 1px solid rgba(0, 238, 255, 0.32);
  box-shadow: 0 0 18px rgba(0, 238, 255, 0.14), 0 16px 40px rgba(0,0,0,0.55);
}
#scan-modal .scan-modal-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: linear-gradient(90deg, rgba(64,0,64,0.95) 0%, rgba(6,23,38,0.98) 100%);
  color: #fff;
  border-bottom: 1px solid rgba(0, 238, 255, 0.22);
}
#scan-modal .scan-modal-body {
  height: calc(100% - 44px);
  overflow: auto;
  background: transparent;
  color: #ddeeff;
  padding: 14px;
  font-size: 14px;
  line-height: 1.65;
}
#scan-modal .scan-modal-body table {
  width: 100%;
  border-collapse: collapse;
}
#scan-modal .scan-modal-content {
  max-width: 760px;
  margin: 0 auto;
}
#scan-modal .scan-modal-port-content {
  max-width: 100%;
}
#scan-modal .scan-modal-port-overview {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
  margin-bottom: 14px;
}
#scan-modal .scan-modal-port-summary {
  padding: 10px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  border-radius: 8px;
  background: rgba(4, 18, 34, 0.72);
}
#scan-modal .scan-modal-port-summary strong {
  display: block;
  color: #ffffff;
  font-family: 'Orbitron', monospace;
  font-size: 14px;
  margin-top: 4px;
}
#scan-modal .scan-modal-port-summary-label {
  display: block;
  color: #7ecfda;
  font-size: 10px;
  letter-spacing: 0.12em;
  text-transform: uppercase;
}
#scan-modal .scan-modal-trade-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}
#scan-modal .scan-modal-trade-card {
  border: 1px solid rgba(0, 238, 255, 0.16);
  border-radius: 10px;
  background: rgba(4, 18, 34, 0.72);
  overflow: hidden;
}
#scan-modal .scan-modal-trade-card-header {
  padding: 12px 14px 10px;
  border-bottom: 1px solid rgba(0, 238, 255, 0.14);
  background: linear-gradient(90deg, rgba(64,0,64,0.36) 0%, rgba(6,23,38,0.1) 100%);
}
#scan-modal .scan-modal-trade-card-header strong {
  display: block;
  color: #fff;
  font-family: 'Orbitron', monospace;
  font-size: 13px;
  letter-spacing: 0.08em;
}
#scan-modal .scan-modal-trade-card-header span {
  display: block;
  color: #8eb8d6;
  font-size: 12px;
  margin-top: 4px;
}
#scan-modal .scan-modal-trade-table th {
  color: #7ecfda;
  font-family: 'Orbitron', monospace;
  font-size: 10px;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}
#scan-modal .scan-modal-trade-table tr + tr {
  border-top: 1px solid rgba(0, 238, 255, 0.08);
}
#scan-modal .scan-modal-trade-meta {
  color: #8eb8d6;
  font-size: 11px;
  margin-top: 2px;
}
#scan-modal .scan-modal-trade-input {
  width: 84px;
  padding: 6px 8px;
  border: 1px solid rgba(0, 238, 255, 0.18);
  border-radius: 6px;
  background: rgba(6, 23, 38, 0.9);
  color: #fff;
  text-align: right;
}
#scan-modal .scan-modal-body td,
#scan-modal .scan-modal-body th {
  padding: 6px 8px;
}
#scan-modal .scan-modal-body a.mnu,
#scan-modal .scan-modal-body a.new_link,
#scan-modal .scan-modal-body a {
  color: #00eeff;
}
#scan-modal .scan-modal-body a:hover {
  color: #fff;
}
#scan-modal .scan-modal-body img {
  max-width: 100%;
}
#scan-modal .scan-modal-body input,
#scan-modal .scan-modal-body select,
#scan-modal .scan-modal-body button {
  max-width: 100%;
}
#scan-modal .scan-modal-form-actions {
  display: flex;
  justify-content: flex-end;
  margin-top: 14px;
}
#scan-modal .scan-modal-primary-button {
  appearance: none;
  border: 1px solid rgba(0, 238, 255, 0.34);
  border-radius: 8px;
  background: linear-gradient(135deg, rgba(8,25,50,0.92) 0%, rgba(10,33,58,0.98) 100%);
  color: #ffffff;
  font-family: 'Orbitron', monospace;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  padding: 10px 18px;
  cursor: pointer;
  box-shadow: 0 0 0 1px rgba(255,255,255,0.03) inset, 0 10px 24px rgba(0,0,0,0.26);
}
#scan-modal .scan-modal-primary-button:hover {
  border-color: rgba(0, 238, 255, 0.58);
  box-shadow: 0 0 14px rgba(0, 238, 255, 0.12), 0 10px 24px rgba(0,0,0,0.3);
}
@media (max-width: 900px) {
  #scan-modal .scan-modal-port-overview,
  #scan-modal .scan-modal-trade-grid {
    grid-template-columns: 1fr;
  }
}
#scan-modal .scan-modal-loading,
#scan-modal .scan-modal-error {
  padding: 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(4, 18, 34, 0.78);
  color: #ddeeff;
}
#scan-modal .scan-modal-error {
  color: #ff9fb0;
}
#scan-modal .scan-modal-cta {
  display: flex;
  justify-content: center;
  margin: 18px 0 6px;
}
#scan-modal .scan-modal-cta-link {
  display: inline-flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: min(420px, 100%);
  padding: 12px 18px;
  border: 1px solid rgba(0, 238, 255, 0.24);
  border-radius: 8px;
  background: linear-gradient(135deg, rgba(8,25,50,0.88) 0%, rgba(10,33,58,0.96) 100%);
  box-shadow: 0 0 0 1px rgba(255,255,255,0.02) inset, 0 10px 24px rgba(0,0,0,0.24);
  text-align: center;
  text-decoration: none;
}
#scan-modal .scan-modal-cta-link:hover {
  border-color: rgba(0, 238, 255, 0.5);
  box-shadow: 0 0 18px rgba(0, 238, 255, 0.12), 0 10px 24px rgba(0,0,0,0.28);
}
#scan-modal .scan-modal-cta-kicker {
  font-family: 'Orbitron', monospace;
  font-size: 10px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: #7ecfda;
}
#scan-modal .scan-modal-cta-main {
  font-family: 'Orbitron', monospace;
  font-size: 14px;
  font-weight: 600;
  letter-spacing: 0.06em;
  color: #ffffff;
}
</style>

<div id="scan-modal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.75); z-index:1000;">
  <div class="scan-modal-shell" role="dialog" aria-modal="true" aria-labelledby="scan-modal-title">
    <div class="scan-modal-header">
      <strong id="scan-modal-title"><?php echo $l->get('l_scan'); ?></strong>
      <button id="scan-modal-close" type="button" style="border:1px solid #fff; background:#500050; color:#fff; padding:4px 10px; cursor:pointer;">Close</button>
    </div>
    <div id="scan-modal-body" class="scan-modal-body"><div class="scan-modal-loading">Loading...</div></div>
  </div>
</div>

<script>
(function () {
  var modal = document.getElementById('scan-modal');
  var body = document.getElementById('scan-modal-body');
  var closeButton = document.getElementById('scan-modal-close');
  var title = document.getElementById('scan-modal-title');
  var lastTrigger = null;
  var activeRequest = null;
  var parser = document.createElement('div');
  var refreshOnClose = false;

  function closeModal() {
    var shouldRefresh = refreshOnClose;
    refreshOnClose = false;
    modal.style.display = 'none';
    body.innerHTML = '';
    if (activeRequest) {
      activeRequest.abort();
      activeRequest = null;
    }
    if (lastTrigger) {
      lastTrigger.focus();
    }
    if (shouldRefresh) {
      window.location.reload();
    }
  }

  function renderModalHtml(html) {
    parser.innerHTML = html;
    body.innerHTML = '';

    while (parser.firstChild) {
      body.appendChild(parser.firstChild);
    }

    var scripts = body.querySelectorAll('script');
    scripts.forEach(function (script) {
      var replacement = document.createElement('script');
      if (script.src) {
        replacement.src = script.src;
      } else {
        replacement.text = script.textContent;
      }

      Array.prototype.forEach.call(script.attributes, function (attribute) {
        replacement.setAttribute(attribute.name, attribute.value);
      });

      script.parentNode.replaceChild(replacement, script);
    });
  }

  function loadModalContent(url, requestOptions) {
    body.innerHTML = '<div class="scan-modal-loading">Loading...</div>';
    modal.style.display = 'block';

    if (activeRequest) {
      activeRequest.abort();
    }

    activeRequest = new XMLHttpRequest();
    activeRequest.open((requestOptions && requestOptions.method) || 'GET', url, true);

    if (requestOptions && requestOptions.method === 'POST') {
      activeRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
    }

    activeRequest.onreadystatechange = function () {
      if (activeRequest.readyState !== 4) {
        return;
      }

      if (activeRequest.status >= 200 && activeRequest.status < 300) {
        renderModalHtml(activeRequest.responseText);
        refreshOnClose = !!(requestOptions && requestOptions.refreshOnClose);
      } else {
        body.innerHTML = '<div class="scan-modal-error">Unable to load content.</div>';
      }
      activeRequest = null;
    };

    activeRequest.send((requestOptions && requestOptions.payload) || null);
  }

  function withModalParam(url) {
    return url + (url.indexOf('?') === -1 ? '?' : '&') + 'modal=1';
  }

  document.addEventListener('click', function (event) {
    var trigger = event.target.closest('a[data-scan-sector], a[data-modal-fetch]');
    if (!trigger) {
      if (event.target === modal) {
        closeModal();
      }
      return;
    }

    event.preventDefault();
    lastTrigger = trigger;
    refreshOnClose = trigger.getAttribute('data-refresh-on-close') === '1';
    if (trigger.hasAttribute('data-scan-sector')) {
      title.textContent = '<?php echo addslashes($l->get('l_scan')); ?>: ' + trigger.getAttribute('data-scan-sector');
    } else {
      title.textContent = trigger.getAttribute('data-modal-title') || trigger.textContent.trim();
    }
    loadModalContent(withModalParam(trigger.href));
  });

  closeButton.addEventListener('click', closeModal);

  body.addEventListener('click', function (event) {
    var modalLink = event.target.closest('a');
    if (!modalLink) {
      return;
    }

    var href = modalLink.getAttribute('href');
    if (!href) {
      return;
    }

    if (href.indexOf('port.php') === -1 && href.indexOf('port2.php') === -1) {
      return;
    }

    event.preventDefault();
    title.textContent = 'Trading Port';
    loadModalContent(withModalParam(modalLink.href));
  });

  body.addEventListener('submit', function (event) {
    var form = event.target;
    var action = form.getAttribute('action') || '';
    if (action.indexOf('port2.php') === -1 && action.indexOf('port.php') === -1) {
      return;
    }

    event.preventDefault();
    title.textContent = 'Trading Port';
    var formData = new FormData(form);
    formData.append('modal', '1');
    var payload = new URLSearchParams(formData).toString();
    loadModalContent(form.action || action, {
      method: (form.method || 'POST').toUpperCase(),
      payload: payload,
      refreshOnClose: action.indexOf('port2.php') !== -1
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape' && modal.style.display === 'block') {
      closeModal();
    }
  });
})();
</script>

<?php

include "footer.php";
?>
