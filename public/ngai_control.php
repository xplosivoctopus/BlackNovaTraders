<?php
// Blacknova Traders — NGAI Control Panel
// File: ngai_control.php
//
// Admin panel for managing NGAI (Next Generation AI) ships.
// Ships are created in bnt_ships (@ngai email), bnt_xenobe (active/compat),
// and bnt_ngai (ship_type, faction, home_sector, hostile_on_scan).
// Also manages Void Zone designations on bnt_zones.

include "config/config.php";
bnt_require_admin();

load_languages($db, $lang, array('xenobe_control', 'common', 'global_includes', 'global_funcs', 'footer', 'news'), $langvars, $db_logging);

$title = "NGAI Control";
include "header.php";
connectdb();
bigtitle();

$ngaiRequestDefaults = array(
    'menu' => '',
    'operation' => '',
    'user' => '',
    'character' => '',
    'shipname' => '',
    'sector' => '',
    'xenlevel' => '',
    'ship_type' => '',
    'faction' => '',
    'home_sector' => '',
    'hostile_on_scan' => '',
    'character_name' => '',
    'ship_name' => '',
    'credits' => '',
    'hull' => '',
    'engines' => '',
    'power' => '',
    'computer' => '',
    'sensors' => '',
    'beams' => '',
    'torp_launchers' => '',
    'shields' => '',
    'armor' => '',
    'cloak' => '',
    'active' => '',
    'toggle_zone_id' => '',
);

foreach ($ngaiRequestDefaults as $ngaiField => $defaultValue)
{
    $$ngaiField = $_POST[$ngaiField] ?? $_GET[$ngaiField] ?? $defaultValue;
}

if (!isset($_POST['menu']))
    $module = '';
else
    $module = $menu;

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    bnt_require_csrf();
}

// ── Helpers ───────────────────────────────────────────────────────────────────
function ngai_checked($val)  { return $val === 'Y' ? 'checked' : ''; }
function ngai_yesno($onoff)  { return $onoff === 'ON' ? 'Y' : 'N'; }

// ── Main menu ─────────────────────────────────────────────────────────────────
if (empty($module))
{
    echo "<h2>NGAI Control Panel</h2>";
    echo "<p>Manage Next Generation AI ships and Void Zone designations.</p>";
    echo "<form action='ngai_control.php' method='post'>";
    echo "<select name='menu'>";
    echo "<option value='createnew'>Create a New NGAI Ship</option>";
    echo "<option value='editngai'>Edit an Existing NGAI Ship</option>";
    echo "<option value='deletengai'>Delete an NGAI Ship</option>";
    echo "<option value='clearlog'>Clear All NGAI Log Entries</option>";
    echo "<option value='voidzones'>Manage Void Zones</option>";
    echo "</select>&nbsp;";
    echo "<input type='submit' value='Go'>";
    echo "</form>";
    include "footer.php";
    die();
}

$button_main = true;

// ── Return-to-main helper ─────────────────────────────────────────────────────
function ngai_main_button($module)
{
    echo "<br><form action='ngai_control.php' method='post'>";
    echo "<input type='submit' value='Return to main menu'>";
    echo "</form>";
}

// ═════════════════════════════════════════════════════════════════════════════
// CREATE NEW NGAI SHIP
// ═════════════════════════════════════════════════════════════════════════════
if ($module === 'createnew')
{
    echo "<h2>Create a New NGAI Ship</h2>";
    echo "<form action='ngai_control.php' method='post'>";

    if (empty($operation))
    {
        // Generate a random name
        $S1 = array("Ar","Ax","Cy","Dra","Ex","Hy","Iv","Kr","Mx","Ny","Ob","Pr","Qua","Rx","Sy","Tr","Ur","Vx","Wy","Zy");
        $S2 = array("al","en","ix","on","ar","il","os","ur","an","el","or","in","ek","ax","ul","yi","oa","em","ith","ov");
        $S3 = array("us","an","ix","or","ex","yn","ak","el","os","ar","on","al","ir","uk","yl","ox","em","ek","ith","az");
        $character = $S1[mt_rand(0,19)] . $S2[mt_rand(0,19)] . $S3[mt_rand(0,19)];
        $shipname  = "NGAI-" . $character;
        $sector    = mt_rand(1, $sector_max);

        echo "<table border='0' cellpadding='5'>";
        echo "<tr><td>Character name</td><td><input type='text' name='character' size='25' value='" . htmlspecialchars($character) . "'></td></tr>";
        echo "<tr><td>Ship name</td><td><input type='text' name='shipname' size='25' value='" . htmlspecialchars($shipname) . "'></td></tr>";
        echo "<tr><td>Starting sector</td><td><input type='text' name='sector' size='8' value='$sector'></td></tr>";
        echo "<tr><td>Level</td><td><input type='text' name='xenlevel' size='5' value='5'></td></tr>";
        echo "<tr><td>Ship type</td><td><select name='ship_type'>";
        foreach (array('scout','fighter','spy','cruiser','battleship','freighter') as $t)
            echo "<option value='$t'>" . ucfirst($t) . "</option>";
        echo "</select></td></tr>";
        echo "<tr><td>Faction (1–5)</td><td><input type='text' name='faction' size='3' value='1'></td></tr>";
        echo "<tr><td>Home sector (0 = none)</td><td><input type='text' name='home_sector' size='8' value='0'></td></tr>";
        echo "<tr><td>Hostile on scan?</td><td><input type='checkbox' name='hostile_on_scan' value='ON'> Yes</td></tr>";
        echo "</table><br>";
        echo "<input type='hidden' name='operation' value='docreate'>";
        echo "<input type='hidden' name='menu' value='createnew'>";
        echo "<input type='submit' value='Create NGAI Ship'>";
    }
    elseif ($operation === 'docreate')
    {
        $character    = trim($character);
        $shipname     = trim($shipname);
        $sector       = max(1, (int)$sector);
        $xenlevel     = max(0, (int)$xenlevel);
        $faction      = max(1, min(5, (int)$faction));
        $home_sector  = max(0, (int)$home_sector);
        $_hostile     = isset($hostile_on_scan) ? 'Y' : 'N';
        $ship_type    = in_array($ship_type, array('scout','fighter','spy','cruiser','battleship','freighter'))
                        ? $ship_type : 'fighter';

        $errflag = 0;
        if ($character === '' || $shipname === '')
        {
            echo "<p style='color:#f55;'>Character name and ship name may not be blank.</p>";
            $errflag = 1;
        }

        $emailname = str_replace(' ', '_', $character) . '@ngai';
        $shipname  = str_replace(' ', '_', $shipname);

        if (!$errflag)
        {
            $chk = $db->Execute(
                "SELECT email FROM {$db->prefix}ships WHERE email=? OR character_name=? OR ship_name=?",
                array($emailname, $character, $shipname)
            );
            if ($chk && !$chk->EOF)
            {
                echo "<p style='color:#f55;'>Name conflict — email, character, or ship name already in use.</p>";
                $errflag = 1;
            }
        }

        if (!$errflag)
        {
            $makepass   = bin2hex(random_bytes(8));
            $maxenergy  = NUM_ENERGY($xenlevel);
            $maxarmor   = NUM_ARMOR($xenlevel);
            $maxfighters = NUM_FIGHTERS($xenlevel);
            $maxtorps   = NUM_TORPEDOES($xenlevel);
            $stamp      = date("Y-m-d H:i:s");

            $r1 = $db->Execute(
                "INSERT INTO {$db->prefix}ships
                 (ship_name, ship_destroyed, character_name, password, email,
                  hull, engines, power, computer, sensors, beams, torp_launchers,
                  torps, shields, armor, armor_pts, cloak, credits, sector,
                  ship_ore, ship_organics, ship_goods, ship_energy, ship_colonists,
                  ship_fighters, ship_damage, turns, on_planet, dev_warpedit,
                  dev_genesis, dev_beacon, dev_emerwarp, dev_escapepod, dev_fuelscoop,
                  dev_minedeflector, turns_used, last_login, rating, score, team,
                  team_invite, interface, ip_address, planet_id,
                  preset1, preset2, preset3, preset4, preset5,
                  trade_colonists, trade_fighters, trade_torps, trade_energy,
                  cleared_defences, lang, dev_lssd)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                array(
                    $shipname, 'N', $character, $makepass, $emailname,
                    $xenlevel, $xenlevel, $xenlevel, $xenlevel, $xenlevel,
                    $xenlevel, $xenlevel, $maxtorps, $xenlevel, $xenlevel,
                    $maxarmor, $xenlevel, $start_credits, $sector,
                    0, 0, 0, $maxenergy, 0, $maxfighters, 0, $start_turns,
                    'N', 0, 0, 0, 0, 'N', 'N', 0, 0, $stamp,
                    0, 0, 0, 0, 'N', '127.0.0.1', 0,
                    0, 0, 0, 0, 0,
                    'Y', 'N', 'N', 'Y', null, $default_lang, 'Y'
                )
            );
            db_op_result($db, $r1, __LINE__, __FILE__, $db_logging);

            $r2 = $db->Execute(
                "INSERT INTO {$db->prefix}xenobe (xenobe_id, active, aggression, orders) VALUES (?,?,?,?)",
                array($emailname, 'Y', 1, 1)
            );
            db_op_result($db, $r2, __LINE__, __FILE__, $db_logging);

            $r3 = $db->Execute(
                "INSERT INTO {$db->prefix}ngai (ngai_id, ship_type, faction, home_sector, hostile_on_scan) VALUES (?,?,?,?,?)",
                array($emailname, $ship_type, $faction, $home_sector, $_hostile)
            );
            db_op_result($db, $r3, __LINE__, __FILE__, $db_logging);

            if ($r1 && $r2 && $r3)
                echo "<p style='color:#0f0;'>NGAI ship <strong>$character</strong> ($ship_type, faction $faction) created successfully.</p>";
            else
                echo "<p style='color:#f55;'>Error during creation: " . htmlspecialchars($db->ErrorMsg()) . "</p>";
        }

        echo "<input type='hidden' name='menu' value='createnew'>";
        echo "<input type='submit' value='Create another'>";
        $button_main = true;
    }

    echo "</form>";
}

// ═════════════════════════════════════════════════════════════════════════════
// EDIT NGAI SHIP
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'editngai')
{
    echo "<h2>Edit NGAI Ship</h2>";
    echo "<form action='ngai_control.php' method='post'>";

    if (empty($operation))
    {
        // List all NGAI ships for selection
        $res = $db->Execute(
            "SELECT s.email, s.character_name, s.ship_name, n.ship_type, n.faction
             FROM {$db->prefix}ships s
             JOIN {$db->prefix}ngai n ON n.ngai_id = s.email
             WHERE s.ship_destroyed = 'N'
             ORDER BY n.faction, n.ship_type, s.character_name"
        );
        if (!$res || $res->EOF)
        {
            echo "<p>No active NGAI ships found.</p>";
        }
        else
        {
            echo "<select name='user'>";
            while (!$res->EOF)
            {
                $r = $res->fields;
                echo "<option value='" . htmlspecialchars($r['email']) . "'>"
                   . htmlspecialchars($r['character_name'])
                   . " [" . ucfirst($r['ship_type']) . ", Faction " . $r['faction'] . "]"
                   . "</option>";
                $res->MoveNext();
            }
            echo "</select>&nbsp;";
            echo "<input type='hidden' name='operation' value='showform'>";
            echo "<input type='submit' value='Edit'>";
        }
    }
    elseif ($operation === 'showform')
    {
        $sr = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE email=?", array($user));
        $nr = $db->Execute("SELECT * FROM {$db->prefix}ngai WHERE ngai_id=?", array($user));
        $xr = $db->Execute("SELECT * FROM {$db->prefix}xenobe WHERE xenobe_id=?", array($user));
        if (!$sr || $sr->EOF)
        {
            echo "<p style='color:#f55;'>Ship not found.</p>";
        }
        else
        {
            $s = $sr->fields;
            $n = $nr->fields;
            $x = $xr->fields;
            $eu = htmlspecialchars($user);
            echo "<p>Editing: <strong>" . htmlspecialchars($s['character_name']) . "</strong></p>";
            echo "<table border='0' cellpadding='5'>";
            echo "<tr><td>Character name</td><td><input type='text' name='character_name' size='25' value='" . htmlspecialchars($s['character_name']) . "'></td></tr>";
            echo "<tr><td>Ship name</td><td><input type='text' name='ship_name' size='25' value='" . htmlspecialchars($s['ship_name']) . "'></td></tr>";
            echo "<tr><td>Sector</td><td><input type='text' name='sector' size='8' value='" . (int)$s['sector'] . "'></td></tr>";
            echo "<tr><td>Credits</td><td><input type='text' name='credits' size='12' value='" . (int)$s['credits'] . "'></td></tr>";
            echo "<tr><td>Hull</td><td><input type='text' name='hull' size='5' value='" . (int)$s['hull'] . "'></td></tr>";
            echo "<tr><td>Engines</td><td><input type='text' name='engines' size='5' value='" . (int)$s['engines'] . "'></td></tr>";
            echo "<tr><td>Power</td><td><input type='text' name='power' size='5' value='" . (int)$s['power'] . "'></td></tr>";
            echo "<tr><td>Computer</td><td><input type='text' name='computer' size='5' value='" . (int)$s['computer'] . "'></td></tr>";
            echo "<tr><td>Sensors</td><td><input type='text' name='sensors' size='5' value='" . (int)$s['sensors'] . "'></td></tr>";
            echo "<tr><td>Beams</td><td><input type='text' name='beams' size='5' value='" . (int)$s['beams'] . "'></td></tr>";
            echo "<tr><td>Torp launchers</td><td><input type='text' name='torp_launchers' size='5' value='" . (int)$s['torp_launchers'] . "'></td></tr>";
            echo "<tr><td>Shields</td><td><input type='text' name='shields' size='5' value='" . (int)$s['shields'] . "'></td></tr>";
            echo "<tr><td>Armor</td><td><input type='text' name='armor' size='5' value='" . (int)$s['armor'] . "'></td></tr>";
            echo "<tr><td>Cloak</td><td><input type='text' name='cloak' size='5' value='" . (int)$s['cloak'] . "'></td></tr>";
            echo "<tr><td colspan='2'><hr></td></tr>";
            echo "<tr><td>Ship type</td><td><select name='ship_type'>";
            foreach (array('scout','fighter','spy','cruiser','battleship','freighter') as $t)
            {
                $sel = ($n['ship_type'] === $t) ? ' selected' : '';
                echo "<option value='$t'$sel>" . ucfirst($t) . "</option>";
            }
            echo "</select></td></tr>";
            echo "<tr><td>Faction (1–5)</td><td><input type='text' name='faction' size='3' value='" . (int)$n['faction'] . "'></td></tr>";
            echo "<tr><td>Home sector</td><td><input type='text' name='home_sector' size='8' value='" . (int)$n['home_sector'] . "'></td></tr>";
            echo "<tr><td>Hostile on scan</td><td><input type='checkbox' name='hostile_on_scan' value='ON' " . ($n['hostile_on_scan'] === 'Y' ? 'checked' : '') . "> Yes</td></tr>";
            echo "<tr><td>Active</td><td><input type='checkbox' name='active' value='ON' " . ($x['active'] === 'Y' ? 'checked' : '') . "> Yes</td></tr>";
            echo "</table><br>";
            echo "<input type='hidden' name='operation' value='save'>";
            echo "<input type='hidden' name='user' value='$eu'>";
            echo "<input type='submit' value='Save Changes'>";
        }
    }
    elseif ($operation === 'save')
    {
        $_active   = isset($active)         ? 'Y' : 'N';
        $_hostile  = isset($hostile_on_scan) ? 'Y' : 'N';
        $ship_type = in_array($ship_type, array('scout','fighter','spy','cruiser','battleship','freighter'))
                     ? $ship_type : 'fighter';
        $faction      = max(1, min(5, (int)$faction));
        $home_sector  = max(0, (int)$home_sector);

        $r1 = $db->Execute(
            "UPDATE {$db->prefix}ships
             SET character_name=?, ship_name=?, sector=?, credits=?,
                 hull=?, engines=?, power=?, computer=?, sensors=?,
                 beams=?, torp_launchers=?, shields=?, armor=?, cloak=?
             WHERE email=?",
            array(
                $character_name, $ship_name, (int)$sector, (int)$credits,
                (int)$hull, (int)$engines, (int)$power, (int)$computer, (int)$sensors,
                (int)$beams, (int)$torp_launchers, (int)$shields, (int)$armor, (int)$cloak,
                $user
            )
        );
        $r2 = $db->Execute(
            "UPDATE {$db->prefix}ngai SET ship_type=?, faction=?, home_sector=?, hostile_on_scan=? WHERE ngai_id=?",
            array($ship_type, $faction, $home_sector, $_hostile, $user)
        );
        $r3 = $db->Execute(
            "UPDATE {$db->prefix}xenobe SET active=? WHERE xenobe_id=?",
            array($_active, $user)
        );

        if ($r1 && $r2 && $r3)
            echo "<p style='color:#0f0;'>Changes saved.</p>";
        else
            echo "<p style='color:#f55;'>Error: " . htmlspecialchars($db->ErrorMsg()) . "</p>";

        echo "<input type='hidden' name='menu' value='editngai'>";
        echo "<input type='submit' value='Edit another'>";
        $button_main = true;
    }

    echo "</form>";
}

// ═════════════════════════════════════════════════════════════════════════════
// DELETE NGAI SHIP
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'deletengai')
{
    echo "<h2>Delete NGAI Ship</h2>";
    echo "<form action='ngai_control.php' method='post'>";

    if (empty($operation))
    {
        $res = $db->Execute(
            "SELECT s.email, s.character_name, n.ship_type, n.faction
             FROM {$db->prefix}ships s
             JOIN {$db->prefix}ngai n ON n.ngai_id = s.email
             ORDER BY s.character_name"
        );
        if (!$res || $res->EOF)
        {
            echo "<p>No NGAI ships found.</p>";
        }
        else
        {
            echo "<select name='user'>";
            while (!$res->EOF)
            {
                $r = $res->fields;
                echo "<option value='" . htmlspecialchars($r['email']) . "'>"
                   . htmlspecialchars($r['character_name'])
                   . " [" . ucfirst($r['ship_type']) . ", Faction " . $r['faction'] . "]"
                   . "</option>";
                $res->MoveNext();
            }
            echo "</select>&nbsp;";
            echo "<input type='hidden' name='operation' value='confirm'>";
            echo "<input type='submit' value='Select for deletion'>";
        }
    }
    elseif ($operation === 'confirm')
    {
        $sr = $db->Execute("SELECT character_name FROM {$db->prefix}ships WHERE email=?", array($user));
        if ($sr && !$sr->EOF)
        {
            $cname = htmlspecialchars($sr->fields['character_name']);
            echo "<p style='color:#ff0;'>Delete NGAI ship <strong>$cname</strong>? This cannot be undone.</p>";
            echo "<input type='hidden' name='operation' value='dodelete'>";
            echo "<input type='hidden' name='user' value='" . htmlspecialchars($user) . "'>";
            echo "<input type='submit' value='Yes, delete'>";
        }
    }
    elseif ($operation === 'dodelete')
    {
        $db->Execute("DELETE FROM {$db->prefix}ngai_hostile WHERE ngai_id=?", array($user));
        $db->Execute("DELETE FROM {$db->prefix}ngai WHERE ngai_id=?", array($user));
        $db->Execute("DELETE FROM {$db->prefix}xenobe WHERE xenobe_id=?", array($user));
        $db->Execute("DELETE FROM {$db->prefix}ships WHERE email=?", array($user));
        echo "<p style='color:#0f0;'>NGAI ship deleted.</p>";
    }

    echo "<input type='hidden' name='menu' value='deletengai'>";
    echo "</form>";
}

// ═════════════════════════════════════════════════════════════════════════════
// CLEAR NGAI LOGS
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'clearlog')
{
    echo "<h2>Clear All NGAI Log Entries</h2>";
    echo "<form action='ngai_control.php' method='post'>";

    if (empty($operation))
    {
        echo "<p style='color:#ff0;'>This will delete all log entries for every NGAI ship.</p>";
        echo "<input type='hidden' name='operation' value='doclean'>";
        echo "<input type='submit' value='Clear NGAI logs'>";
    }
    elseif ($operation === 'doclean')
    {
        $res = $db->Execute("SELECT ship_id FROM {$db->prefix}ships WHERE email LIKE '%@ngai'");
        $count = 0;
        while ($res && !$res->EOF)
        {
            $db->Execute("DELETE FROM {$db->prefix}logs WHERE ship_id=?", array($res->fields['ship_id']));
            $count++;
            $res->MoveNext();
        }
        echo "<p style='color:#0f0;'>Cleared logs for $count NGAI ships.</p>";
    }

    echo "<input type='hidden' name='menu' value='clearlog'>";
    echo "</form>";
}

// ═════════════════════════════════════════════════════════════════════════════
// MANAGE VOID ZONES
// ═════════════════════════════════════════════════════════════════════════════
elseif ($module === 'voidzones')
{
    echo "<h2>Manage Void Zones</h2>";
    echo "<p>Void zones are zone types where NGAI ships defend territory against all human intruders.</p>";
    echo "<form action='ngai_control.php' method='post'>";

    if (isset($toggle_zone_id))
    {
        $zid = (int)$toggle_zone_id;
        // Toggle is_void for this zone
        $cur = $db->Execute("SELECT is_void FROM {$db->prefix}zones WHERE zone_id=?", array($zid));
        if ($cur && !$cur->EOF)
        {
            $new_void = ($cur->fields['is_void'] === 'Y') ? 'N' : 'Y';
            $db->Execute("UPDATE {$db->prefix}zones SET is_void=? WHERE zone_id=?", array($new_void, $zid));
            echo "<p style='color:#0f0;'>Zone $zid updated: is_void = $new_void.</p>";
        }
    }

    // Show all zones with void toggle
    $res = $db->Execute("SELECT zone_id, zone_name, allow_attack, is_void FROM {$db->prefix}zones ORDER BY zone_id");
    if (!$res || $res->EOF)
    {
        echo "<p>No zones found.</p>";
    }
    else
    {
        echo "<table border='1' cellpadding='6'>";
        echo "<tr><th>ID</th><th>Zone Name</th><th>Allow Attack</th><th>Is Void</th><th>Action</th></tr>";
        while (!$res->EOF)
        {
            $z = $res->fields;
            $void_label = ($z['is_void'] === 'Y') ? "<span style='color:#0f0;'>YES</span>" : "No";
            echo "<tr><td>" . (int)$z['zone_id'] . "</td>";
            echo "<td>" . htmlspecialchars($z['zone_name']) . "</td>";
            echo "<td>" . htmlspecialchars($z['allow_attack']) . "</td>";
            echo "<td>$void_label</td>";
            echo "<td><button type='submit' name='toggle_zone_id' value='" . (int)$z['zone_id'] . "'>Toggle Void</button></td></tr>";
            $res->MoveNext();
        }
        echo "</table>";
    }

    echo "<input type='hidden' name='menu' value='voidzones'>";
    echo "</form>";
}

else
{
    echo "<p>Unknown module.</p>";
}

// ── Return to main menu button ────────────────────────────────────────────────
if ($button_main)
    ngai_main_button($module);

include "footer.php";
?>
