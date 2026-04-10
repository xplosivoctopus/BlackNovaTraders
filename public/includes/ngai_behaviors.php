<?php
// Blacknova Traders — NGAI (Next Generation AI) Behavior Engine
// File: includes/ngai_behaviors.php
//
// NGAI ships are stored in bnt_ships (email like "name@ngai"),
// registered in bnt_xenobe (active/aggression/orders for compat),
// and extended by bnt_ngai (ship_type, faction, home_sector, hostile_on_scan).
//
// Six ship types, each with distinct behavior:
//   scout      — fast roamer, logs player positions, flees strong opponents
//   fighter    — evaluates target power before attacking, retreats if outmatched
//   spy        — stealthy, does not attack directly; retaliates when scanned
//   cruiser    — opportunistic trader and attacker
//   battleship — relentless hunter, never retreats
//   freighter  — trades, avoids all combat

if (preg_match("/ngai_behaviors.php/i", $_SERVER['PHP_SELF'])) {
    echo "You can not access this file directly!";
    die();
}

// ═══════════════════════════════════════════════════════════════
// UTILITY — Combat power score (single numeric for comparison)
// ═══════════════════════════════════════════════════════════════
function ngai_combat_power($shipinfo)
{
    global $level_factor, $torp_dmg_rate;
    $lf = (float)$level_factor;
    $power = NUM_BEAMS((int)$shipinfo['beams'])
           + NUM_SHIELDS((int)$shipinfo['shields'])
           + NUM_FIGHTERS((int)$shipinfo['computer'])
           + NUM_ARMOR((int)$shipinfo['armor'])
           + (round(pow($lf, (int)$shipinfo['torp_launchers'])) * 2 * (float)$torp_dmg_rate)
           + $shipinfo['ship_fighters']
           + $shipinfo['armor_pts'];
    return max(1, (int)$power);
}

// ═══════════════════════════════════════════════════════════════
// UTILITY — Is sector a Void zone? (cached via global array)
// ═══════════════════════════════════════════════════════════════
function ngai_is_void_sector($sector_id)
{
    global $ngai_void_sectors;
    return isset($ngai_void_sectors[(int)$sector_id]);
}

// ═══════════════════════════════════════════════════════════════
// UTILITY — Find first attackable target in $sector
//   $include_ngai = true  → also look at @ngai ships (inter-faction)
// ═══════════════════════════════════════════════════════════════
function ngai_sector_target($sector, $self_email, $self_faction, $include_ngai = true)
{
    global $db;
    $res = $db->Execute(
        "SELECT * FROM {$db->prefix}ships
         WHERE sector = ? AND email != ? AND planet_id = 0
           AND ship_destroyed = 'N' AND ship_id > 1",
        array((int)$sector, $self_email)
    );
    if (!$res || $res->EOF) return null;

    while (!$res->EOF)
    {
        $t = $res->fields;

        // Never attack classic Xenobes
        if (strpos($t['email'], '@xenobe') !== false)
        {
            $res->MoveNext();
            continue;
        }

        // For @ngai ships, only attack different-faction ships
        if (strpos($t['email'], '@ngai') !== false)
        {
            if (!$include_ngai) { $res->MoveNext(); continue; }
            $fr = $db->Execute("SELECT faction FROM {$db->prefix}ngai WHERE ngai_id = ?", array($t['email']));
            if ($fr && !$fr->EOF && (int)$fr->fields['faction'] === (int)$self_faction)
            {
                $res->MoveNext();
                continue; // Same faction — skip
            }
        }

        return $t;
    }
    return null;
}

// ═══════════════════════════════════════════════════════════════
// UTILITY — Warp ship to its home sector (flee)
// ═══════════════════════════════════════════════════════════════
function ngai_retreat($ngaiinfo)
{
    global $playerinfo, $db;
    if ((int)$ngaiinfo['home_sector'] > 0 && $playerinfo['sector'] != $ngaiinfo['home_sector'])
    {
        $stamp = date("Y-m-d H:i:s");
        $db->Execute(
            "UPDATE {$db->prefix}ships SET sector = ?, last_login = ?, turns_used = turns_used + 1 WHERE ship_id = ?",
            array((int)$ngaiinfo['home_sector'], $stamp, $playerinfo['ship_id'])
        );
        playerlog($db, $playerinfo['ship_id'], LOG_RAW,
                  "NGAI {$ngaiinfo['ship_type']} retreated to home sector {$ngaiinfo['home_sector']}.");
    }
}

// ═══════════════════════════════════════════════════════════════
// INTER-FACTION COMBAT — NGAI attacks another @ngai ship
// Uses a flag so xenobetoship() skips its normal @ngai guard.
// ═══════════════════════════════════════════════════════════════
function ngai_attack_ngai($target_ship_id)
{
    global $ngai_interfaction_attack;
    $ngai_interfaction_attack = true;
    xenobetoship($target_ship_id);
    $ngai_interfaction_attack = false;
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Scout
//   Moves 2–3 sectors per tick. Logs player sightings.
//   Attacks only when power advantage is overwhelming (>2×).
// ═══════════════════════════════════════════════════════════════
function ngai_run_scout($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $targetlink, $db;

    $moves = mt_rand(2, 3);
    for ($i = 0; $i < $moves; $i++)
    {
        $targetlink = $playerinfo['sector'];
        xenobemove();
        if ($xenobeisdead) return;

        // Refresh after move
        $r = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id = ?", array($playerinfo['ship_id']));
        if ($r && !$r->EOF) $playerinfo = $r->fields;
    }

    $target = ngai_sector_target($playerinfo['sector'], $playerinfo['email'], $ngaiinfo['faction'], false);
    if (!$target) return;

    $my_pow     = ngai_combat_power($playerinfo);
    $their_pow  = ngai_combat_power($target);

    if ($my_pow > $their_pow * 2)
    {
        // Overwhelmingly stronger — opportunity strike
        playerlog($db, $playerinfo['ship_id'], LOG_NGAI_ATTACK, $target['character_name']);
        xenobetoship($target['ship_id']);
    }
    else
    {
        playerlog($db, $playerinfo['ship_id'], LOG_RAW,
                  "NGAI Scout spotted {$target['character_name']} in sector {$playerinfo['sector']} — target logged, evading.");
        playerlog($db, $target['ship_id'], LOG_RAW,
                  "A fast unidentified vessel passed through your sector and disappeared.");
    }
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Fighter
//   Evaluates odds. Attacks if own power ≥ 80 % of target power.
//   Retreats if outmatched (< 50 %). Hunts top players 25 % of the time.
// ═══════════════════════════════════════════════════════════════
function ngai_run_fighter($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $targetlink, $db;

    if (mt_rand(1, 4) === 1)
    {
        xenobehunter();
        return;
    }

    $targetlink = $playerinfo['sector'];
    xenobemove();
    if ($xenobeisdead) return;

    $r = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id = ?", array($playerinfo['ship_id']));
    if ($r && !$r->EOF) $playerinfo = $r->fields;

    $target = ngai_sector_target($playerinfo['sector'], $playerinfo['email'], $ngaiinfo['faction'], true);
    if (!$target) return;

    $my_pow    = ngai_combat_power($playerinfo);
    $their_pow = ngai_combat_power($target);

    if ($my_pow >= $their_pow * 0.8)
    {
        playerlog($db, $playerinfo['ship_id'], LOG_NGAI_ATTACK, $target['character_name']);
        if (strpos($target['email'], '@ngai') !== false)
            ngai_attack_ngai($target['ship_id']);
        else
            xenobetoship($target['ship_id']);
    }
    elseif ($my_pow < $their_pow * 0.5)
    {
        playerlog($db, $playerinfo['ship_id'], LOG_RAW,
                  "NGAI Fighter outmatched by {$target['character_name']} — retreating.");
        ngai_retreat($ngaiinfo);
    }
    // 50–80 % match → skip this engagement
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Spy
//   Does not attack proactively. When scanned, warps to attacker
//   and strikes on the next tick (queued in ngai_hostile).
//   Otherwise roams quietly.
// ═══════════════════════════════════════════════════════════════
function ngai_run_spy($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $targetlink, $db;

    // Check for a queued retaliation target
    $hr = $db->Execute(
        "SELECT * FROM {$db->prefix}ngai_hostile WHERE ngai_id = ? ORDER BY triggered_at ASC LIMIT 1",
        array($playerinfo['email'])
    );
    if ($hr && !$hr->EOF)
    {
        $hostile = $hr->fields;
        $tr = $db->Execute(
            "SELECT * FROM {$db->prefix}ships WHERE ship_id = ? AND ship_destroyed = 'N'",
            array((int)$hostile['target_ship_id'])
        );
        if ($tr && !$tr->EOF)
        {
            $target = $tr->fields;
            // Warp to target's sector
            $stamp = date("Y-m-d H:i:s");
            $db->Execute(
                "UPDATE {$db->prefix}ships SET sector = ?, last_login = ?, turns_used = turns_used + 1 WHERE ship_id = ?",
                array((int)$target['sector'], $stamp, $playerinfo['ship_id'])
            );
            $playerinfo['sector'] = $target['sector'];
            playerlog($db, $playerinfo['ship_id'], LOG_RAW,
                      "NGAI Spy tracked scanner {$target['character_name']} to sector {$target['sector']} and is attacking.");
            playerlog($db, $target['ship_id'], LOG_RAW,
                      "The vessel you scanned has tracked you down and is attacking!");
            xenobetoship($target['ship_id']);
        }
        // Clear hostile record regardless (target may already be gone)
        $db->Execute(
            "DELETE FROM {$db->prefix}ngai_hostile WHERE hostile_id = ?",
            array((int)$hostile['hostile_id'])
        );
        return;
    }

    // No triggered target — move quietly
    $targetlink = $playerinfo['sector'];
    xenobemove();
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Cruiser
//   Roams, then 40 % trades, 40 % evaluates/attacks, 20 % rests.
// ═══════════════════════════════════════════════════════════════
function ngai_run_cruiser($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $targetlink, $db;

    $targetlink = $playerinfo['sector'];
    xenobemove();
    if ($xenobeisdead) return;

    $r = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id = ?", array($playerinfo['ship_id']));
    if ($r && !$r->EOF) $playerinfo = $r->fields;

    $roll = mt_rand(1, 10);

    if ($roll <= 4)
    {
        xenobetrade();
    }
    elseif ($roll <= 8)
    {
        $target = ngai_sector_target($playerinfo['sector'], $playerinfo['email'], $ngaiinfo['faction'], true);
        if ($target)
        {
            $my_pow    = ngai_combat_power($playerinfo);
            $their_pow = ngai_combat_power($target);
            if ($my_pow >= $their_pow * 0.9)
            {
                playerlog($db, $playerinfo['ship_id'], LOG_NGAI_ATTACK, $target['character_name']);
                if (strpos($target['email'], '@ngai') !== false)
                    ngai_attack_ngai($target['ship_id']);
                else
                    xenobetoship($target['ship_id']);
            }
        }
    }
    // else: roam only
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Battleship
//   Always hunts top players. Never retreats.
//   Also engages rival-faction NGAI found in sector after hunt.
// ═══════════════════════════════════════════════════════════════
function ngai_run_battleship($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $db;

    xenobehunter();
    if ($xenobeisdead) return;

    $r = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id = ?", array($playerinfo['ship_id']));
    if ($r && !$r->EOF) $playerinfo = $r->fields;

    // Also attack rival-faction NGAI in current sector
    $target = ngai_sector_target($playerinfo['sector'], $playerinfo['email'], $ngaiinfo['faction'], true);
    if ($target && strpos($target['email'], '@ngai') !== false)
    {
        playerlog($db, $playerinfo['ship_id'], LOG_NGAI_ATTACK, $target['character_name']);
        ngai_attack_ngai($target['ship_id']);
    }
}

// ═══════════════════════════════════════════════════════════════
// BEHAVIOR: Freighter
//   80 % trades, 20 % returns toward home sector. Never attacks.
// ═══════════════════════════════════════════════════════════════
function ngai_run_freighter($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $targetlink, $db;

    if (mt_rand(1, 5) > 1)
    {
        xenobetrade();
    }
    else
    {
        ngai_retreat($ngaiinfo);
    }
}

// ═══════════════════════════════════════════════════════════════
// DISPATCH — Routes each NGAI ship to its behavior function.
//
// Void Zone override: any NGAI in a Void zone sector attacks any
// player found there immediately, regardless of ship type.
// ═══════════════════════════════════════════════════════════════
function ngai_dispatch($ngaiinfo)
{
    global $playerinfo, $xenobeisdead, $db;

    // Void Zone: defend territory against any human intruder
    if (ngai_is_void_sector($playerinfo['sector']))
    {
        $intruder = ngai_sector_target($playerinfo['sector'], $playerinfo['email'], $ngaiinfo['faction'], false);
        if ($intruder)
        {
            playerlog($db, $playerinfo['ship_id'], LOG_RAW,
                      "NGAI defending The Void against intruder {$intruder['character_name']}.");
            playerlog($db, $intruder['ship_id'], LOG_RAW,
                      "You have entered The Void — AI forces are defending their territory!");
            xenobetoship($intruder['ship_id']);
            return;
        }
    }

    switch ($ngaiinfo['ship_type'])
    {
        case 'scout':      ngai_run_scout($ngaiinfo);      break;
        case 'fighter':    ngai_run_fighter($ngaiinfo);    break;
        case 'spy':        ngai_run_spy($ngaiinfo);        break;
        case 'cruiser':    ngai_run_cruiser($ngaiinfo);    break;
        case 'battleship': ngai_run_battleship($ngaiinfo); break;
        case 'freighter':  ngai_run_freighter($ngaiinfo);  break;
    }
}
?>
