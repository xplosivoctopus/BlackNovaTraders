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
// File: ranking.php

include "config/config.php";
updatecookie();

$lang = isset($_GET['lang']) ? $_GET['lang'] : $default_lang;
$link_back = (isset($_GET['lang']) && $_GET['lang'] !== '') ? '?lang=' . urlencode((string) $_GET['lang']) : '';

load_languages($db, $lang, array('main', 'ranking', 'common', 'global_includes', 'global_funcs', 'footer', 'teams'), $langvars, $db_logging);

$l_ranks_title = str_replace("[max_ranks]", $max_ranks, $l_ranks_title);
$title = $l_ranks_title;

$basePlayerSql = bnt_rankings_base_player_sql();
$sort = trim((string) ($_GET['sort'] ?? 'score'));
$sortMap = array(
    'score' => array('label' => $l_score, 'order' => 'live_score DESC, raw_asset_value DESC, character_name ASC'),
    'wealth' => array('label' => 'Net Worth', 'order' => 'raw_asset_value DESC, live_score DESC, character_name ASC'),
    'liquid' => array('label' => 'Liquid Wealth', 'order' => 'liquid_wealth DESC, raw_asset_value DESC, character_name ASC'),
    'planets' => array('label' => 'Empire', 'order' => 'planet_count DESC, planet_asset DESC, character_name ASC'),
    'good' => array('label' => 'Most Honored', 'order' => 'CASE WHEN rating > 0 THEN 0 ELSE 1 END ASC, rating DESC, raw_asset_value DESC, character_name ASC'),
    'bad' => array('label' => 'Most Notorious', 'order' => 'CASE WHEN rating < 0 THEN 0 ELSE 1 END ASC, rating ASC, raw_asset_value DESC, character_name ASC'),
    'bounty' => array('label' => 'Most Wanted', 'order' => 'bounty_total DESC, raw_asset_value DESC, character_name ASC'),
    'efficiency' => array('label' => 'Efficiency', 'order' => 'efficiency DESC, raw_asset_value DESC, character_name ASC'),
    'login' => array('label' => $l_ranks_lastlog, 'order' => 'last_login DESC, character_name ASC'),
);
if (!isset($sortMap[$sort])) {
    $sort = 'score';
}

$numPlayersRes = $db->Execute("SELECT COUNT(*) AS num_players FROM ({$basePlayerSql}) ranked_players");
db_op_result($db, $numPlayersRes, __LINE__, __FILE__, $db_logging);
$numPlayers = ($numPlayersRes && !$numPlayersRes->EOF) ? (int) $numPlayersRes->fields['num_players'] : 0;

$standings = $db->SelectLimit("SELECT * FROM ({$basePlayerSql}) ranked_players ORDER BY " . $sortMap[$sort]['order'], $max_ranks);
db_op_result($db, $standings, __LINE__, __FILE__, $db_logging);

$boards = array(
    'Top Commanders' => array(
        'metric' => 'live_score',
        'suffix' => '',
        'order' => 'live_score DESC, raw_asset_value DESC, character_name ASC',
        'meta' => 'Net-worth score across ship assets, planets, and banked credits.',
    ),
    'Most Honored' => array(
        'metric' => 'rating',
        'suffix' => ' rep',
        'order' => 'rating DESC, raw_asset_value DESC, character_name ASC',
        'where' => 'rating > 0',
        'meta' => 'Positive reputation. This is not a PvP kill rating.',
    ),
    'Most Notorious' => array(
        'metric' => 'rating',
        'suffix' => ' rep',
        'order' => 'rating ASC, raw_asset_value DESC, character_name ASC',
        'where' => 'rating < 0',
        'meta' => 'Negative reputation. High infamy, not necessarily high combat skill.',
    ),
    'Deepest Pockets' => array(
        'metric' => 'liquid_wealth',
        'suffix' => ' cr',
        'order' => 'liquid_wealth DESC, raw_asset_value DESC, character_name ASC',
        'meta' => 'Ship credits plus bank net worth.',
    ),
    'Empire Builders' => array(
        'metric' => 'planet_count',
        'suffix' => ' worlds',
        'order' => 'planet_count DESC, planet_asset DESC, raw_asset_value DESC, character_name ASC',
        'meta' => 'Planets owned, with owned-world value as the tiebreaker.',
    ),
    'Most Wanted' => array(
        'metric' => 'bounty_total',
        'suffix' => ' cr',
        'order' => 'bounty_total DESC, raw_asset_value DESC, character_name ASC',
        'meta' => 'Active open bounty total across all hunters, including Federation bounties.',
    ),
);

$boardResults = array();
foreach ($boards as $boardTitle => $boardConfig) {
    $boardSql = "SELECT * FROM ({$basePlayerSql}) ranked_players";
    if (!empty($boardConfig['where'])) {
        $boardSql .= " WHERE " . $boardConfig['where'];
    }
    $boardSql .= " ORDER BY " . $boardConfig['order'];
    $boardQuery = $db->SelectLimit($boardSql, 5);
    db_op_result($db, $boardQuery, __LINE__, __FILE__, $db_logging);
    $rows = array();
    if ($boardQuery) {
        while (!$boardQuery->EOF) {
            $rows[] = $boardQuery->fields;
            $boardQuery->MoveNext();
        }
    }
    $boardResults[$boardTitle] = $rows;
}

$teamBoard = $db->SelectLimit(
    "SELECT ranked.team,
            ranked.team_name,
            COUNT(*) AS live_members,
            SUM(ranked.raw_asset_value) AS combined_assets,
            ROUND(SQRT(SUM(ranked.raw_asset_value))) AS combined_score,
            SUM(ranked.liquid_wealth) AS liquid_wealth,
            AVG(ranked.rating) AS avg_rating
       FROM ({$basePlayerSql}) ranked
      WHERE ranked.team > 0
   GROUP BY ranked.team, ranked.team_name
   ORDER BY combined_assets DESC, ranked.team_name ASC",
    5
);
db_op_result($db, $teamBoard, __LINE__, __FILE__, $db_logging);

include "header.php";
bigtitle();

$sortLinks = array(
    'score' => $l_score,
    'wealth' => 'Net Worth',
    'liquid' => 'Liquid Wealth',
    'planets' => 'Empire',
    'good' => 'Honored',
    'bad' => 'Notorious',
    'bounty' => 'Most Wanted',
    'efficiency' => 'Efficiency',
    'login' => $l_ranks_lastlog,
);

echo <<<HTML
<style>
.rank-shell {
  width: min(1180px, calc(100% - 24px));
  margin: 16px auto 28px;
  color: #dbefff;
}
.rank-hero {
  padding: 26px 28px;
  margin-bottom: 18px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: linear-gradient(135deg, rgba(4,15,30,0.98), rgba(8,26,50,0.96));
  box-shadow: 0 10px 36px rgba(0,0,0,0.42);
}
.rank-eyebrow {
  color: #7edfff;
  font-size: 11px;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  margin-bottom: 8px;
}
.rank-title {
  margin: 0 0 8px;
  font-size: 32px;
  color: #f2fbff;
}
.rank-copy {
  margin: 0;
  line-height: 1.6;
  color: rgba(220, 238, 248, 0.9);
}
.rank-toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
}
.rank-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 9px 12px;
  border: 1px solid rgba(0, 238, 255, 0.16);
  background: rgba(8, 29, 48, 0.96);
  color: #eaf9ff;
  text-decoration: none;
}
.rank-link--active {
  background: #00eeff;
  color: #001620;
  border-color: rgba(0, 238, 255, 0.45);
}
.rank-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 0 0 18px;
}
.rank-summary__item {
  min-width: 170px;
  border: 1px solid rgba(0, 238, 255, 0.1);
  background: rgba(5, 16, 29, 0.96);
  padding: 12px 14px;
}
.rank-summary__label {
  display: block;
  margin-bottom: 5px;
  color: rgba(122, 176, 204, 0.92);
  font-size: 10px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}
.rank-summary__value {
  display: block;
  font-size: 22px;
  color: #eefbff;
  font-family: var(--font-hud, monospace);
}
.rank-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}
.rank-panel {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.82);
  padding: 18px;
}
.rank-panel h2 {
  margin: 0 0 10px;
  color: #eefbff;
  font-size: 20px;
}
.rank-panel__copy {
  margin: 0 0 14px;
  color: rgba(170, 200, 225, 0.78);
  font-size: 13px;
  line-height: 1.5;
}
.rank-list {
  display: grid;
  gap: 10px;
}
.rank-card {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(5, 16, 29, 0.96);
  padding: 12px 14px;
}
.rank-card__top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 6px;
}
.rank-card__title {
  color: #eefbff;
  font-size: 18px;
  text-decoration: none;
}
.rank-card__value {
  color: #7edfff;
  white-space: nowrap;
}
.rank-card__meta {
  color: rgba(170, 200, 225, 0.78);
  font-size: 12px;
}
.rank-table {
  width: 100%;
  border-collapse: collapse;
}
.rank-table th,
.rank-table td {
  padding: 10px 12px;
  border-bottom: 1px solid rgba(0, 238, 255, 0.08);
  text-align: left;
}
.rank-table th {
  color: #7edfff;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.14em;
}
.rank-table tr:hover {
  background: rgba(0, 238, 255, 0.04);
}
.rank-pilot {
  color: #eefbff;
  text-decoration: none;
  font-weight: 700;
}
@media (max-width: 920px) {
  .rank-grid {
    grid-template-columns: 1fr;
  }
  .rank-table {
    display: block;
    overflow-x: auto;
  }
}
</style>
HTML;

echo "<div class='rank-shell'>";
echo "<section class='rank-hero'>";
echo "<div class='rank-eyebrow'>Galactic Standings</div>";
echo "<h1 class='rank-title'>" . htmlspecialchars($l_ranks_title, ENT_QUOTES, 'UTF-8') . "</h1>";
echo "<p class='rank-copy'>Standings now use live derived net worth instead of stale cached score. Reputation is shown separately from wealth, and bounty / empire boards now reflect what those systems actually measure.</p>";
echo "<div class='rank-toolbar'>";
foreach ($sortLinks as $sortKey => $sortLabel) {
    $classes = ($sort === $sortKey) ? 'rank-link rank-link--active' : 'rank-link';
    $href = 'ranking.php?sort=' . urlencode($sortKey);
    if ($link_back !== '') {
        $href .= '&amp;lang=' . urlencode((string) $_GET['lang']);
    }
    echo "<a class='{$classes}' href='{$href}'>" . htmlspecialchars($sortLabel, ENT_QUOTES, 'UTF-8') . "</a>";
}
echo "<a class='rank-link' href='main.php'>&lt; Dashboard</a>";
echo "</div>";
echo "</section>";

echo "<div class='rank-summary'>";
echo "<div class='rank-summary__item'><span class='rank-summary__label'>Tracked Pilots</span><span class='rank-summary__value'>" . NUMBER($numPlayers) . "</span></div>";
echo "<div class='rank-summary__item'><span class='rank-summary__label'>Active Sort</span><span class='rank-summary__value'>" . htmlspecialchars($sortMap[$sort]['label'], ENT_QUOTES, 'UTF-8') . "</span></div>";
echo "</div>";

echo "<div class='rank-grid'>";
foreach ($boards as $boardTitle => $boardConfig) {
    echo "<section class='rank-panel'>";
    echo "<h2>" . htmlspecialchars($boardTitle, ENT_QUOTES, 'UTF-8') . "</h2>";
    echo "<p class='rank-panel__copy'>" . htmlspecialchars($boardConfig['meta'], ENT_QUOTES, 'UTF-8') . "</p>";
    echo "<div class='rank-list'>";
    if (empty($boardResults[$boardTitle])) {
        echo "<div class='rank-card'><div class='rank-card__meta'>No data available yet.</div></div>";
    } else {
        foreach ($boardResults[$boardTitle] as $index => $row) {
            $metricField = $boardConfig['metric'];
            $metricValue = NUMBER($row[$metricField] ?? 0) . $boardConfig['suffix'];
            echo "<article class='rank-card'>";
            echo "<div class='rank-card__top'>";
            echo "<a class='rank-card__title' href='profile.php?ship_id=" . (int) $row['ship_id'] . "'>" . NUMBER($index + 1) . ". " . htmlspecialchars((string) $row['character_name'], ENT_QUOTES, 'UTF-8') . "</a>";
            echo "<span class='rank-card__value'>" . htmlspecialchars($metricValue, ENT_QUOTES, 'UTF-8') . "</span>";
            echo "</div>";
            echo "<div class='rank-card__meta'>Ship: " . htmlspecialchars((string) $row['ship_name'], ENT_QUOTES, 'UTF-8') . " | Net worth " . NUMBER($row['raw_asset_value']) . " | Team " . htmlspecialchars((string) $row['team_name'], ENT_QUOTES, 'UTF-8') . "</div>";
            echo "</article>";
        }
    }
    echo "</div>";
    echo "</section>";
}

echo "<section class='rank-panel'>";
echo "<h2>Team Power</h2>";
echo "<p class='rank-panel__copy'>Team power is derived from live combined member assets, then compressed through the same square-root model used for individual score.</p>";
echo "<div class='rank-list'>";
if ($teamBoard && !$teamBoard->EOF) {
    $teamRank = 1;
    while (!$teamBoard->EOF) {
        $team = $teamBoard->fields;
        echo "<article class='rank-card'>";
        echo "<div class='rank-card__top'>";
        echo "<div class='rank-card__title'>" . NUMBER($teamRank) . ". " . htmlspecialchars((string) $team['team_name'], ENT_QUOTES, 'UTF-8') . "</div>";
        echo "<span class='rank-card__value'>" . NUMBER($team['combined_score']) . " score</span>";
        echo "</div>";
        echo "<div class='rank-card__meta'>" . NUMBER($team['live_members']) . " live members | " . NUMBER($team['combined_assets']) . " total assets | " . NUMBER($team['liquid_wealth']) . " liquid wealth | Avg rep " . NUMBER((int) round((float) $team['avg_rating'])) . "</div>";
        echo "</article>";
        $teamRank++;
        $teamBoard->MoveNext();
    }
} else {
    echo "<div class='rank-card'><div class='rank-card__meta'>No active teams ranked yet.</div></div>";
}
echo "</div>";
echo "</section>";
echo "</div>";

echo "<section class='rank-panel'>";
echo "<h2>Full Pilot Standings</h2>";
echo "<table class='rank-table'>";
echo "<tr>";
echo "<th>Rank</th>";
echo "<th>$l_player</th>";
echo "<th>$l_score</th>";
echo "<th>Net Worth</th>";
echo "<th>Liquid</th>";
echo "<th>Empire</th>";
echo "<th>Reputation</th>";
echo "<th>Wanted</th>";
echo "<th>Efficiency</th>";
echo "<th>Online</th>";
echo "</tr>";

if (!$standings) {
    echo "<tr><td colspan='10'>$l_ranks_none</td></tr>";
} else {
    $rank = 1;
    while (!$standings->EOF) {
        $row = $standings->fields;
        $online = ((TIME() - (int) $row['online']) / 60 <= 5) ? 'Online' : '';

        echo "<tr>";
        echo "<td>" . NUMBER($rank) . "</td>";
        echo "<td>";
        echo player_insignia_name($db, $row['email']);
        echo "&nbsp;<a class='rank-pilot new_link' href='profile.php?ship_id=" . (int) $row['ship_id'] . "'>" . htmlspecialchars((string) $row['character_name'], ENT_QUOTES, 'UTF-8') . "</a>";
        echo "</td>";
        echo "<td>" . NUMBER($row['live_score']) . "</td>";
        echo "<td>" . NUMBER($row['raw_asset_value']) . "</td>";
        echo "<td>" . NUMBER($row['liquid_wealth']) . "</td>";
        echo "<td>" . NUMBER($row['planet_count']) . "</td>";
        echo "<td>" . NUMBER($row['rating']) . "</td>";
        echo "<td>" . NUMBER($row['bounty_total']) . "</td>";
        echo "<td>" . NUMBER($row['efficiency']) . "</td>";
        echo "<td>" . $online . "</td>";
        echo "</tr>";

        $rank++;
        $standings->MoveNext();
    }
}
echo "</table>";
echo "</section>";

echo "<div style='margin-top:18px; display:flex; gap:10px; flex-wrap:wrap;'><a class='rank-link' href='main.php'>&lt; Dashboard</a></div>";
echo "</div>";

if (empty($username)) {
    echo str_replace("[here]", "<a href='index.php" . $link_back . "'>" . $l->get('l_here') . "</a>", $l->get('l_global_mlogin'));
}

include "footer.php";
?>
