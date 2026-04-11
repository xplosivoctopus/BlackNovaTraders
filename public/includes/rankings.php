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
// File: includes/rankings.php

function bnt_get_rankings_sql_parts(): array
{
    global $db;
    global $upgrade_factor;
    global $upgrade_cost;
    global $torpedo_price;
    global $armor_price;
    global $fighter_price;
    global $ore_price;
    global $organics_price;
    global $goods_price;
    global $energy_price;
    global $colonist_price;
    global $dev_genesis_price;
    global $dev_beacon_price;
    global $dev_emerwarp_price;
    global $dev_warpedit_price;
    global $dev_minedeflector_price;
    global $dev_escapepod_price;
    global $dev_fuelscoop_price;
    global $dev_lssd_price;
    global $base_credits;

    $shipLevels = "(" .
        "ROUND(POW({$upgrade_factor}, s.hull)) +" .
        "ROUND(POW({$upgrade_factor}, s.engines)) +" .
        "ROUND(POW({$upgrade_factor}, s.power)) +" .
        "ROUND(POW({$upgrade_factor}, s.computer)) +" .
        "ROUND(POW({$upgrade_factor}, s.sensors)) +" .
        "ROUND(POW({$upgrade_factor}, s.beams)) +" .
        "ROUND(POW({$upgrade_factor}, s.torp_launchers)) +" .
        "ROUND(POW({$upgrade_factor}, s.shields)) +" .
        "ROUND(POW({$upgrade_factor}, s.armor)) +" .
        "ROUND(POW({$upgrade_factor}, s.cloak))" .
        ") * {$upgrade_cost}";

    $shipEquipment = "(" .
        "(s.torps * {$torpedo_price}) +" .
        "(s.armor_pts * {$armor_price}) +" .
        "(s.ship_ore * {$ore_price}) +" .
        "(s.ship_organics * {$organics_price}) +" .
        "(s.ship_goods * {$goods_price}) +" .
        "(s.ship_energy * {$energy_price}) +" .
        "(s.ship_colonists * {$colonist_price}) +" .
        "(s.ship_fighters * {$fighter_price})" .
        ")";

    $shipDevices = "(" .
        "(s.dev_warpedit * {$dev_warpedit_price}) +" .
        "(s.dev_genesis * {$dev_genesis_price}) +" .
        "(s.dev_beacon * {$dev_beacon_price}) +" .
        "(s.dev_emerwarp * {$dev_emerwarp_price}) +" .
        "(CASE WHEN s.dev_escapepod='Y' THEN {$dev_escapepod_price} ELSE 0 END) +" .
        "(CASE WHEN s.dev_fuelscoop='Y' THEN {$dev_fuelscoop_price} ELSE 0 END) +" .
        "(CASE WHEN s.dev_lssd='Y' THEN {$dev_lssd_price} ELSE 0 END) +" .
        "(s.dev_minedeflector * {$dev_minedeflector_price})" .
        ")";

    $planetAsset = "(" .
        "(SUM(p.organics) * {$organics_price}) +" .
        "(SUM(p.ore) * {$ore_price}) +" .
        "(SUM(p.goods) * {$goods_price}) +" .
        "(SUM(p.energy) * {$energy_price}) +" .
        "(SUM(p.colonists) * {$colonist_price}) +" .
        "(SUM(p.fighters) * {$fighter_price}) +" .
        "SUM(p.credits) +" .
        "SUM(CASE WHEN p.base='Y' THEN {$base_credits} + (p.torps * {$torpedo_price}) ELSE 0 END)" .
        ")";

    return array(
        'ship_levels' => $shipLevels,
        'ship_equipment' => $shipEquipment,
        'ship_devices' => $shipDevices,
        'ship_asset' => "({$shipLevels} + {$shipEquipment} + {$shipDevices} + s.credits)",
        'planet_asset' => $planetAsset,
        'net_bank' => "(COALESCE(ib.balance, 0) - COALESCE(ib.loan, 0))",
        'liquid_wealth' => "(s.credits + (COALESCE(ib.balance, 0) - COALESCE(ib.loan, 0)))",
        'planet_aggregate_sql' => "SELECT p.owner,
                                          COUNT(*) AS planet_count,
                                          {$planetAsset} AS planet_asset
                                     FROM {$db->prefix}planets p
                                    WHERE p.owner > 0
                                      AND p.defeated='N'
                                 GROUP BY p.owner",
    );
}

function bnt_rankings_base_player_sql(bool $requireTurnsUsed = true): string
{
    global $db;

    $parts = bnt_get_rankings_sql_parts();
    $shipAsset = $parts['ship_asset'];
    $netBank = $parts['net_bank'];
    $liquidWealth = $parts['liquid_wealth'];
    $planetAggregateSql = $parts['planet_aggregate_sql'];

    $sql = "SELECT s.ship_id,
                   s.email,
                   s.character_name,
                   s.ship_name,
                   s.turns_used,
                   s.last_login,
                   UNIX_TIMESTAMP(s.last_login) AS online,
                   s.rating,
                   s.credits,
                   COALESCE(t.team_name, '') AS team_name,
                   COALESCE(ib.balance, 0) AS bank_balance,
                   COALESCE(ib.loan, 0) AS bank_loan,
                   {$netBank} AS bank_net,
                   {$liquidWealth} AS liquid_wealth,
                   {$shipAsset} AS ship_asset,
                   COALESCE(pa.planet_count, 0) AS planet_count,
                   COALESCE(pa.planet_asset, 0) AS planet_asset,
                   COALESCE(bt.bounty_total, 0) AS bounty_total,
                   GREATEST(({$shipAsset} + COALESCE(pa.planet_asset, 0) + {$netBank}), 0) AS raw_asset_value,
                   ROUND(SQRT(GREATEST(({$shipAsset} + COALESCE(pa.planet_asset, 0) + {$netBank}), 0))) AS live_score,
                   CASE
                       WHEN s.turns_used < 150 THEN 0
                       ELSE ROUND(GREATEST(({$shipAsset} + COALESCE(pa.planet_asset, 0) + {$netBank}), 0) / s.turns_used)
                   END AS efficiency
              FROM {$db->prefix}ships s
         LEFT JOIN {$db->prefix}teams t ON s.team = t.id
         LEFT JOIN {$db->prefix}ibank_accounts ib ON ib.ship_id = s.ship_id
         LEFT JOIN ({$planetAggregateSql}) pa ON pa.owner = s.ship_id
         LEFT JOIN (
               SELECT bounty_on, SUM(amount) AS bounty_total
                 FROM {$db->prefix}bounty
             GROUP BY bounty_on
         ) bt ON bt.bounty_on = s.ship_id
             WHERE s.ship_destroyed='N'
               AND s.email NOT LIKE '%@xenobe'";

    if ($requireTurnsUsed) {
        $sql .= " AND s.turns_used > 0";
    }

    return $sql;
}

function bnt_get_ranked_player_row(int $shipId, bool $requireTurnsUsed = true): ?array
{
    global $db;

    $sql = bnt_rankings_base_player_sql($requireTurnsUsed) . " AND s.ship_id=? LIMIT 1";
    $res = $db->Execute($sql, array($shipId));
    if (!$res || $res->EOF) {
        return null;
    }

    return $res->fields;
}

function bnt_get_live_score_value(int $shipId): int
{
    $row = bnt_get_ranked_player_row($shipId);
    if ($row === null) {
        return 0;
    }

    return (int) $row['live_score'];
}

function bnt_refresh_score(int $shipId): int
{
    global $db;

    $score = bnt_get_live_score_value($shipId);
    $db->Execute("UPDATE {$db->prefix}ships SET score=? WHERE ship_id=?", array($score, $shipId));
    return $score;
}
