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
// File: new2.php

include "config/config.php";

if (!isset($_GET['lang']))
{
    $_GET['lang'] = null;
    $lang = $default_lang;
    $link = '';
}
else
{
    $lang = $_GET['lang'];
    $link = "?lang=" . $lang;
}

// New database driven language entries
load_languages($db, $lang, array('new', 'login', 'common', 'global_includes', 'combat', 'footer', 'news'), $langvars, $db_logging);

$title = $l_new_title2;
include "header.php";
bigtitle ();

if ($account_creation_closed)
{
    die ($l_new_closed_message);
}

# Get the user supplied post vars.
$username  = null;
$shipname  = null;
$character = null;
$password = null;
$password_confirm = null;
if (array_key_exists('character', $_POST))
{
    $character  = $_POST['character'];
}

if (array_key_exists('shipname', $_POST))
{
    $shipname   = $_POST['shipname'];
}

if (array_key_exists('username', $_POST))
{
    $username   = $_POST['username'];
}

if (array_key_exists('password', $_POST))
{
    $password = (string) $_POST['password'];
}

if (array_key_exists('password_confirm', $_POST))
{
    $password_confirm = (string) $_POST['password_confirm'];
}

if (array_key_exists('lang', $_POST))
{
    $lang   = $_POST['lang'];
}
else
{
    $lang = $default_lang;
}

$character = htmlspecialchars ($character);
$shipname = htmlspecialchars ($shipname);
$character = preg_replace ('/[^A-Za-z0-9\_\s\-\.\']+/', ' ', $character);
$shipname = preg_replace ('/[^A-Za-z0-9\_\s\-\.\']+/', ' ', $shipname);

// $username = $_POST['username']; // This needs to STAY before the db query

$result = $db->Execute(
    "SELECT email, character_name, ship_name FROM {$db->prefix}ships WHERE email=? OR character_name=? OR ship_name=?",
    array($username, $character, $shipname)
);
db_op_result ($db, $result, __LINE__, __FILE__, $db_logging);
$flag = 0;

if ($username == '' || $character == '' || $shipname == '' || $password == '' || $password_confirm == '')
{
    echo $l_new_blank . ' Password fields may not be blank.<br>';
    $flag = 1;
}

if ($password !== $password_confirm)
{
    echo "The password fields do not match.<br>";
    $flag = 1;
}

if (strlen($password) < 8)
{
    echo "Password must be at least 8 characters long.<br>";
    $flag = 1;
}

while (!$result->EOF)
{
    $row = $result->fields;
    if (strtolower ($row['email']) == strtolower ($username))
    {
        echo $l_new_inuse . '<br>';
        $flag = 1;
    }
    if (strtolower ($row['character_name']) == strtolower($character))
    {
        $l_new_inusechar=str_replace("[character]", $character, $l_new_inusechar);
        echo $l_new_inusechar . '<br>';
        $flag = 1;
    }
    if (strtolower ($row['ship_name']) == strtolower ($shipname))
    {
        $l_new_inuseship = str_replace ("[shipname]", $shipname, $l_new_inuseship);
        echo $l_new_inuseship . '<br>';
        $flag = 1;
    }
    $result->MoveNext();
}

if ($flag == 0)
{
    $stamp=date("Y-m-d H:i:s");
    $query = $db->Execute("SELECT MAX(turns_used + turns) AS mturns FROM {$db->prefix}ships");
    db_op_result ($db, $query, __LINE__, __FILE__, $db_logging);
    $res = $query->fields;

    $mturns = $res['mturns'];
    if ($mturns === null || $mturns === false || $mturns === '')
    {
        $mturns = $start_turns;
    }
    else
    {
        $mturns = (int)$mturns;
    }

    if ($mturns > $max_turns)
    {
        $mturns = $max_turns;
    }

    $hashedPassword = bnt_password_hash_value($password);
    $result2 = $db->Execute("INSERT INTO {$db->prefix}ships (ship_name, ship_destroyed, character_name, password, email, armor_pts, credits, ship_energy, ship_fighters, turns, on_planet, dev_warpedit, dev_genesis, dev_beacon, dev_emerwarp, dev_escapepod, dev_fuelscoop, dev_minedeflector, last_login, ip_address, trade_colonists, trade_fighters, trade_torps, trade_energy, cleared_defences, lang, dev_lssd)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)", array ($shipname, 'N', $character, $hashedPassword, $username, $start_armor, $start_credits, $start_energy, $start_fighters, $mturns, 'N', $start_editors, $start_genesis, $start_beacon, $start_emerwarp, $escape, $scoop, $start_minedeflectors, $stamp, $ip, 'Y', 'N', 'N', 'Y', NULL, $lang, $start_lssd));
    db_op_result ($db, $result2, __LINE__, __FILE__, $db_logging);

    if (!$result2)
    {
        echo $db->ErrorMsg() . "<br>";
    }
    else
    {
        $result2 = $db->Execute("SELECT ship_id FROM {$db->prefix}ships WHERE email=?", array($username));
        db_op_result ($db, $result2, __LINE__, __FILE__, $db_logging);

        $shipid = $result2->fields;

        log_move ($db, $shipid['ship_id'], 0); // A new player is placed into sector 0. Make sure his movement log shows it, so they see it on the galaxy map.
        $resx = $db->Execute("INSERT INTO {$db->prefix}zones VALUES(NULL, ?, ?, 'N', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 'Y', 0)", array($character . "'s Territory", $shipid['ship_id']));
        db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);

        $resx = $db->Execute("INSERT INTO {$db->prefix}ibank_accounts (ship_id,balance,loan) VALUES(?,0,0)", array($shipid['ship_id']));
        db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);

        echo "Account created successfully.<br><br>";
        echo "<a href=index.php" . $link . ">$l_clickme</A> $l_new_login";
    }
}
else
{
    $l_new_err = str_replace ("[here]", "<a href='new.php'>" . $l_here . "</a>",$l_new_err);
    echo $l_new_err;
}

include "footer.php";
?>
