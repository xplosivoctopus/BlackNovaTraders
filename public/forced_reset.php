<?php
// Blacknova Traders - A web-based massively multiplayer space combat and trading game
// Copyright (C) 2001-2012 Ron Harwood and the BNT development team
//
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
// File: forced_reset.php
// Handles admin-mandated password reset on next login.

include "config/config.php";

// Must be logged in AND flagged for forced reset
if (empty($_SESSION['logged_in']) || empty($_SESSION['force_password_reset']))
{
    header("Location: main.php");
    exit;
}

load_languages($db, $lang, array('login', 'common', 'global_includes', 'global_funcs', 'footer', 'news'), $langvars, $db_logging);

$title = "Set New Password";
include "header.php";
bigtitle();

$email = $_SESSION['username'] ?? '';
$error = '';
$success = false;

if (isset($_POST['submit_new_password']))
{
    bnt_require_csrf();
    $new_pass  = isset($_POST['new_password'])     ? $_POST['new_password']     : '';
    $conf_pass = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if (strlen($new_pass) < 8)
    {
        $error = "Password must be at least 8 characters.";
    }
    elseif ($new_pass !== $conf_pass)
    {
        $error = "Passwords do not match.";
    }
    else
    {
        $hashed = bnt_password_hash_value($new_pass);
        $db->Execute("UPDATE {$db->prefix}ships SET password=?, force_password_reset='N' WHERE email=?",
                     array($hashed, $email));
        unset($_SESSION['force_password_reset']);
        $success = true;
    }
}

echo "<div class='index-welcome'>\n";
echo "<h1 class='index-h1'>New Password Required</h1>\n";

if ($success)
{
    echo "<p style='color:#00ff88;'>Your password has been updated. You can now continue.</p>\n";
    echo "<p><a href='main.php'>Enter the game &rarr;</a></p>\n";
}
else
{
    echo "<p style='color:#c8e8ff;'>An administrator has required you to set a new password before continuing.</p>\n";

    if ($error)
    {
        echo "<p style='color:#ff6680;'>" . htmlspecialchars($error) . "</p>\n";
    }

    echo "<form action='forced_reset.php' method='post'>\n";
    echo "  " . bnt_csrf_input() . "\n";
    echo "  <dl class='twocolumn-form'>\n";
    echo "    <dt><label for='new_password'>New password:</label></dt>\n";
    echo "    <dd><input type='password' id='new_password' name='new_password' size='25' maxlength='72' autocomplete='new-password'></dd>\n";
    echo "    <dt><label for='confirm_password'>Confirm password:</label></dt>\n";
    echo "    <dd><input type='password' id='confirm_password' name='confirm_password' size='25' maxlength='72' autocomplete='new-password'></dd>\n";
    echo "  </dl>\n";
    echo "  <br style='clear:both'><br>\n";
    echo "  <div style='text-align:center'>\n";
    echo "    <input class='button green' type='submit' name='submit_new_password' value='Set New Password'>\n";
    echo "  </div>\n";
    echo "</form>\n";
}

echo "</div>\n";

include "footer.php";
?>
