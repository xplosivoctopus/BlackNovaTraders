<?php
// Blacknova Traders — NGAI (Next Generation AI) Scheduler Entry
// File: sched_ngai.php
//
// Called by scheduler.php each tick. Iterates all active NGAI ships,
// regenerates stats, then dispatches each ship to its behavior function.

if (preg_match("/sched_ngai.php/i", $_SERVER['PHP_SELF']))
{
    echo "You can not access this file directly!";
    die();
}

// Include xenobe support functions (reused by NGAI)
include_once "includes/xenobe_hunter.php";
include_once "includes/xenobe_move.php";
include_once "includes/xenobe_regen.php";
include_once "includes/xenobe_to_sec_def.php";
include_once "includes/xenobe_to_ship.php";
include_once "includes/xenobe_trade.php";
include_once "includes/xenobe_to_planet.php";

// NGAI behavior engine
include_once "includes/ngai_behaviors.php";

global $targetlink, $xenobeisdead, $ngai_interfaction_attack;
$ngai_interfaction_attack = false;

// ── Pre-load void sector IDs for O(1) lookup in ngai_is_void_sector() ───────
$ngai_void_sectors = array();
$void_res = $db->Execute(
    "SELECT u.sector_id FROM {$db->prefix}universe u
     JOIN {$db->prefix}zones z ON z.zone_id = u.zone_id
     WHERE z.is_void = 'Y'"
);
if ($void_res && !$void_res->EOF)
{
    while (!$void_res->EOF)
    {
        $ngai_void_sectors[(int)$void_res->fields['sector_id']] = true;
        $void_res->MoveNext();
    }
}

// ── Counters for end-of-run summary ──────────────────────────────────────────
$ngai_total      = 0;
$ngai_counts     = array(
    'scout'      => 0,
    'fighter'    => 0,
    'spy'        => 0,
    'cruiser'    => 0,
    'battleship' => 0,
    'freighter'  => 0,
);

// Lock core tables before iterating ships
$resa = $db->Execute(
    "LOCK TABLES {$db->prefix}ships WRITE, {$db->prefix}ngai WRITE, {$db->prefix}ngai_hostile WRITE"
);
db_op_result($db, $resa, __LINE__, __FILE__, $db_logging);

// Select all active (non-destroyed) NGAI ships, joined with their NGAI profile
$res = $db->Execute(
    "SELECT s.*, n.ship_type, n.faction, n.home_sector, n.hostile_on_scan
     FROM {$db->prefix}ships s
     JOIN {$db->prefix}ngai n ON n.ngai_id = s.email
     WHERE s.ship_destroyed = 'N'
     ORDER BY s.ship_id"
);
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);

while ($res && !$res->EOF)
{
    $xenobeisdead = 0;
    $playerinfo   = $res->fields;

    // Build ngaiinfo array from the joined NGAI columns
    $ngaiinfo = array(
        'ngai_id'       => $playerinfo['email'],
        'ship_type'     => $playerinfo['ship_type'],
        'faction'       => (int)$playerinfo['faction'],
        'home_sector'   => (int)$playerinfo['home_sector'],
        'hostile_on_scan' => $playerinfo['hostile_on_scan'],
    );

    // Regenerate energy, armor, fighters, torpedoes (reuses xenobe logic)
    xenoberegen();

    // Dispatch to ship-type behavior
    $ngai_total++;
    $type = $ngaiinfo['ship_type'];
    if (isset($ngai_counts[$type])) $ngai_counts[$type]++;

    ngai_dispatch($ngaiinfo);

    $res->MoveNext();
}
if ($res) $res->_close();

// Unlock tables
$result = $db->Execute("UNLOCK TABLES");
db_op_result($db, $result, __LINE__, __FILE__, $db_logging);

?>
