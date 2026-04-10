<?php
// Blacknova Traders - A web-based massively multiplayer space combat and trading game
// Copyright (C) 2001-2012 Ron Harwood and the BNT development team
//
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
// File: reset_password.php

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

load_languages($db, $lang, array('login', 'common', 'global_includes', 'global_funcs', 'footer', 'news'), $langvars, $db_logging);

$title = "Reset Password";
include "header.php";
bigtitle();

$token = isset($_GET['token']) ? trim($_GET['token']) : (isset($_POST['token']) ? trim($_POST['token']) : '');
$error = '';
$success = false;

echo "<div class='index-welcome'>\n";
echo "<h1 class='index-h1'>Reset Password</h1>\n";

// Validate token
if (empty($token) || !preg_match('/^[0-9a-f]{64}$/', $token))
{
    echo "<p style='color:#f55;'>Invalid or missing reset token.</p>\n";
    echo "<p><a href='forgot_password.php{$link}'>Request a new reset link</a></p>\n";
    echo "</div>\n";
    include "footer.php";
    die();
}

$res = $db->Execute("SELECT * FROM {$db->prefix}password_resets WHERE token=? AND expires > ?",
                    array($token, time()));
db_op_result($db, $res, __LINE__, __FILE__, $db_logging);

if (!$res || $res->EOF)
{
    echo "<p style='color:#f55;'>This reset link has expired or is invalid. Reset links are only valid for 1 hour.</p>\n";
    echo "<p><a href='forgot_password.php{$link}'>Request a new reset link</a></p>\n";
    echo "</div>\n";
    include "footer.php";
    die();
}

$reset_row = $res->fields;
$reset_email = $reset_row['email'];

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
        $db->Execute("UPDATE {$db->prefix}ships SET password=? WHERE email=?", array($hashed, $reset_email));
        $db->Execute("DELETE FROM {$db->prefix}password_resets WHERE email=?", array($reset_email));

        $success = true;
    }
}

if ($success)
{
    echo "<p style='color:#0f0;'>Your password has been updated successfully.</p>\n";
    echo "<p><a href='index.php{$link}'>Return to login</a></p>\n";
}
else
{
    if ($error)
    {
        echo "<p style='color:#f55;'>{$error}</p>\n";
    }
    echo "<p>Enter a new password for <strong>" . htmlspecialchars($reset_email) . "</strong>.</p>\n";
    echo "<form action='reset_password.php' method='post'>\n";
    echo "  <input type='hidden' name='token' value='" . htmlspecialchars($token) . "'>\n";
    if ($link) echo "  <input type='hidden' name='lang' value='" . htmlspecialchars($lang) . "'>\n";
    echo "  <dl class='twocolumn-form'>\n";
    echo "    <dt><label for='new_password'>New password:</label></dt>\n";
    echo "    <dd><input type='password' id='new_password' name='new_password' size='25' maxlength='72'></dd>\n";
    echo "    <dt><label for='confirm_password'>Confirm password:</label></dt>\n";
    echo "    <dd><input type='password' id='confirm_password' name='confirm_password' size='25' maxlength='72'></dd>\n";
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
