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
// File: preset.php

include "config/config.php";
updatecookie();

// New database driven language entries
load_languages($db, $lang, array('presets', 'common', 'global_includes', 'global_funcs', 'combat', 'footer', 'news'), $langvars, $db_logging);

$title = $l_pre_title;
include "header.php";

if (checklogin())
{
    die();
}

$result = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE email='$username'");
db_op_result ($db, $result, __LINE__, __FILE__, $db_logging);
$playerinfo = $result->fields;

bigtitle();

if (!isset($change))
{
    echo "<form action='preset.php' method='post'>";
    echo "<div style='padding:2px;'>Preset 1: <input type='text' name='preset1' size='6' maxlength='6' value='{$playerinfo['preset1']}'></div>";
    echo "<div style='padding:2px;'>Preset 2: <input type='text' name='preset2' size='6' maxlength='6' value='{$playerinfo['preset2']}'></div>";
    echo "<div style='padding:2px;'>Preset 3: <input type='text' name='preset3' size='6' maxlength='6' value='{$playerinfo['preset3']}'></div>";
    echo "<div style='padding:2px;'>Preset 4: <input type='text' name='preset4' size='6' maxlength='6' value='{$playerinfo['preset4']}'></div>";
    echo "<div style='padding:2px;'>Preset 5: <input type='text' name='preset5' size='6' maxlength='6' value='{$playerinfo['preset5']}'></div>";
    echo "<input type='hidden' name='change' value='1'>";
    echo "<div style='padding:2px;'><input type='submit' value={$l_pre_save}></div>";
    echo "</FORM>";
}
else
{
    $preset1 = round(abs($preset1));
    $preset2 = round(abs($preset2));
    $preset3 = round(abs($preset3));
    $preset4 = round(abs(isset($preset4) ? $preset4 : 0));
    $preset5 = round(abs(isset($preset5) ? $preset5 : 0));
    $exceed = 0;
    foreach (array(1=>$preset1, 2=>$preset2, 3=>$preset3, 4=>$preset4, 5=>$preset5) as $num => $val)
    {
        if ($val >= $sector_max)
        {
            $msg = str_replace("[preset]", $num, $l_pre_exceed);
            $msg = str_replace("[sector_max]", ($sector_max-1), $msg);
            echo $msg . "<br><br>";
            $exceed = 1;
            break;
        }
    }
    if (!$exceed)
    {
        $update = $db->Execute("UPDATE {$db->prefix}ships SET preset1=$preset1,preset2=$preset2,preset3=$preset3,preset4=$preset4,preset5=$preset5 WHERE ship_id=$playerinfo[ship_id]");
        db_op_result ($db, $update, __LINE__, __FILE__, $db_logging);
        $l_pre_set = str_replace("[preset1]", "<a href=rsmove.php?engage=1&destination=$preset1>$preset1</a>", $l_pre_set);
        $l_pre_set = str_replace("[preset2]", "<a href=rsmove.php?engage=1&destination=$preset2>$preset2</a>", $l_pre_set);
        $l_pre_set = str_replace("[preset3]", "<a href=rsmove.php?engage=1&destination=$preset3>$preset3</a>", $l_pre_set);
        echo $l_pre_set . "<br>";
        echo "Preset 4 set to <a href='rsmove.php?engage=1&destination=$preset4'>$preset4</a>, preset 5 set to <a href='rsmove.php?engage=1&destination=$preset5'>$preset5</a>.<br><br>";
    }
}

TEXT_GOTOMAIN();
include "footer.php";
?>
