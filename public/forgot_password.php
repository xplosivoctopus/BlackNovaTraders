<?php
// Blacknova Traders - A web-based massively multiplayer space combat and trading game
// Copyright (C) 2001-2012 Ron Harwood and the BNT development team
//
//  This program is free software: you can redistribute it and/or modify
//  it under the terms of the GNU Affero General Public License as
//  published by the Free Software Foundation, either version 3 of the
//  License, or (at your option) any later version.
//
// File: forgot_password.php

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

$title = "Password Recovery";
include "header.php";
bigtitle();

$sent = false;
$error = '';

if (isset($_POST['submit_reset']))
{
    bnt_require_csrf();
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        $error = "Please enter a valid email address.";
    }
    else
    {
        // Check if account exists
        $res = $db->Execute("SELECT ship_id, character_name FROM {$db->prefix}ships WHERE email=? AND ship_destroyed='N'", array($email));
        db_op_result($db, $res, __LINE__, __FILE__, $db_logging);

        // Always show success message to avoid email enumeration
        if ($res && !$res->EOF)
        {
            $player = $res->fields;

            // Delete any existing reset tokens for this email
            $db->Execute("DELETE FROM {$db->prefix}password_resets WHERE email=?", array($email));

            // Generate a secure token
            $token = bin2hex(random_bytes(32));
            $expires = time() + 3600; // 1 hour

            $db->Execute("INSERT INTO {$db->prefix}password_resets (email, token, expires) VALUES (?, ?, ?)",
                         array($email, $token, $expires));

            // Build reset URL
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
            $link_to_game = rtrim($scheme . ltrim($gamedomain, ".") . $gamepath, '/');
            $reset_url = $link_to_game . "/reset_password.php?token=" . urlencode($token) . ($link ? "&lang=" . urlencode($lang) : '');

            $subject  = "Password Reset — {$game_name}";
            $body     = "Hello {$player['character_name']},\r\n\r\n";
            $body    .= "A password reset was requested for your account on {$game_name}.\r\n\r\n";
            $body    .= "Click the link below to set a new password (valid for 1 hour):\r\n";
            $body    .= $reset_url . "\r\n\r\n";
            $body    .= "If you did not request this, ignore this email — your password will not change.\r\n\r\n";
            $body    .= "— {$adminname}";
            bnt_send_email($email, $subject, $body);
        }

        $sent = true;
    }
}

echo "<div class='index-welcome'>\n";
echo "<h1 class='index-h1'>Password Recovery</h1>\n";

if ($sent)
{
    echo "<p>If an account exists for that email address, a password reset link has been sent. Please check your inbox.</p>\n";
    echo "<p><a href='index.php{$link}'>Return to login</a></p>\n";
}
else
{
    if ($error)
    {
        echo "<p style='color:#f55;'>{$error}</p>\n";
    }
    echo "<p>Enter the email address for your account and we will send you a reset link.</p>\n";
    echo "<form action='forgot_password.php{$link}' method='post'>\n";
    echo "  <dl class='twocolumn-form'>\n";
    echo "    <dt><label for='email'>Email:</label></dt>\n";
    echo "    <dd><input type='email' id='email' name='email' size='30' maxlength='40'></dd>\n";
    echo "  </dl>\n";
    echo "  <br style='clear:both'><br>\n";
    echo "  <div style='text-align:center'>\n";
    echo "    <input class='button green' type='submit' name='submit_reset' value='Send Reset Link'>\n";
    echo "  </div>\n";
    echo "</form>\n";
    echo "<br><p><a href='index.php{$link}'>Back to login</a></p>\n";
}

echo "</div>\n";

include "footer.php";
?>
