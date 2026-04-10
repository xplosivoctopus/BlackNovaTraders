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
// File: mail.php


include "config/config.php";

// New database driven language entries
load_languages($db, $lang, array('mail', 'common', 'global_funcs', 'global_includes', 'global_funcs', 'combat', 'footer', 'news'), $langvars, $db_logging);

$title = $l_mail_title;
include "header.php";
bigtitle();
echo "<div style='color:#fff; width:400px; text-align:left; padding:6px;'>Password recovery by e-mail is no longer available.</div>\n";
echo "<br>\n";
echo "<div style='font-size:14px; font-weight:bold; color:#f00;'>Use the password you chose when creating the account, or have an administrator reset it directly.</div>\n";

include "footer.php";
?>
