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
// File: includes/text_gotomain.php

if (preg_match("/text_gotomain.php/i", $_SERVER['PHP_SELF'])) {
      echo "You can not access this file directly!";
      die();
}

if (!function_exists('bnt_nav_button_html'))
{
    function bnt_nav_button_html(string $href, string $label): string
    {
        return "<div class='bnt-nav-actions'><a class='bnt-nav-button' href='" . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</a></div>";
    }
}

function TEXT_GOTOMAIN (string $href = 'main.php', string $label = '< Dashboard')
{
    echo bnt_nav_button_html($href, $label);
}
?>
