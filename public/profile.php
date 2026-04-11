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
// File: profile.php

include "config/config.php";
updatecookie();

load_languages($db, $lang, array('common', 'global_includes', 'global_funcs', 'footer', 'news', 'teams'), $langvars, $db_logging);

$body_class = 'profile';

if (checklogin()) {
    die();
}

$viewerRes = $db->Execute("SELECT ship_id, character_name, team, sector FROM {$db->prefix}ships WHERE email=? LIMIT 1", array($username));
db_op_result($db, $viewerRes, __LINE__, __FILE__, $db_logging);
$viewer = $viewerRes->fields;

$profileShipId = (int) ($_GET['ship_id'] ?? 0);
$profileName = trim((string) ($_GET['name'] ?? ''));
$rankedPlayerSql = bnt_rankings_base_player_sql(false);

if ($profileShipId > 0) {
    $profileRes = $db->Execute(
        "SELECT ranked.live_score AS score,
                ranked.raw_asset_value,
                ranked.liquid_wealth,
                ranked.planet_count,
                ranked.bounty_total,
                s.ship_id, s.character_name, s.ship_name, s.team, s.turns_used, s.last_login, s.rating, s.sector,
                s.hull, s.engines, s.power, s.computer, s.sensors, s.armor, s.shields, s.beams, s.torp_launchers, s.cloak,
                s.ship_destroyed, t.team_name
           FROM {$db->prefix}ships s
      LEFT JOIN {$db->prefix}teams t ON s.team = t.id
      LEFT JOIN ({$rankedPlayerSql}) ranked ON ranked.ship_id = s.ship_id
          WHERE s.ship_id=? LIMIT 1",
        array($profileShipId)
    );
} elseif ($profileName !== '') {
    $profileRes = $db->Execute(
        "SELECT ranked.live_score AS score,
                ranked.raw_asset_value,
                ranked.liquid_wealth,
                ranked.planet_count,
                ranked.bounty_total,
                s.ship_id, s.character_name, s.ship_name, s.team, s.turns_used, s.last_login, s.rating, s.sector,
                s.hull, s.engines, s.power, s.computer, s.sensors, s.armor, s.shields, s.beams, s.torp_launchers, s.cloak,
                s.ship_destroyed, t.team_name
           FROM {$db->prefix}ships s
      LEFT JOIN {$db->prefix}teams t ON s.team = t.id
      LEFT JOIN ({$rankedPlayerSql}) ranked ON ranked.ship_id = s.ship_id
          WHERE s.character_name=? LIMIT 1",
        array($profileName)
    );
} else {
    $profileRes = $db->Execute(
        "SELECT ranked.live_score AS score,
                ranked.raw_asset_value,
                ranked.liquid_wealth,
                ranked.planet_count,
                ranked.bounty_total,
                s.ship_id, s.character_name, s.ship_name, s.team, s.turns_used, s.last_login, s.rating, s.sector,
                s.hull, s.engines, s.power, s.computer, s.sensors, s.armor, s.shields, s.beams, s.torp_launchers, s.cloak,
                s.ship_destroyed, t.team_name
           FROM {$db->prefix}ships s
      LEFT JOIN {$db->prefix}teams t ON s.team = t.id
      LEFT JOIN ({$rankedPlayerSql}) ranked ON ranked.ship_id = s.ship_id
          WHERE s.ship_id=? LIMIT 1",
        array((int) $viewer['ship_id'])
    );
}
db_op_result($db, $profileRes, __LINE__, __FILE__, $db_logging);

if (!$profileRes || $profileRes->EOF) {
    $title = 'Player Profile';
    include "header.php";
    echo "<div style='width:min(1100px, calc(100% - 24px)); margin:16px auto 28px; color:#dbefff;'>";
    echo "<div style='border-left:3px solid #ff3355; background:rgba(255,51,85,0.08); color:#ff96a8; padding:12px 14px;'>Player profile not found.</div>";
    echo "</div>";
    TEXT_GOTOMAIN();
    include "footer.php";
    exit;
}

$profile = $profileRes->fields;
$profile['score'] = (int) ($profile['score'] ?? 0);
$profile['raw_asset_value'] = (int) ($profile['raw_asset_value'] ?? 0);
$profile['liquid_wealth'] = (int) ($profile['liquid_wealth'] ?? 0);
$profile['planet_count'] = (int) ($profile['planet_count'] ?? 0);
$profile['bounty_total'] = (int) ($profile['bounty_total'] ?? 0);
$title = $profile['character_name'] . ' Profile';

$rating = (int) $profile['rating'];

$techStats = array(
    'Hull' => (int) $profile['hull'],
    'Engines' => (int) $profile['engines'],
    'Power' => (int) $profile['power'],
    'Computer' => (int) $profile['computer'],
    'Sensors' => (int) $profile['sensors'],
    'Armor' => (int) $profile['armor'],
    'Shields' => (int) $profile['shields'],
    'Beams' => (int) $profile['beams'],
    'Torpedoes' => (int) $profile['torp_launchers'],
    'Cloak' => (int) $profile['cloak'],
);

$avgTech = 0;
if (!empty($techStats)) {
    $avgTech = round(array_sum($techStats) / count($techStats), 1);
}

$rankRes = $db->Execute(
    "SELECT COUNT(*) + 1 AS rank_position FROM (" . bnt_rankings_base_player_sql(true) . ") ranked_players WHERE ranked_players.raw_asset_value > ?",
    array($profile['raw_asset_value'])
);
db_op_result($db, $rankRes, __LINE__, __FILE__, $db_logging);
$rankPosition = ($rankRes && !$rankRes->EOF) ? (int) $rankRes->fields['rank_position'] : 0;
if ((int) $profile['turns_used'] < 1) {
    $rankPosition = 0;
}

$isOwnProfile = ((int) $viewer['ship_id'] === (int) $profile['ship_id']);
$isContact = bnt_is_contact((int) $viewer['ship_id'], (int) $profile['ship_id']);

include "header.php";

echo <<<HTML
<style>
.profile-shell {
  width: min(1120px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.profile-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.profile-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.profile-title {
  margin: 0 0 6px;
  font-size: 34px;
  color: #f2fbff;
}
.profile-subtitle {
  color: rgba(220, 238, 248, 0.9);
  line-height: 1.6;
}
.profile-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 18px;
}
.profile-actions a {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
}
.profile-grid {
  display: grid;
  grid-template-columns: 0.95fr 1.35fr;
  gap: 16px;
}
.profile-panel {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
}
.profile-panel h2 {
  margin: 0 0 14px;
  color: #eefbff;
  font-size: 20px;
}
.profile-stat-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}
.profile-stat {
  border: 1px solid rgba(0, 238, 255, 0.1);
  background: rgba(5, 16, 29, 0.96);
  padding: 12px 14px;
}
.profile-stat__label {
  display: block;
  font-size: 10px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(122, 176, 204, 0.9);
  margin-bottom: 4px;
}
.profile-stat__value {
  display: block;
  font-family: var(--font-hud, monospace);
  font-size: 20px;
  color: #eefbff;
}
.profile-meta-list {
  display: grid;
  gap: 10px;
}
.profile-meta-row {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  border-bottom: 1px solid rgba(0, 238, 255, 0.08);
  padding-bottom: 10px;
}
.profile-meta-row:last-child {
  border-bottom: 0;
  padding-bottom: 0;
}
.profile-meta-row strong {
  color: #eefbff;
}
.profile-tech-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
}
.profile-tech-card {
  border: 1px solid rgba(0, 238, 255, 0.1);
  background: rgba(5, 16, 29, 0.96);
  padding: 12px 14px;
}
.profile-tech-card__name {
  display: block;
  font-size: 11px;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #7edfff;
  margin-bottom: 4px;
}
.profile-tech-card__value {
  display: block;
  font-family: var(--font-hud, monospace);
  font-size: 19px;
  color: #eefbff;
}
@media (max-width: 920px) {
  .profile-grid,
  .profile-tech-grid,
  .profile-stat-grid {
    grid-template-columns: 1fr;
  }
}
</style>
HTML;

echo "<div class='profile-shell'>";
echo "<section class='profile-hero'>";
echo "<div class='profile-eyebrow'>Pilot Record</div>";
echo "<h1 class='profile-title'>" . htmlspecialchars((string) $profile['character_name'], ENT_QUOTES, 'UTF-8') . "</h1>";
echo "<div class='profile-subtitle'>Aboard <strong>" . htmlspecialchars((string) $profile['ship_name'], ENT_QUOTES, 'UTF-8') . "</strong>";
if (!empty($profile['team_name'])) {
    echo " · Team <strong>" . htmlspecialchars((string) $profile['team_name'], ENT_QUOTES, 'UTF-8') . "</strong>";
}
echo " · Status <strong>" . (((string) $profile['ship_destroyed'] === 'Y') ? 'Destroyed' : 'Active') . "</strong></div>";
echo "<div class='profile-actions'>";
if (!$isOwnProfile) {
    echo "<a href='mailto2.php?name=" . rawurlencode((string) $profile['character_name']) . "'>Send Message</a>";
    if (!$isContact) {
        echo "<a href='contacts.php?search=" . rawurlencode((string) $profile['character_name']) . "'>Add To Contacts</a>";
    } else {
        echo "<a href='contacts.php'>View In Contacts</a>";
    }
}
echo "<a href='ranking.php'>Rankings</a>";
echo "<a href='contacts.php'>Contacts</a>";
echo "</div>";
echo "</section>";

echo "<div class='profile-grid'>";
echo "<section class='profile-panel'>";
echo "<h2>Overview</h2>";
echo "<div class='profile-stat-grid'>";
echo "<div class='profile-stat'><span class='profile-stat__label'>Rank</span><span class='profile-stat__value'>" . ($rankPosition > 0 ? NUMBER($rankPosition) : 'N/A') . "</span></div>";
echo "<div class='profile-stat'><span class='profile-stat__label'>Score</span><span class='profile-stat__value'>" . NUMBER($profile['score']) . "</span></div>";
echo "<div class='profile-stat'><span class='profile-stat__label'>Turns Used</span><span class='profile-stat__value'>" . NUMBER($profile['turns_used']) . "</span></div>";
echo "<div class='profile-stat'><span class='profile-stat__label'>Reputation</span><span class='profile-stat__value'>" . NUMBER($rating) . "</span></div>";
echo "</div>";
echo "<div style='height:16px;'></div>";
echo "<div class='profile-meta-list'>";
echo "<div class='profile-meta-row'><span>Character</span><strong>" . htmlspecialchars((string) $profile['character_name'], ENT_QUOTES, 'UTF-8') . "</strong></div>";
echo "<div class='profile-meta-row'><span>Ship</span><strong>" . htmlspecialchars((string) $profile['ship_name'], ENT_QUOTES, 'UTF-8') . "</strong></div>";
echo "<div class='profile-meta-row'><span>Last Login</span><strong>" . htmlspecialchars((string) $profile['last_login'], ENT_QUOTES, 'UTF-8') . "</strong></div>";
echo "<div class='profile-meta-row'><span>Average Tech</span><strong>" . htmlspecialchars((string) NUMBER($avgTech), ENT_QUOTES, 'UTF-8') . "</strong></div>";
echo "<div class='profile-meta-row'><span>Team</span><strong>" . htmlspecialchars((string) ($profile['team_name'] ?: 'Independent'), ENT_QUOTES, 'UTF-8') . "</strong></div>";
echo "</div>";
echo "</section>";

echo "<section class='profile-panel'>";
echo "<h2>Ship Technology</h2>";
echo "<div class='profile-tech-grid'>";
foreach ($techStats as $label => $value) {
    echo "<div class='profile-tech-card'>";
    echo "<span class='profile-tech-card__name'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</span>";
    echo "<span class='profile-tech-card__value'>" . NUMBER($value) . "</span>";
    echo "</div>";
}
echo "</div>";
echo "</section>";
echo "</div>";
echo "</div>";

TEXT_GOTOMAIN();
include "footer.php";
?>
