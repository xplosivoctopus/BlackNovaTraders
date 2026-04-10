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
// File: admin.php

include "config/config.php";
bnt_require_admin();

// New database driven language entries
load_languages($db, $lang, array('admin', 'common', 'global_includes', 'combat', 'footer', 'news'), $langvars, $db_logging);

$title = $l->get('l_admin_title');
include "header.php";

connectdb ();
bigtitle ();
$swordfish = '';

function checked($yesno)
{
    return(($yesno == "Y") ? "checked" : "");
}

function yesno($onoff)
{
    return(($onoff == "ON") ? "Y" : "N");
}

function bnt_admin_modules(): array
{
    return array(
        'addons' => array('label' => 'Addons', 'summary' => 'Discover, enable, and disable drop-in addon folders.'),
        'motd' => array('label' => 'MOTD / Announcement', 'summary' => 'Manage the live message shown to players.'),
        'useredit' => array('label' => 'User Editor', 'summary' => 'Edit player accounts, ships, cargo, credits, and devices.'),
        'univedit' => array('label' => 'Universe Editor', 'summary' => 'Recalculate sector distances for a resized universe.'),
        'sectedit' => array('label' => 'Sector Editor', 'summary' => 'Edit sector names, zones, beacons, ports, and geometry.'),
        'planedit' => array('label' => 'Planet Editor', 'summary' => 'Edit planets, ownership, production, resources, and defenses.'),
        'linkedit' => array('label' => 'Link Editor', 'summary' => 'Reserved for future warp-link editing improvements.'),
        'zoneedit' => array('label' => 'Zone Editor', 'summary' => 'Change zone restrictions and hull limits.'),
        'ipedit' => array('label' => 'IP Bans', 'summary' => 'Review addresses and add or remove bans.'),
        'logview' => array('label' => 'Logs', 'summary' => 'Open admin and player activity logs.')
    );
}

function bnt_admin_label(?string $module): string
{
    $modules = bnt_admin_modules();
    if ($module !== null && isset($modules[$module])) {
        return $modules[$module]['label'];
    }

    return 'Admin Home';
}

function bnt_admin_render_styles(): void
{
    echo <<<HTML
<style>
/* ══════════════════════════════════════════════════════
   BLACKNOVA ADMIN — MISSION CONTROL INTERFACE
   Draws from main.css design tokens (--cyan, --font-hud…)
   ══════════════════════════════════════════════════════ */

/* ── Shell ─────────────────────────────────────────── */
.admin-shell {
  width: min(1300px, calc(100% - 24px));
  margin: 0 auto 40px;
  color: var(--text-prime, #ddeeff);
}

/* ── Sticky nav bar ────────────────────────────────── */
.admin-nav {
  position: sticky;
  top: 0;
  z-index: 200;
  display: flex;
  align-items: stretch;
  background: rgba(2, 7, 16, 0.97);
  border: 1px solid rgba(0, 238, 255, 0.14);
  border-top: none;
  border-radius: 0 0 5px 5px;
  backdrop-filter: blur(22px);
  box-shadow: 0 6px 40px rgba(0,0,0,0.7), 0 1px 0 rgba(0,238,255,0.08);
  margin-bottom: 22px;
  overflow-x: auto;
  scrollbar-width: none;
}
.admin-nav::-webkit-scrollbar { display: none; }
.admin-nav::after {
  content: '';
  position: absolute;
  bottom: 0; left: 0; right: 0;
  height: 1px;
  background: linear-gradient(90deg,
    transparent 0%,
    rgba(0,238,255,0.0) 5%,
    rgba(0,238,255,0.45) 35%,
    rgba(191,95,255,0.35) 65%,
    rgba(0,238,255,0.45) 85%,
    transparent 100%);
  background-size: 200% 100%;
  animation: topBarFlow 7s linear infinite;
  pointer-events: none;
}

.admin-nav__brand {
  display: flex;
  align-items: center;
  gap: 11px;
  padding: 0 22px 0 16px;
  border-right: 1px solid rgba(0,238,255,0.10);
  flex-shrink: 0;
  text-decoration: none;
}
.admin-nav__logo {
  width: 30px;
  height: 30px;
  border: 1px solid rgba(0,238,255,0.45);
  border-radius: 3px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-family: var(--font-hud, monospace);
  font-size: 13px;
  font-weight: 900;
  color: #00eeff;
  text-shadow: 0 0 10px #00eeff;
  background: rgba(0,238,255,0.06);
  flex-shrink: 0;
  line-height: 1;
}
.admin-nav__wordmark { line-height: 1.15; }
.admin-nav__title {
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.22em;
  color: rgba(0,238,255,0.8);
  text-transform: uppercase;
  display: block;
}
.admin-nav__subtitle {
  font-family: var(--font-hud, monospace);
  font-size: 7.5px;
  letter-spacing: 0.16em;
  color: rgba(0,238,255,0.35);
  text-transform: uppercase;
  display: block;
}

.admin-nav__links {
  display: flex;
  align-items: stretch;
  flex: 1;
  overflow-x: auto;
  scrollbar-width: none;
}
.admin-nav__links::-webkit-scrollbar { display: none; }

.admin-tab {
  display: flex;
  align-items: center;
  padding: 0 15px;
  min-height: 50px;
  color: var(--text-dim, rgba(78,120,152,1));
  text-decoration: none;
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  white-space: nowrap;
  border-right: 1px solid rgba(0,238,255,0.05);
  position: relative;
  transition: color 0.2s, background 0.2s;
}
.admin-tab::after {
  content: '';
  position: absolute;
  bottom: 0; left: 14px; right: 14px;
  height: 2px;
  background: #00eeff;
  border-radius: 2px 2px 0 0;
  transform: scaleX(0);
  transition: transform 0.22s cubic-bezier(.34,1.3,.64,1);
}
.admin-tab:hover {
  color: #c8e8ff;
  background: rgba(0,238,255,0.04);
}
.admin-tab:hover::after {
  transform: scaleX(0.5);
  background: rgba(0,238,255,0.45);
}
.admin-tab--active {
  color: #00eeff !important;
  background: rgba(0,238,255,0.07) !important;
  text-shadow: 0 0 9px rgba(0,238,255,0.4);
}
.admin-tab--active::after {
  transform: scaleX(1) !important;
}

.admin-nav__system {
  display: flex;
  align-items: stretch;
  border-left: 1px solid rgba(0,238,255,0.10);
  margin-left: auto;
  flex-shrink: 0;
}
.admin-tab--sys {
  color: rgba(245,158,11,0.5);
  font-size: 9px;
}
.admin-tab--sys:hover {
  color: #f59e0b !important;
  background: rgba(245,158,11,0.05) !important;
}
.admin-tab--sys::after { background: #f59e0b !important; }
.admin-tab--back {
  color: rgba(191,95,255,0.5);
  font-size: 9px;
}
.admin-tab--back:hover {
  color: #bf5fff !important;
  background: rgba(191,95,255,0.05) !important;
}
.admin-tab--back::after { background: #bf5fff !important; }

/* ── Hero banner ───────────────────────────────────── */
.admin-hero {
  position: relative;
  padding: 30px 30px 26px;
  margin-bottom: 22px;
  border: 1px solid rgba(0,238,255,0.16);
  background: linear-gradient(135deg,
    rgba(4,15,30,0.99) 0%,
    rgba(8,26,50,0.97) 50%,
    rgba(2,10,22,0.99) 100%);
  box-shadow:
    0 0 0 1px rgba(0,238,255,0.05),
    0 10px 40px rgba(0,0,0,0.5),
    inset 0 1px 0 rgba(255,255,255,0.04);
  border-radius: 5px;
  overflow: hidden;
}
.admin-hero::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg,
    transparent 0%, #00eeff 25%, #bf5fff 60%, #00eeff 80%, transparent 100%);
  background-size: 200% 100%;
  animation: topBarFlow 5s linear infinite;
}
.admin-hero::after {
  content: '';
  position: absolute;
  inset: 0;
  background-image:
    linear-gradient(rgba(0,238,255,0.025) 1px, transparent 1px),
    linear-gradient(90deg, rgba(0,238,255,0.025) 1px, transparent 1px);
  background-size: 44px 44px;
  pointer-events: none;
}
.admin-hero__inner {
  position: relative;
  z-index: 1;
}

.admin-eyebrow {
  display: flex;
  align-items: center;
  gap: 8px;
  font-family: var(--font-hud, monospace);
  font-size: 10px;
  font-weight: 700;
  letter-spacing: 0.24em;
  color: rgba(0,238,255,0.65);
  text-transform: uppercase;
  margin-bottom: 12px;
}
.admin-eyebrow::before {
  content: '';
  width: 7px;
  height: 7px;
  border-radius: 50%;
  background: #00eeff;
  box-shadow: 0 0 9px #00eeff, 0 0 20px rgba(0,238,255,0.5);
  animation: indexFlicker 2.8s ease-in-out infinite;
  flex-shrink: 0;
}

.admin-title {
  margin: 0 0 12px;
  font-family: var(--font-hud, monospace);
  font-size: 30px;
  font-weight: 700;
  letter-spacing: 0.04em;
  color: #ffffff;
  text-shadow: 0 0 40px rgba(0,238,255,0.22);
  line-height: 1.1;
}

.admin-subtitle {
  margin: 0;
  color: rgba(170,200,225,0.65);
  font-size: 13px;
  line-height: 1.65;
  max-width: 780px;
}

/* ── Module card grid ──────────────────────────────── */
.admin-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.admin-card {
  position: relative;
  padding: 22px 22px 20px;
  border: 1px solid rgba(0,238,255,0.11);
  background: linear-gradient(160deg,
    rgba(6,20,38,0.98) 0%,
    rgba(3,11,24,0.99) 100%);
  box-shadow: 0 4px 20px rgba(0,0,0,0.35);
  border-radius: 5px;
  transition: border-color 0.25s, box-shadow 0.25s, transform 0.22s;
  overflow: hidden;
}
.admin-card::before {
  content: '';
  position: absolute;
  top: 0; left: 0;
  width: 3px; height: 100%;
  background: linear-gradient(180deg,
    rgba(0,238,255,0.65) 0%,
    rgba(191,95,255,0.3) 100%);
  opacity: 0.45;
  transition: opacity 0.25s;
}
.admin-card:hover {
  border-color: rgba(0,238,255,0.32);
  box-shadow: 0 6px 28px rgba(0,0,0,0.45), 0 0 22px rgba(0,238,255,0.07);
  transform: translateY(-2px);
}
.admin-card:hover::before { opacity: 1; }

.admin-card__icon {
  font-size: 20px;
  margin-bottom: 12px;
  line-height: 1;
  opacity: 0.7;
}
.admin-card h3 {
  margin: 0 0 8px;
  font-family: var(--font-hud, monospace);
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.10em;
  color: #ffffff;
  text-transform: uppercase;
}
.admin-card p {
  margin: 0 0 18px;
  color: rgba(170,200,225,0.55);
  font-size: 12px;
  line-height: 1.58;
}
.admin-card__link {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #00eeff;
  text-decoration: none;
  transition: gap 0.2s, text-shadow 0.2s;
}
.admin-card__link::after { content: '→'; }
.admin-card__link:hover {
  text-shadow: 0 0 12px rgba(0,238,255,0.65);
  gap: 11px;
}

/* ── Content panel ─────────────────────────────────── */
.admin-panel {
  position: relative;
  padding: 26px 28px 24px;
  margin-bottom: 22px;
  border: 1px solid rgba(0,238,255,0.14);
  background: linear-gradient(160deg,
    rgba(6,18,36,0.99) 0%,
    rgba(3,10,22,0.99) 100%);
  box-shadow:
    0 8px 40px rgba(0,0,0,0.45),
    inset 0 1px 0 rgba(255,255,255,0.03);
  border-radius: 5px;
  overflow: hidden;
}
.admin-panel::before {
  content: '';
  position: absolute;
  top: 0; left: 0; right: 0;
  height: 2px;
  background: linear-gradient(90deg,
    transparent 0%, rgba(0,238,255,0.5) 30%,
    rgba(191,95,255,0.3) 70%, transparent 100%);
  background-size: 200% 100%;
  animation: topBarFlow 8s linear infinite;
}

.admin-section-title {
  margin: 0 0 22px;
  font-family: var(--font-hud, monospace);
  font-size: 18px;
  font-weight: 700;
  letter-spacing: 0.07em;
  color: #ffffff;
}

/* ── Action button row ─────────────────────────────── */
.admin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 26px;
  padding-top: 22px;
  border-top: 1px solid rgba(0,238,255,0.07);
}
.admin-btn,
.admin-actions a {
  display: inline-flex;
  align-items: center;
  padding: 9px 18px;
  border: 1px solid rgba(0,238,255,0.28);
  background: rgba(0,238,255,0.05);
  color: #c0e0ff;
  text-decoration: none;
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 600;
  letter-spacing: 0.15em;
  text-transform: uppercase;
  border-radius: 3px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
}
.admin-btn:hover,
.admin-actions a:hover {
  border-color: rgba(0,238,255,0.70);
  background: rgba(0,238,255,0.11);
  color: #ffffff;
  box-shadow: 0 0 16px rgba(0,238,255,0.18);
}

/* ── Form elements inside admin-panel ─────────────── */
.admin-panel table {
  border-collapse: collapse;
}
.admin-panel td,
.admin-panel th {
  padding: 7px 12px;
  vertical-align: middle;
  color: var(--text-prime, #ddeeff);
  font-size: 13px;
}
/* Label column */
.admin-panel td:first-child:not([colspan]):not([align]) {
  color: rgba(78,120,152,1);
  font-family: var(--font-hud, monospace);
  font-size: 10.5px;
  font-weight: 600;
  letter-spacing: 0.10em;
  text-transform: uppercase;
  white-space: nowrap;
}
.admin-panel tr:hover > td { background: rgba(0,238,255,0.018); }

/* Text / email / number inputs */
.admin-panel input[type=text],
.admin-panel input[type=email],
.admin-panel input[type=password],
.admin-panel input[type=number] {
  background: rgba(1,8,20,0.98);
  border: 1px solid rgba(0,238,255,0.26);
  border-radius: 3px;
  color: #00eeff;
  padding: 7px 11px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  outline: none;
  caret-color: #00eeff;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.admin-panel input[type=text]:focus,
.admin-panel input[type=email]:focus,
.admin-panel input[type=password]:focus,
.admin-panel input[type=number]:focus {
  border-color: #00eeff;
  box-shadow: 0 0 0 2px rgba(0,238,255,0.11), 0 0 14px rgba(0,238,255,0.09);
}

/* Select */
.admin-panel select {
  background: rgba(1,8,20,0.98);
  border: 1px solid rgba(0,238,255,0.26);
  border-radius: 3px;
  color: #00eeff;
  padding: 7px 11px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  outline: none;
  cursor: pointer;
  transition: border-color 0.2s;
}
.admin-panel select:focus { border-color: #00eeff; box-shadow: 0 0 0 2px rgba(0,238,255,0.11); }
.admin-panel select[size] {
  min-height: 170px;
  padding: 6px;
}
.admin-panel select option { background: #030f1c; color: #ddeeff; }
.admin-panel select option:checked { background: rgba(0,238,255,0.18); color: #fff; }

/* Textarea */
.admin-panel textarea {
  display: block;
  background: rgba(1,8,20,0.98);
  border: 1px solid rgba(0,238,255,0.26);
  border-radius: 3px;
  color: #c8e8ff;
  padding: 10px 13px;
  font-family: 'Courier New', monospace;
  font-size: 13px;
  outline: none;
  resize: vertical;
  min-height: 150px;
  line-height: 1.55;
  transition: border-color 0.2s, box-shadow 0.2s;
}
.admin-panel textarea:focus {
  border-color: #00eeff;
  box-shadow: 0 0 0 2px rgba(0,238,255,0.11);
}

/* Submit / button */
.admin-panel input[type=submit],
.admin-panel button[type=submit],
.admin-panel button {
  display: inline-flex;
  align-items: center;
  padding: 9px 20px;
  border: 1px solid rgba(0,238,255,0.38);
  background: rgba(0,238,255,0.07);
  color: #c0e0ff;
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  border-radius: 3px;
  cursor: pointer;
  transition: border-color 0.2s, background 0.2s, color 0.2s, box-shadow 0.2s;
  -webkit-appearance: none;
  appearance: none;
}
.admin-panel input[type=submit]:hover,
.admin-panel button[type=submit]:hover,
.admin-panel button:hover {
  border-color: rgba(0,238,255,0.75);
  background: rgba(0,238,255,0.14);
  color: #ffffff;
  box-shadow: 0 0 18px rgba(0,238,255,0.22);
}

/* Radio & checkbox */
.admin-panel input[type=radio],
.admin-panel input[type=checkbox] {
  accent-color: #00eeff;
  width: 14px;
  height: 14px;
  cursor: pointer;
  vertical-align: middle;
}
.admin-panel label {
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  color: var(--text-prime, #ddeeff);
}

/* HR inside forms */
.admin-panel hr, .admin-panel HR { border: none; border-top: 1px solid rgba(0,238,255,0.09); margin: 10px 0; }

/* Strong tags */
.admin-panel strong { color: #e0f0ff; font-family: var(--font-hud, monospace); letter-spacing: 0.05em; }

/* Old-school font colour tags still in some forms */
.admin-panel font[color="#6f0"] { color: #00ff88 !important; font-family: var(--font-hud, monospace); }

/* ── Data tables (IP bans, player lists) ───────────── */
.admin-panel table[border="1"],
.admin-data-table {
  width: 100%;
  border-collapse: collapse;
  margin: 12px 0;
}
.admin-panel table[border="1"] td,
.admin-panel table[border="1"] th {
  border: 1px solid rgba(0,238,255,0.09);
  padding: 8px 13px;
  font-size: 12px;
}
.admin-panel table[border="1"] tr:first-child td,
.admin-panel table[border="1"] tr:first-child th {
  background: rgba(0,238,255,0.07);
  color: rgba(0,238,255,0.85);
  font-family: var(--font-hud, monospace);
  font-size: 9.5px;
  font-weight: 700;
  letter-spacing: 0.13em;
  text-transform: uppercase;
  border-color: rgba(0,238,255,0.18);
}
.admin-panel table[border="1"] tr:nth-child(odd) td  { background: rgba(4,18,34,0.8);  }
.admin-panel table[border="1"] tr:nth-child(even) td { background: rgba(8,26,52,0.8);  }
.admin-panel table[border="1"] tr:not(:first-child):hover td {
  background: rgba(0,238,255,0.04);
}

/* Nested sub-tables (ship stats, device lists) */
.admin-panel td > table {
  background: rgba(0,238,255,0.018);
  border: 1px solid rgba(0,238,255,0.07);
  border-radius: 3px;
}
.admin-panel td > table td { font-size: 12px; }
.admin-panel td > table td:first-child:not([colspan]):not([align]) { font-size: 9.5px; }

/* ── Inline status alerts ──────────────────────────── */
.admin-alert {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 11px 16px;
  border-radius: 3px;
  font-size: 13px;
  font-family: var(--font-hud, monospace);
  font-weight: 600;
  letter-spacing: 0.06em;
  margin-bottom: 18px;
  border-left: 3px solid;
}
.admin-alert--ok   { border-color: #00ff88; background: rgba(0,255,136,0.07); color: #00ff88; }
.admin-alert--warn { border-color: #f59e0b; background: rgba(245,158,11,0.07); color: #fbbf24; }
.admin-alert--err  { border-color: #ff3355; background: rgba(255,51,85,0.07);  color: #ff6680; }

.addon-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
  gap: 12px;
  margin: 0 0 20px;
}

.addon-summary-card {
  border: 1px solid rgba(0, 238, 255, 0.12);
  background: rgba(4, 14, 26, 0.78);
  padding: 12px 14px;
  border-radius: 4px;
}

.addon-summary-card__label {
  display: block;
  font-size: 10px;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(122, 176, 204, 0.9);
  margin-bottom: 4px;
}

.addon-summary-card__value {
  display: block;
  font-family: var(--font-hud, monospace);
  font-size: 20px;
  color: #eefbff;
}

.addon-manager {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
  gap: 16px;
}

.addon-card {
  border: 1px solid rgba(0, 238, 255, 0.14);
  background: linear-gradient(180deg, rgba(5, 16, 29, 0.96), rgba(3, 11, 21, 0.98));
  border-radius: 4px;
  padding: 18px;
  box-shadow: inset 0 1px 0 rgba(255,255,255,0.03);
}

.addon-card__top {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 10px;
}

.addon-card__title {
  margin: 0;
  color: #e9fbff;
  font-size: 20px;
}

.addon-card__meta {
  margin: 6px 0 0;
  font-size: 12px;
  color: rgba(174, 207, 228, 0.75);
}

.addon-card__description {
  margin: 0 0 12px;
  color: rgba(220, 238, 248, 0.9);
  line-height: 1.55;
  min-height: 44px;
}

.addon-pill {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  min-width: 86px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 10px;
  font-weight: 800;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  border: 1px solid transparent;
}

.addon-pill--enabled {
  color: #00170d;
  background: #00ff88;
  border-color: rgba(0, 255, 136, 0.35);
}

.addon-pill--disabled {
  color: #fbbf24;
  background: rgba(245, 158, 11, 0.08);
  border-color: rgba(245, 158, 11, 0.24);
}

.addon-pill--invalid {
  color: #ff8fa1;
  background: rgba(255, 51, 85, 0.08);
  border-color: rgba(255, 51, 85, 0.24);
}

.addon-card__paths {
  margin: 0 0 12px;
  padding: 0;
  list-style: none;
  font-size: 12px;
  color: rgba(170, 200, 225, 0.72);
}

.addon-card__paths li {
  margin-bottom: 6px;
}

.addon-card__paths code {
  color: #dff8ff;
}

.addon-card__warnings {
  margin: 0 0 14px;
  padding: 0;
  list-style: none;
}

.addon-card__warnings li {
  border-left: 3px solid #ff3355;
  background: rgba(255, 51, 85, 0.08);
  color: #ff95a8;
  padding: 8px 10px;
  margin-bottom: 8px;
}

.addon-card__actions {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.addon-card__button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(0, 238, 255, 0.18);
  background: rgba(7, 26, 42, 0.96);
  color: #e8fbff;
  padding: 9px 13px;
  text-decoration: none;
  font-family: var(--font-hud, monospace);
  font-size: 11px;
  letter-spacing: 0.11em;
  text-transform: uppercase;
  cursor: pointer;
}

.addon-card__button:hover {
  background: rgba(11, 40, 64, 0.98);
}

.addon-card__button--danger {
  border-color: rgba(255, 51, 85, 0.2);
  color: #ff9dad;
}

.addon-card__button--success {
  border-color: rgba(0, 255, 136, 0.2);
  color: #8ff8c2;
}
</style>
HTML;
}

function bnt_admin_render_toolbar(?string $module = null): void
{
    $modules = bnt_admin_modules();
    $homeActive = ($module === null || $module === '') ? ' admin-tab--active' : '';

    echo "<nav class='admin-nav' aria-label='Admin navigation'>";

    // Brand block
    echo "<a class='admin-nav__brand' href='admin.php'>";
    echo "<span class='admin-nav__logo'>BN</span>";
    echo "<span class='admin-nav__wordmark'>";
    echo "<span class='admin-nav__title'>BlackNova</span>";
    echo "<span class='admin-nav__subtitle'>Control Center</span>";
    echo "</span>";
    echo "</a>";

    // Module tabs
    echo "<div class='admin-nav__links'>";
    echo "<a class='admin-tab{$homeActive}' href='admin.php'>Dashboard</a>";
    foreach ($modules as $key => $definition)
    {
        $active = ($module === $key) ? ' admin-tab--active' : '';
        echo "<a class='admin-tab{$active}' href='admin.php?menu={$key}'>" . htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8') . "</a>";
    }
    echo "</div>";

    // System / utility tabs on the right
    echo "<div class='admin-nav__system'>";
    echo "<a class='admin-tab admin-tab--sys' href='setup_info.php'>Setup Info</a>";
    echo "<a class='admin-tab admin-tab--sys' href='scheduler.php'>Scheduler</a>";
    echo "<a class='admin-tab admin-tab--sys' href='perfmon.php'>Perf Mon</a>";
    echo "<a class='admin-tab admin-tab--back' href='main.php'>&#8592; Cockpit</a>";
    echo "</div>";

    echo "</nav>";
}

function bnt_admin_render_home(): void
{
    $modules = bnt_admin_modules();

    // Module icons (unicode, sci-fi friendly)
    $icons = array(
        'addons'   => '⬢',
        'motd'     => '◈',
        'useredit' => '◎',
        'univedit' => '✦',
        'sectedit' => '⬡',
        'planedit' => '◉',
        'linkedit' => '⟡',
        'zoneedit' => '⬟',
        'ipedit'   => '⊗',
        'logview'  => '≡',
    );

    echo "<section class='admin-hero'>";
    echo "<div class='admin-hero__inner'>";
    echo "<div class='admin-eyebrow'>Administrator Access</div>";
    echo "<h2 class='admin-title'>Mission Control</h2>";
    echo "<p class='admin-subtitle'>Central command for BlackNova Traders. Manage live announcements, addon toggles, player accounts, universe topology, moderation, and server diagnostics from one place. Navigation persists across every admin section.</p>";
    echo "</div>";
    echo "</section>";

    echo "<div class='admin-grid'>";
    foreach ($modules as $key => $definition)
    {
        $icon = htmlspecialchars($icons[$key] ?? '◆', ENT_QUOTES, 'UTF-8');
        $label = htmlspecialchars($definition['label'], ENT_QUOTES, 'UTF-8');
        $summary = htmlspecialchars($definition['summary'], ENT_QUOTES, 'UTF-8');
        echo "<section class='admin-card'>";
        echo "<div class='admin-card__icon'>{$icon}</div>";
        echo "<h3>{$label}</h3>";
        echo "<p>{$summary}</p>";
        echo "<a class='admin-card__link' href='admin.php?menu={$key}'>Open Module</a>";
        echo "</section>";
    }

    echo "<section class='admin-card'>";
    echo "<div class='admin-card__icon'>⚙</div>";
    echo "<h3>System Tools</h3>";
    echo "<p>Server diagnostics, setup details, universe creation, and manual scheduler execution.</p>";
    echo "<a class='admin-card__link' href='setup_info.php' style='display:flex;margin-bottom:12px;'>Setup Info</a>";
    echo "<a class='admin-card__link' href='scheduler.php' style='display:flex;margin-bottom:12px;'>Run Scheduler</a>";
    echo "<a class='admin-card__link' href='perfmon.php' style='display:flex;margin-bottom:12px;'>Perf Monitor</a>";
    echo "<a class='admin-card__link' href='create_universe.php' style='display:flex;'>Universe Creation</a>";
    echo "</section>";
    echo "</div>";
}

function bnt_admin_render_addons_manager(): void
{
    $addons = bnt_discover_addons(true);
    $totalAddons = count($addons);
    $enabledAddons = 0;
    $invalidAddons = 0;

    foreach ($addons as $addon) {
        if (!empty($addon['enabled'])) {
            $enabledAddons++;
        }
        if (empty($addon['is_valid'])) {
            $invalidAddons++;
        }
    }

    echo "<p style='color:rgba(170,200,225,0.72);font-size:13px;margin:0 0 18px;'>Drop each addon into <code>" . htmlspecialchars(bnt_addons_root(), ENT_QUOTES, 'UTF-8') . "</code> inside its own folder. Each folder should contain an <code>addon.json</code> manifest and a <code>bootstrap.php</code> file. Then enable or disable it here.</p>";

    echo "<div class='addon-summary-grid'>";
    echo "<div class='addon-summary-card'><span class='addon-summary-card__label'>Discovered</span><span class='addon-summary-card__value'>{$totalAddons}</span></div>";
    echo "<div class='addon-summary-card'><span class='addon-summary-card__label'>Enabled</span><span class='addon-summary-card__value'>{$enabledAddons}</span></div>";
    echo "<div class='addon-summary-card'><span class='addon-summary-card__label'>Needs Attention</span><span class='addon-summary-card__value'>{$invalidAddons}</span></div>";
    echo "</div>";

    if ($totalAddons === 0) {
        echo "<div class='admin-alert admin-alert--warn'>No addons were found. Add a folder under <code>" . htmlspecialchars(bnt_addons_root(), ENT_QUOTES, 'UTF-8') . "</code> to get started.</div>";
        return;
    }

    echo "<div class='addon-manager'>";
    foreach ($addons as $addon) {
        $slug = htmlspecialchars($addon['slug'], ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($addon['name'], ENT_QUOTES, 'UTF-8');
        $version = htmlspecialchars($addon['version'], ENT_QUOTES, 'UTF-8');
        $author = htmlspecialchars($addon['author'], ENT_QUOTES, 'UTF-8');
        $description = htmlspecialchars($addon['description'], ENT_QUOTES, 'UTF-8');
        $directory = htmlspecialchars($addon['directory'], ENT_QUOTES, 'UTF-8');
        $bootstrapPath = htmlspecialchars((string) ($addon['bootstrap_path'] ?? ''), ENT_QUOTES, 'UTF-8');

        $pillClass = 'addon-pill addon-pill--disabled';
        $pillLabel = 'Disabled';
        if (empty($addon['is_valid'])) {
            $pillClass = 'addon-pill addon-pill--invalid';
            $pillLabel = 'Invalid';
        } elseif (!empty($addon['enabled'])) {
            $pillClass = 'addon-pill addon-pill--enabled';
            $pillLabel = 'Enabled';
        }

        echo "<section class='addon-card'>";
        echo "<div class='addon-card__top'>";
        echo "<div>";
        echo "<h3 class='addon-card__title'>{$name}</h3>";
        echo "<p class='addon-card__meta'>Slug <code>{$slug}</code> · v{$version} · {$author}</p>";
        echo "</div>";
        echo "<span class='{$pillClass}'>{$pillLabel}</span>";
        echo "</div>";

        if ($description !== '') {
            echo "<p class='addon-card__description'>{$description}</p>";
        } else {
            echo "<p class='addon-card__description'>No description provided.</p>";
        }

        echo "<ul class='addon-card__paths'>";
        echo "<li><strong>Folder</strong> <code>{$directory}</code></li>";
        echo "<li><strong>Bootstrap</strong> <code>{$bootstrapPath}</code></li>";
        echo "</ul>";

        if (!empty($addon['warnings'])) {
            echo "<ul class='addon-card__warnings'>";
            foreach ($addon['warnings'] as $warning) {
                echo "<li>" . htmlspecialchars((string) $warning, ENT_QUOTES, 'UTF-8') . "</li>";
            }
            echo "</ul>";
        }

        echo "<div class='addon-card__actions'>";
        if (!empty($addon['enabled'])) {
            $playerPage = bnt_get_addon_page($addon['slug'], 'index');
            $adminPage = bnt_get_addon_page($addon['slug'], 'admin');
            if ($playerPage !== null) {
                echo "<a class='addon-card__button' href='" . htmlspecialchars(bnt_addon_url($addon['slug'], 'index'), ENT_QUOTES, 'UTF-8') . "'>Open</a>";
            }
            if ($adminPage !== null) {
                echo "<a class='addon-card__button' href='" . htmlspecialchars(bnt_addon_url($addon['slug'], 'admin'), ENT_QUOTES, 'UTF-8') . "'>Manage</a>";
            }
        }
        if (!empty($addon['is_valid'])) {
            echo "<form action='admin.php' method='post' style='margin:0;'>";
            echo bnt_csrf_input();
            echo "<input type='hidden' name='menu' value='addons'>";
            echo "<input type='hidden' name='addon_slug' value='{$slug}'>";
            if (!empty($addon['enabled'])) {
                echo "<button class='addon-card__button addon-card__button--danger' type='submit' name='addon_action' value='disable'>Disable</button>";
            } else {
                echo "<button class='addon-card__button addon-card__button--success' type='submit' name='addon_action' value='enable'>Enable</button>";
            }
            echo "</form>";
        }
        echo "</div>";
        echo "</section>";
    }
    echo "</div>";
}

function bnt_admin_render_section_start(string $module): void
{
    $label = htmlspecialchars(bnt_admin_label($module), ENT_QUOTES, 'UTF-8');
    echo "<section class='admin-panel'>";
    echo "<div class='admin-eyebrow'>Admin Section</div>";
    echo "<h2 class='admin-section-title'>{$label}</h2>";
}

function bnt_admin_render_section_end(?string $module = null): void
{
    echo "<div class='admin-actions'>";
    echo "<a class='admin-btn' href='admin.php'>&#8592; Dashboard</a>";
    if (!empty($module))
    {
        echo "<a class='admin-btn' href='admin.php?menu=" . rawurlencode($module) . "'>Reset Section</a>";
    }
    echo "<a class='admin-btn' href='main.php'>Back to Cockpit</a>";
    echo "</div>";
    echo "</section>";
}

bnt_admin_render_styles();

if (isset($_REQUEST['menu']))
{
    $module = $_REQUEST['menu'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    bnt_require_csrf();
}

{
    if (empty($module))
    {
        echo "<div class='admin-shell'>";
        bnt_admin_render_toolbar();
        bnt_admin_render_home();
        echo "</div>";
    }
    else
    {
        $button_main = true;
        echo "<div class='admin-shell'>";
        bnt_admin_render_toolbar($module);
        bnt_admin_render_section_start($module);

        if ($module == "addons")
        {
            $addonAction = trim((string) ($_POST['addon_action'] ?? ''));
            $addonSlug = trim((string) ($_POST['addon_slug'] ?? ''));

            if ($addonAction !== '' && $addonSlug !== '')
            {
                $availableAddons = bnt_discover_addons(true);
                if (!isset($availableAddons[$addonSlug])) {
                    echo "<div class='admin-alert admin-alert--err'>That addon could not be found.</div>";
                } elseif ($addonAction === 'enable') {
                    if (!empty($availableAddons[$addonSlug]['is_valid']) && bnt_set_addon_enabled($addonSlug, true)) {
                        echo "<div class='admin-alert admin-alert--ok'>Addon enabled. It will load automatically on the next request.</div>";
                    } else {
                        echo "<div class='admin-alert admin-alert--err'>Unable to enable that addon. Check the manifest and bootstrap file.</div>";
                    }
                } elseif ($addonAction === 'disable') {
                    if (bnt_set_addon_enabled($addonSlug, false)) {
                        echo "<div class='admin-alert admin-alert--warn'>Addon disabled.</div>";
                    } else {
                        echo "<div class='admin-alert admin-alert--err'>Unable to disable that addon.</div>";
                    }
                }
            }

            bnt_admin_render_addons_manager();
        }
        elseif ($module == "motd")
        {
            $motd = bnt_get_motd();
            $motdAction = $_POST['motd_action'] ?? '';

            if ($motdAction === 'save')
            {
                $motdHeadline = trim((string) ($_POST['motd_headline'] ?? ''));
                $motdBody = trim((string) ($_POST['motd_body'] ?? ''));
                $motdActive = (string) ($_POST['motd_active'] ?? 'Y') === 'Y';
                $currentAdmin = bnt_get_current_playerinfo();
                $updatedBy = isset($currentAdmin['ship_id']) ? (int) $currentAdmin['ship_id'] : null;

                bnt_save_motd($motdHeadline, $motdBody, $motdActive, $updatedBy);
                $motd = bnt_get_motd();
                echo "<div class='admin-alert admin-alert--ok'>Announcement updated successfully.</div>";
            }
            elseif ($motdAction === 'clear')
            {
                $currentAdmin = bnt_get_current_playerinfo();
                $updatedBy = isset($currentAdmin['ship_id']) ? (int) $currentAdmin['ship_id'] : null;

                bnt_save_motd('', '', false, $updatedBy);
                $motd = bnt_get_motd();
                echo "<div class='admin-alert admin-alert--warn'>Announcement cleared.</div>";
            }

            echo "<form action='admin.php' method='post'>";
            echo bnt_csrf_input();
            echo "<input type='hidden' name='menu' value='motd'>";
            echo "<table border='0' cellspacing='0' cellpadding='5'>";
            echo "<tr><td>Headline</td><td><input type='text' name='motd_headline' maxlength='150' style='width:480px;' value=\"" . htmlspecialchars((string) ($motd['headline'] ?? ''), ENT_QUOTES, 'UTF-8') . "\"></td></tr>";
            echo "<tr><td style='vertical-align:top;'>Message</td><td><textarea name='motd_body' rows='10' cols='80'>" . htmlspecialchars((string) ($motd['body'] ?? ''), ENT_QUOTES, 'UTF-8') . "</textarea></td></tr>";
            echo "<tr><td>Status</td><td>";
            echo "<label><input type='radio' name='motd_active' value='Y' " . ((($motd['is_active'] ?? 'N') === 'Y') ? "checked" : "") . "> Live now</label>&nbsp;&nbsp;";
            echo "<label><input type='radio' name='motd_active' value='N' " . ((($motd['is_active'] ?? 'N') !== 'Y') ? "checked" : "") . "> Hidden</label>";
            echo "</td></tr>";
            if (!empty($motd['updated_at']))
            {
                echo "<tr><td>Last updated</td><td>" . htmlspecialchars((string) $motd['updated_at'], ENT_QUOTES, 'UTF-8') . "</td></tr>";
            }
            echo "</table><br>";
            echo "<button type='submit' name='motd_action' value='save'>Save Announcement</button>&nbsp;";
            echo "<button type='submit' name='motd_action' value='clear'>Clear Announcement</button>";
            echo "</form>";
        }
        elseif ($module == "useredit")
        {
            echo "<p style='color:rgba(170,200,225,0.6);font-size:13px;margin:0 0 18px;'>Select a player from the list to view and edit their account, ship stats, cargo, and devices.</p>";
            echo "<form action='admin.php' method='post'>";
            if (empty($user))
            {
                echo "<select size='20' name='user'>";
                $res = $db->Execute("SELECT ship_id,character_name FROM {$db->prefix}ships ORDER BY character_name");
                db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                while (!$res->EOF)
                {
                    $row=$res->fields;
                    echo "<option value='$row[ship_id]'>$row[character_name]</option>";
                    $res->MoveNext();
                }
                echo "</select>";
                echo "&nbsp;<input type='submit' value='Edit'>";
            }
            else
            {
                if (empty($operation))
                {
                    $res = $db->Execute("SELECT * FROM {$db->prefix}ships WHERE ship_id=?", array($user));
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    $row = $res->fields;
                    echo "<table border='0' cellspacing='0' cellpadding='5'>";
                    $force_reset_checked = (($row['force_password_reset'] ?? 'N') === 'Y') ? 'checked' : '';
                    echo "<tr><td>Player name</td><td><input type='text' name='character_name' value=\"$row[character_name]\"></td></tr>";
                    echo "<tr><td>Require password reset</td><td><label><input type='checkbox' name='force_password_reset' value='ON' {$force_reset_checked}> Force player to set a new password on next login</label></td></tr>";
                    echo "<tr><td>E-mail</td><td><input type='email' name='email' value=\"$row[email]\"></td></tr>";
                    echo "<tr><td>ID</td><td>$user</td></tr>";
                    echo "<tr><td>Ship</td><td><input type='text' name='ship_name' size='30' maxlength='25' value=\"$row[ship_name]\"><br><span style='font-size:11px; color:#b8d2e7;'>Maximum 25 characters.</span></td></tr>";
                    echo "<tr><td>Destroyed?</td><td><input type='checkbox' name='ship_destroyed' value='ON' " . checked($row['ship_destroyed']) . "></td></tr>";
                    echo "<tr><td>Levels</td>";
                    echo "<td><table border='0' cellspacing='0' cellpadding='5'>";
                    echo "<tr><td>Hull</td><td><input type='text' size='5' name='hull' value=\"$row[hull]\"></td>";
                    echo "<td>Engines</td><td><input type='text' size='5' name='engines' value=\"$row[engines]\"></td>";
                    echo "<td>Power</td><td><input type='text' size='5' name='power' value=\"$row[power]\"></td>";
                    echo "<td>Computer</td><td><input type='text' size='5' name='computer' value=\"$row[computer]\"></td></tr>";
                    echo "<tr><td>Sensors</td><td><input type='text' size='5' name='sensors' value=\"$row[sensors]\"></td>";
                    echo "<td>Armor</td><td><input type='text' size='5' name='armor' value=\"$row[armor]\"></td>";
                    echo "<td>Shields</td><td><input type='text' size='5' name='shields' value=\"$row[shields]\"></td>";
                    echo "<td>Beams</td><td><input type='text' size='5' name='beams' value=\"$row[beams]\"></td></tr>";
                    echo "<tr><td>Torpedoes</td><td><input type='text' size='5' name='torp_launchers' value=\"$row[torp_launchers]\"></td>";
                    echo "<td>Cloak</td><td><input type='text' size='5' name='cloak' value=\"$row[cloak]\"></td></tr>";
                    echo "</table></td></tr>";
                    echo "<tr><td>Holds</td>";
                    echo "<td><table border='0' cellspacing='0' cellpadding='5'>";
                    echo "<tr><td>Ore</td><td><input type='text' size='8' name='ship_ore' value=\"$row[ship_ore]\"></td>";
                    echo "<td>Organics</td><td><input type='text' size='8' name='ship_organics' value=\"$row[ship_organics]\"></td>";
                    echo "<td>Goods</td><td><input type='text' size='8' name='ship_goods' value=\"$row[ship_goods]\"></td></tr>";
                    echo "<tr><td>Energy</td><td><input type='text' size='8' name='ship_energy' value=\"$row[ship_energy]\"></td>";
                    echo "<td>Colonists</td><td><input type='text' size='8' name='ship_colonists' value=\"$row[ship_colonists]\"></td></tr>";
                    echo "</table></td></tr>";
                    echo "<tr><td>Combat</td>";
                    echo "<td><table border='0' cellspacing='0' cellpadding='5'>";
                    echo "<tr><td>Fighters</td><td><input type='text' size='8' name='ship_fighters' value=\"$row[ship_fighters]\"></td>";
                    echo "<td>Torpedoes</td><td><input type='text' size='8' name='torps' value=\"$row[torps]\"></td></tr>";
                    echo "<tr><td>Armor Pts</td><td><input type='text' size='8' name='armor_pts' value=\"$row[armor_pts]\"></td></tr>";
                    echo "</table></td></tr>";
                    echo "<tr><td>Devices</td>";
                    echo "<td><table border='0' cellspacing='0' cellpadding='5'>";
                    echo "<tr><td>Beacons</td><td><input type='text' size='5' name='dev_beacon' value=\"$row[dev_beacon]\"></td>";
                    echo "<td>Warp Editors</td><td><input type='text' size='5' name='dev_warpedit' value=\"$row[dev_warpedit]\"></td>";
                    echo "<td>Genesis Torpedoes</td><td><input type='text' size='5' name='dev_genesis' value=\"$row[dev_genesis]\"></td></tr>";
                    echo "<tr><td>Mine Deflectors</td><td><input type='text' size='5' name='dev_minedeflector' value=\"$row[dev_minedeflector]\"></td>";
                    echo "<td>Emergency Warp</td><td><input type='text' size='5' name='dev_emerwarp' value=\"$row[dev_emerwarp]\"></td></tr>";
                    echo "<tr><td>Escape Pod</td><td><input type='checkbox' name='dev_escapepod' value='ON' " . checked($row['dev_escapepod']) . "></td>";
                    echo "<td>FuelScoop</td><td><input type='checkbox' name='dev_fuelscoop' value='ON' " . checked($row['dev_fuelscoop']) . "></td></tr>";
                    echo "</table></td></tr>";
                    echo "<tr><td>Credits</td><td><input type='text' name='credits' value=\"$row[credits]\"></td></tr>";
                    echo "<tr><td>Turns</td><td><input type='text' name='turns' value=\"$row[turns]\"></td></tr>";
                    echo "<tr><td>Current sector</td><td><input type='text' name='sector' value=\"$row[sector]\"></td></tr>";
                    echo "</table>";
                    echo "<br>";
                    echo "<input type='hidden' name='user' value='$user'>";
                    echo "<input type='hidden' name='operation' value='save'>";
                    echo "<input type='submit' value='Save'>";
                }
                elseif ($operation == "save")
                {
                    // update database
                    $_ship_destroyed = empty($ship_destroyed) ? "N" : "Y";
                    $_dev_escapepod = empty($dev_escapepod) ? "N" : "Y";
                    $_dev_fuelscoop = empty($dev_fuelscoop) ? "N" : "Y";
                    $_force_password_reset = empty($_POST['force_password_reset']) ? "N" : "Y";
                    $resx = $db->Execute("UPDATE {$db->prefix}ships SET character_name=?, force_password_reset=?, email=?, ship_name=?, ship_destroyed=?, hull=?, engines=?, power=?, computer=?, sensors=?, armor=?, shields=?, beams=?, torp_launchers=?, cloak=?, credits=?, turns=?, dev_warpedit=?, dev_genesis=?, dev_beacon=?, dev_emerwarp=?, dev_escapepod=?, dev_fuelscoop=?, dev_minedeflector=?, sector=?, ship_ore=?, ship_organics=?, ship_goods=?, ship_energy=?, ship_colonists=?, ship_fighters=?, torps=?, armor_pts=? WHERE ship_id=?;", array($character_name, $_force_password_reset, $email, $ship_name, $_ship_destroyed, $hull, $engines, $power, $computer, $sensors, $armor, $shields, $beams, $torp_launchers, $cloak, $credits, $turns, $dev_warpedit, $dev_genesis, $dev_beacon, $dev_emerwarp, $_dev_escapepod, $_dev_fuelscoop, $dev_minedeflector, $sector, $ship_ore, $ship_organics, $ship_goods, $ship_energy, $ship_colonists, $ship_fighters, $torps, $armor_pts, $user));

                    db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    echo "<div class='admin-alert admin-alert--ok'>Player record saved.</div>";
                    echo "<input type='submit' value=\"Return to User Editor\">";
                    $button_main = false;
                }
                else
                {
                    echo "Invalid operation";
                }
            }
            echo "<input type='hidden' name='menu' value='useredit'>";
            echo "<input type='hidden' name='swordfish' value='$swordfish'>";
            echo "</form>";
        }
        elseif ($module == "univedit")
        {
            $title = $l->get('l_change_uni_title');
            echo "<p style='color:rgba(170,200,225,0.6);font-size:13px;margin:0 0 18px;'>Recalculate sector distances for a resized universe. Enter the new radius and confirm.</p>";

            if (empty($action))
            {
                echo "<form action='admin.php' method='post'>";
                echo "Universe Size: <input type='text' name='radius' value=\"$universe_size\">";
                echo "<input type='hidden' name='swordfish' value='$swordfish'>";
                echo "<input type='hidden' name='menu' value='univedit'>";
                echo "<input type='hidden' name='action' value='doexpand'> ";
                echo "<input type='submit' value=\"Play God\">";
                echo "</form>";
            }
            elseif ($action == "doexpand")
            {
                echo "<div class='admin-alert admin-alert--warn'>Be sure to update your config.php file with the new universe_size value.</div>";
                $result = $db->Execute("SELECT sector_id FROM {$db->prefix}universe ORDER BY sector_id ASC;");
                db_op_result ($db, $result, __LINE__, __FILE__, $db_logging);
                while (!$result->EOF)
                {
                    $row=$result->fields;
                    $distance=mt_rand(1,$radius);
                    $resx = $db->Execute("UPDATE {$db->prefix}universe SET distance=$distance WHERE sector_id=?;", array($row['sector_id']));
                    db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    echo "Updated sector $row[sector_id] set to $distance<br>";
                    $result->MoveNext();
                }
            }
        }
        elseif ($module == "sectedit")
        {
            echo "<form action='admin.php' method='post'>";
            if (empty($sector))
            {
                echo "<p style='color:rgba(170,200,225,0.6);font-size:13px;margin:0 0 14px;'>Select a sector to edit its name, zone, beacon, port configuration, and geometry. Sector 0 is reserved.</p>";
                echo "<select size='20' name='sector'>";
                $res = $db->Execute("SELECT sector_id FROM {$db->prefix}universe ORDER BY sector_id;");
                db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                while (!$res->EOF)
                {
                    $row=$res->fields;
                    echo "<option value='$row[sector_id]'> $row[sector_id] </option>";
                    $res->MoveNext();
                }
                echo "</select>";
                echo "&nbsp;<input type='submit' value='Edit'>";
            }
            else
            {
                if (empty($operation))
                {
                    $res = $db->Execute("SELECT * FROM {$db->prefix}universe WHERE sector_id=?;", array($sector));
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    $row = $res->fields;

                    echo "<table border='0' cellspacing='2' cellpadding='2'>";
                    echo "<tr><td><tt>          Sector ID  </tt></td><td><font color='#6f0'>$sector</font></td>";
                    echo "<td align='right'><tt>  Sector Name</tt></td><td><input type='text' size='15' name='sector_name' value=\"$row[sector_name]\"></td>";
                    echo "<td align='right'><tt>  Zone ID    </tt></td><td>";
                    echo "<select size='1' name='zone_id'>";
                    $ressubb = $db->Execute("SELECT zone_id,zone_name FROM {$db->prefix}zones ORDER BY zone_name;");
                    db_op_result ($db, $ressubb, __LINE__, __FILE__, $db_logging);
                    while (!$ressubb->EOF)
                    {
                        $rowsubb=$ressubb->fields;
                        if ($rowsubb['zone_id'] == $row['zone_id'])
                        {
                            echo "<option selected='$rowsubb[zone_id]' value='$rowsubb[zone_id]'>$rowsubb[zone_name]</option>";
                        }
                        else
                        {
                            echo "<option value='$rowsubb[zone_id]'>$rowsubb[zone_name]</option>";
                        }
                        $ressubb->MoveNext();
                    }

                    echo "</select></td></tr>";
                    echo "<tr><td><tt>          Beacon     </tt></td><td colspan='5'><input type='text' size='70' name='beacon' value=\"$row[beacon]\"></td></tr>";
                    echo "<tr><td><tt>          Distance   </tt></td><td><input type='text' size='9' name='distance' value=\"$row[distance]\"></td>";
                    echo "<td align='right'><tt>  Angle1     </tt></td><td><input type='text' size='9' name='angle1' value=\"$row[angle1]\"></td>";
                    echo "<td align='right'><tt>  Angle2     </tt></td><td><input type='text' size='9' name='angle2' value=\"$row[angle2]\"></td></tr>";
                    echo "<tr><td colspan='6'>    <HR>       </td></tr>";
                    echo "</table>";

                    echo "<table border='0' cellspacing='2' cellpadding='2'>";
                    echo "<tr><td><tt>          Port Type  </tt></td><td>";
                    echo "<select size='1' name='port_type'>";
                    $oportnon = $oportspe = $oportorg = $oportore = $oportgoo = $oportene = "value";
                    if ($row['port_type'] == "none") $oportnon = "selected='none' value";
                    if ($row['port_type'] == "special") $oportspe = "selected='special' value";
                    if ($row['port_type'] == "organics") $oportorg = "selected='organics' value";
                    if ($row['port_type'] == "ore") $oportore = "selected='ore' value";
                    if ($row['port_type'] == "goods") $oportgoo = "selected='goods' value";
                    if ($row['port_type'] == "energy") $oportene = "selected='energy' value";
                    echo "<option $oportnon='none'>none</option>";
                    echo "<option $oportspe='special'>special</option>";
                    echo "<option $oportorg='organics'>organics</option>";
                    echo "<option $oportore='ore'>ore</option>";
                    echo "<option $oportgoo='goods'>goods</option>";
                    echo "<option $oportene='energy'>energy</option>";
                    echo "</select></td>";
                    echo "<td align='right'><tt>  Organics   </tt></td><td><input type='text' size='9' name='port_organics' value=\"$row[port_organics]\"></td>";
                    echo "<td align='right'><tt>  Ore        </tt></td><td><input type='text' size='9' name='port_ore' value=\"$row[port_ore]\"></td>";
                    echo "<td align='right'><tt>  Goods      </tt></td><td><input type='text' size='9' name='port_goods' value=\"$row[port_goods]\"></td>";
                    echo "<td align='right'><tt>  Energy     </tt></td><td><input type='text' size='9' name='port_energy' value=\"$row[port_energy]\"></td></tr>";
                    echo "<tr><td colspan='10'>   <HR>       </td></tr>";
                    echo "</table>";

                    echo "<br>";
                    echo "<input type='hidden' name='sector' value='$sector'>";
                    echo "<input type='hidden' name='operation' value='save'>";
                    echo "<input type='submit' size='1' value='save'>";
                }
                elseif ($operation == "save")
                {
                    // Update database
                    $secupdate = $db->Execute("UPDATE {$db->prefix}universe SET sector_name=?, zone_id=?, beacon=?, port_type=?, port_organics=?, port_ore=?, port_goods=?, port_energy=?, distance=?, angle1=?, angle2=? WHERE sector_id=?;", array($sector_name, $zone_id, $beacon, $port_type, $port_organics, $port_ore, $port_goods, $port_energy, $distance, $angle1, $angle2, $sector));

                    db_op_result ($db, $secupdate, __LINE__, __FILE__, $db_logging);
                    if (!$secupdate)
                    {
                        echo "<div class='admin-alert admin-alert--err'>Sector save failed: " . htmlspecialchars($db->ErrorMsg(), ENT_QUOTES, 'UTF-8') . "</div>";
                    }
                    else
                    {
                        echo "<div class='admin-alert admin-alert--ok'>Sector record saved.</div>";
                    }

                    echo "<input type='submit' value=\"Return to Sector Editor\">";
                    $button_main = false;
                }
                else
                {
                    echo "Invalid operation";
                }
            }
            echo "<input type='hidden' name='menu' value='sectedit'>";
            echo "<input type='hidden' name='swordfish' value='$swordfish'>";
            echo "</form>";
        }
        elseif ($module == "planedit")
        {
            echo "<form action='admin.php' method='post'>";
            if (empty($planet))
            {
                echo "<select size='15' name='planet'>";
                $res = $db->Execute("SELECT planet_id, name, sector_id FROM {$db->prefix}planets ORDER BY sector_id;");
                db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                while (!$res->EOF)
                {
                    $row=$res->fields;
                    if ($row['name'] == "")
                    {
                        $row['name'] = "Unnamed";
                    }

                    echo "<option value='$row[planet_id]'> $row[name] in sector $row[sector_id] </option>";
                    $res->MoveNext();
                }

                echo "</select>";
                echo "&nbsp;<input type='submit' value='Edit'>";
            }
            else
            {
                if (empty($operation))
                {
                    $res = $db->Execute("SELECT * FROM {$db->prefix}planets WHERE planet_id=?;", array($planet));
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    $row = $res->fields;

                    echo "<table border='0' cellspacing='2' cellpadding='2'>";
                    echo "<tr><td><tt>          Planet ID  </tt></td><td><font color='#6f0'>$planet</font></td>";
                    echo "<td align='right'><tt>  Sector ID  </tt><input type='text' size='5' name='sector_id' value=\"$row[sector_id]\"></td>";
                    echo "<td align='right'><tt>  Defeated   </tt><input type='checkbox' name='defeated' value='ON' " . checked($row['defeated']) . "></td></tr>";
                    echo "<tr><td><tt>          Planet Name</tt></td><td><input type='text' size='15' name='name' value=\"" . $row['name'] . "\"></td>";
                    echo "<td align='right'><tt>  Base       </tt><input type='checkbox' name='base' value='ON' " . checked($row['base']) . "></td>";
                    echo "<td align='right'><tt>  Sells      </tt><input type='checkbox' name='sells' value='ON' " . checked($row['sells']) . "></td></tr>";
                    echo "<tr><td colspan='4'>    <HR>       </td></tr>";
                    echo "</table>";

                    echo "<table border='0' cellspacing='2' cellpadding='2'>";
                    echo "<tr><td><tt>          Planet Owner</tt></td><td>";
                    echo "<select size='1' name='owner'>";
                    $ressuba = $db->Execute("SELECT ship_id,character_name FROM {$db->prefix}ships ORDER BY character_name;");
                    db_op_result ($db, $ressuba, __LINE__, __FILE__, $db_logging);
                    echo "<option value='0'>No One</option>";
                    while (!$ressuba->EOF)
                    {
                        $rowsuba=$ressuba->fields;
                        if ($rowsuba['ship_id'] == $row['owner'])
                        {
                            echo "<option selected='$rowsuba[ship_id]' value='$rowsuba[ship_id]'>$rowsuba[character_name]</option>";
                        }
                        else
                        {
                            echo "<option value='$rowsuba[ship_id]'>$rowsuba[character_name]</option>";
                        }

                        $ressuba->MoveNext();
                    }

                    echo "</select></td>";
                    echo "<td align='right'><tt>  Organics   </tt></td><td><input type='text' size='9' name='organics' value=\"$row[organics]\"></td>";
                    echo "<td align='right'><tt>  Ore        </tt></td><td><input type='text' size='9' name='ore' value=\"$row[ore]\"></td>";
                    echo "<td align='right'><tt>  Goods      </tt></td><td><input type='text' size='9' name='goods' value=\"$row[goods]\"></td>";
                    echo "<td align='right'><tt>  Energy     </tt></td><td><input type='text' size='9' name='energy' value=\"$row[energy]\"></td></tr>";
                    echo "<tr><td><tt>          Planet Corp</tt></td><td><input type='text' size=5 name='corp' value=\"$row[corp]\"></td>";
                    echo "<td align='right'><tt>  Colonists  </tt></td><td><input type='text' size='9' name='colonists' value=\"$row[colonists]\"></td>";
                    echo "<td align='right'><tt>  Credits    </tt></td><td><input type='text' size='9' name='credits' value=\"$row[credits]\"></td>";
                    echo "<td align='right'><tt>  Fighters   </tt></td><td><input type='text' size='9' name='fighters' value=\"$row[fighters]\"></td>";
                    echo "<td align='right'><tt>  Torpedoes  </tt></td><td><input type='text' size='9' name='torps' value=\"$row[torps]\"></td></tr>";
                    echo "<tr><td colspan='2'><tt>Planet Production</tt></td>";
                    echo "<td align='right'><tt>  Organics   </tt></td><td><input type='text' size='9' name='prod_organics' value=\"$row[prod_organics]\"></td>";
                    echo "<td align='right'><tt>  Ore        </tt></td><td><input type='text' size='9' name='prod_ore' value=\"$row[prod_ore]\"></td>";
                    echo "<td align='right'><tt>  Goods      </tt></td><td><input type='text' size='9' name='prod_goods' value=\"$row[prod_goods]\"></td>";
                    echo "<td align='right'><tt>  Energy     </tt></td><td><input type='text' size='9' name='prod_energy' value=\"$row[prod_energy]\"></td></tr>";
                    echo "<tr><td colspan='6'><tt>Planet Production</tt></td>";
                    echo "<td align='right'><tt>  Fighters   </tt></td><td><input type='text' size='9' name='prod_fighters' value=\"$row[prod_fighters]\"></td>";
                    echo "<td align='right'><tt>  Torpedoes  </tt></td><td><input type='text' size='9' name='prod_torp' value=\"$row[prod_torp]\"></td></tr>";
                    echo "<tr><td colspan=10>   <HR>       </td></tr>";
                    echo "</table>";

                    echo "<br>";
                    echo "<input type='hidden' name='planet' value='$planet'>";
                    echo "<input type='hidden' name='operation' value='save'>";
                    echo "<input type='submit' size='1' value='save'>";
                }
                elseif ($operation == "save")
                {
                    // Update database
                    $_defeated = empty($defeated) ? "N" : "Y";
                    $_base = empty($base) ? "N" : "Y";
                    $_sells = empty($sells) ? "N" : "Y";
                    $planupdate = $db->Execute("UPDATE {$db->prefix}planets SET sector_id='$sector_id',defeated='$_defeated',name='$name',base='$_base',sells='$_sells',owner='$owner',organics='$organics',ore='$ore',goods='$goods',energy='$energy',corp='$corp',colonists='$colonists',credits='$credits',fighters='$fighters',torps='$torps',prod_organics='$prod_organics',prod_ore='$prod_ore',prod_goods='$prod_goods',prod_energy='$prod_energy',prod_fighters='$prod_fighters',prod_torp='$prod_torp' WHERE planet_id=$planet");
                    db_op_result ($db, $planupdate, __LINE__, __FILE__, $db_logging);
                    if (!$planupdate)
                    {
                        echo "<div class='admin-alert admin-alert--err'>Planet save failed: " . htmlspecialchars($db->ErrorMsg(), ENT_QUOTES, 'UTF-8') . "</div>";
                    }
                    else
                    {
                        echo "<div class='admin-alert admin-alert--ok'>Planet record saved.</div>";
                    }

                    echo "<input type='submit' value=\"Return to Planet Editor\">";
                    $button_main = false;
                }
                else
                {
                    echo "Invalid operation";
                }
            }

            echo "<input type='hidden' name='menu' value='planedit'>";
            echo "<input type='hidden' name='swordfish' value=$swordfish>";
            echo "</form>";
        }
        elseif ($module == "linkedit")
        {
            echo "<p style='color:rgba(170,200,225,0.6);font-size:13px;margin:0;'>Reserved for future warp-link editing improvements.</p>";
        }
        elseif ($module == "zoneedit")
        {
            echo "";
            echo "<form action='admin.php' method='post'>";
            if (empty($zone))
            {
                echo "<select size='20' name='zone'>";
                $res = $db->Execute("SELECT zone_id,zone_name FROM {$db->prefix}zones ORDER BY zone_name;");
                db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                while (!$res->EOF)
                {
                    $row=$res->fields;
                    echo "<option value='$row[zone_id]'>$row[zone_name]</option>";
                    $res->MoveNext();
                }

                echo "</select>";
                echo "<input type='hidden' name='operation' value='editzone'>";
                echo "&nbsp;<input type='submit' value='Edit'>";
            }
            else
            {
                if ($operation == "editzone")
                {
                    $res = $db->Execute("SELECT * FROM {$db->prefix}zones WHERE zone_id=?;", array($zone));
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    $row = $res->fields;
                    echo "<table border=0 cellspacing=0 cellpadding=5>";
                    echo "<tr><td>Zone ID</td><td>$row[zone_id]</td></tr>";
                    echo "<tr><td>Zone Name</td><td><input type='text' name=zone_name value=\"$row[zone_name]\"></td></tr>";
                    echo "<tr><td>Allow Beacon</td><td><input type=checkbox name=zone_beacon value=ON " . checked($row['allow_beacon']) . "></td>";
                    echo "<tr><td>Allow Attack</td><td><input type=checkbox name=zone_attack value=ON " . checked($row['allow_attack']) . "></td>";
                    echo "<tr><td>Allow WarpEdit</td><td><input type=checkbox name=zone_warpedit value=ON " . checked($row['allow_warpedit']) . "></td>";
                    echo "<tr><td>Allow Planet</td><td><input type=checkbox name=zone_planet value=ON " . checked($row['allow_planet']) . "></td>";
                    echo "</table>";
                    echo "<tr><td>Max Hull</td><td><input type='text' name=zone_hull value=\"$row[max_hull]\"></td></tr>";
                    echo "<br>";
                    echo "<input type='hidden' name=zone value=$zone>";
                    echo "<input type='hidden' name=operation value='save'zone>";
                    echo "<input type=submit value='save'>";
                }
                elseif ($operation == "savezone")
                {
                    // Update database
                    $_zone_beacon = empty($zone_beacon) ? "N" : "Y";
                    $_zone_attack = empty($zone_attack) ? "N" : "Y";
                    $_zone_warpedit = empty($zone_warpedit) ? "N" : "Y";
                    $_zone_planet = empty($zone_planet) ? "N" : "Y";
                    $resx = $db->Execute("UPDATE {$db->prefix}zones SET zone_name='$zone_name',allow_beacon='$_zone_beacon' ,allow_attack='$_zone_attack' ,allow_warpedit='$_zone_warpedit' ,allow_planet='$_zone_planet', max_hull='$zone_hull' WHERE zone_id=$zone");
                    db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    echo "<div class='admin-alert admin-alert--ok'>Zone record saved.</div>";
                    echo "<input type=submit value=\"Return to Zone Editor\">";
                    $button_main = false;
                }
                else
                {
                    echo "Invalid operation";
                }
            }

            echo "<input type='hidden' name=menu value=zoneedit>";
            echo "<input type='hidden' name=swordfish value=$swordfish>";
            echo "</form>";
        }
        elseif ($module == "ipedit")
        {
            echo "<p style='color:rgba(170,200,225,0.6);font-size:13px;margin:0 0 18px;'>Review active bans and player IP addresses. Use Show Player IPs to inspect connections before banning.</p>";
            if (empty($command))
            {
                echo "<form action=admin.php method=post>";
                echo "<input type='hidden' name=swordfish value=$swordfish>";
                echo "<input type='hidden' name=command value=showips>";
                echo "<input type='hidden' name=menu value=ipedit>";
                echo "<input type=submit value=\"Show player's ips\">";
                echo "</form>";

                $res = $db->Execute("SELECT ban_mask FROM {$db->prefix}ip_bans;");
                db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                while (!$res->EOF)
                {
                    $bans[] = $res->fields['ban_mask'];
                    $res->MoveNext();
                }

                if (empty($bans))
                {
                    echo "<strong>No IP bans are currently active.</strong>";
                }
                else
                {
                    echo "<table border=1 cellspacing=1 cellpadding=2 width=100% align=center>" .
                         "<tr bgcolor=$color_line2><td align=center colspan=7><strong><font color=white>" .
                         "Active IP Bans" .
                         "</font></strong>" .
                         "</td></tr>" .
                         "<tr align=center bgcolor=$color_line2>" .
                         "<td><font size=2 color=white><strong>Ban Mask</strong></font></td>" .
                         "<td><font size=2 color=white><strong>Affected Players</strong></font></td>" .
                         "<td><font size=2 color=white><strong>E-mail</strong></font></td>" .
                         "<td><font size=2 color=white><strong>Operations</strong></font></td>" .
                         "</tr>";

                         $curcolor=$color_line1;

                         foreach ($bans as $ban)
                         {
                             echo "<tr bgcolor=$curcolor>";
                             if ($curcolor == $color_line1)
                             {
                                 $curcolor = $color_line2;
                             }
                             else
                             {
                                 $curcolor = $color_line1;
                             }

                             $printban = str_replace("%", "*", $ban);
                             echo "<td align=center><font size=2 color=white>$printban</td>" .
                                  "<td align=center><font size=2 color=white>";

                             $res = $db->Execute("SELECT character_name, ship_id, email FROM {$db->prefix}ships WHERE ip_address LIKE ?;", array($ban));
                             db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                             unset($players);
                             while (!$res->EOF)
                             {
                                 $players[] = $res->fields;
                                 $res->MoveNext();
                             }

                             if (empty($players))
                             {
                                 echo "None";
                             }
                             else
                             {
                                 foreach ($players as $player)
                                 {
                                     echo "<strong>$player[character_name]</strong><br>";
                                 }
                             }

                             echo "<td align=center><font size=2 color=white>";

                             if (empty($players))
                             {
                                 echo "N/A";
                             }
                             else
                             {
                                 foreach ($players as $player)
                                 {
                                     echo "$player[email]<br>";
                                 }
                             }

                             echo "<td align=center nowrap valign=center><font size=2 color=white>" .
                                  "<form action=admin.php method=post>" .
                                  "<input type='hidden' name=swordfish value=$swordfish>" .
                                  "<input type='hidden' name=command value=unbanip>" .
                                  "<input type='hidden' name=menu value=ipedit>" .
                                  "<input type='hidden' name=ban value=$ban>" .
                                  "<input type=submit value=Remove>" .
                                 "</form>";
                         }

                     echo "</table><p>";
                     }
                 }
                 elseif ($command== 'showips')
                 {
                     $res = $db->Execute("SELECT DISTINCT ip_address FROM {$db->prefix}ships;");
                     db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                     while (!$res->EOF)
                     {
                         $ips[] = $res->fields['ip_address'];
                         $res->MoveNext();
                     }

                     echo "<table border=1 cellspacing=1 cellpadding=2 width=100% align=center>" .
                          "<tr bgcolor=$color_line2><td align=center colspan=7><strong><font color=white>" .
                          "Players sorted by IP address" .
                          "</font></strong>" .
                          "</td></tr>" .
                          "<tr align=center bgcolor=$color_line2>" .
                          "<td><font size=2 color=white><strong>IP address</strong></font></td>" .
                          "<td><font size=2 color=white><strong>Players</strong></font></td>" .
                          "<td><font size=2 color=white><strong>E-mail</strong></font></td>" .
                          "<td><font size=2 color=white><strong>Operations</strong></font></td>" .
                          "</tr>";

                     $curcolor=$color_line1;

                     foreach ($ips as $ip)
                     {
                         echo "<tr bgcolor=$curcolor>";
                         if ($curcolor == $color_line1)
                         {
                             $curcolor = $color_line2;
                         }
                         else
                         {
                             $curcolor = $color_line1;
                         }

                         echo "<td align=center><font size=2 color=white>$ip</td>" .
                              "<td align=center><font size=2 color=white>";

                         $res = $db->Execute("SELECT character_name, ship_id, email FROM {$db->prefix}ships WHERE ip_address=?;", array($ip));
                         db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                         unset($players);
                         while (!$res->EOF)
                         {
                             $players[] = $res->fields;
                             $res->MoveNext();
                         }

                         foreach ($players as $player)
                         {
                             echo "<strong>$player[character_name]</strong><br>";
                         }

                         echo "<td align=center><font size=2 color=white>";

                         foreach ($players as $player)
                         {
                             echo "$player[email]<br>";
                         }

                         echo "<td align=center nowrap valign=center><font size=2 color=white>" .
                              "<form action=admin.php method=post>" .
                              "<input type='hidden' name=swordfish value=$swordfish>" .
                              "<input type='hidden' name=command value=banip>" .
                              "<input type='hidden' name=menu value=ipedit>" .
                              "<input type='hidden' name=ip value=$ip>" .
                              "<input type=submit value=Ban>" .
                              "</form>" .
                              "<form action=admin.php method=post>" .
                              "<input type='hidden' name=swordfish value=$swordfish>" .
                              "<input type='hidden' name=command value=unbanip>" .
                              "<input type='hidden' name=menu value=ipedit>" .
                              "<input type='hidden' name=ip value=$ip>" .
                              "<input type=submit value=Unban>" .
                              "</form>";
                    }

                    echo "</table><p>" .
                         "<form action=admin.php method=post>" .
                         "<input type='hidden' name=swordfish value=$swordfish>" .
                         "<input type='hidden' name=menu value=ipedit>" .
                         "<input type=submit value=\"Return to IP bans menu\">" .
                         "</form>";
                }
                elseif ($command == 'banip')
                {
                    $ip = $_POST[ip];
                    echo "<strong>Banning ip : $ip<p>";
                    echo "<font size=2 color=white>Please select ban type :<p>";

                    $ipparts = explode(".", $ip);

                    echo "<table border=0>" .
                         "<tr><td align=right>" .
                         "<form action=admin.php method=post>" .
                         "<input type='hidden' name=swordfish value=$swordfish>" .
                         "<input type='hidden' name=menu value=ipedit>" .
                         "<input type='hidden' name=command value=banip2>" .
                         "<input type='hidden' name=ip value=$ip>" .
                         "<input type=radio name=class value=I checked>" .
                         "<td><font size=2 color=white>IP only : $ip</td>" .
                         "<tr><td>" .
                         "<input type=radio name=class value=A>" .
                         "<td><font size=2 color=white>Class A : $ipparts[0].$ipparts[1].$ipparts[2].*</td>" .
                         "<tr><td>" .
                         "<input type=radio name=class value=B>" .
                         "<td><font size=2 color=white>Class B : $ipparts[0].$ipparts[1].*</td>" .
                         "<tr><td><td><br><input type=submit value=Ban>" .
                         "</table>" .
                         "</form>";

                    echo "<form action=admin.php method=post>" .
                         "<input type='hidden' name=swordfish value=$swordfish>" .
                         "<input type='hidden' name=menu value=ipedit>" .
                         "<input type=submit value=\"Return to IP bans menu\">" .
                         "</form>";
                }
                elseif ($command == 'banip2')
                {
                    $ip = $_POST['ip'];
                    $ipparts = explode(".", $ip);

                    if ($class == 'A')
                    {
                        $banmask = "$ipparts[0].$ipparts[1].$ipparts[2].%";
                    }
                    elseif ($class == 'B')
                    {
                        $banmask = "$ipparts[0].$ipparts[1].%";
                    }
                    else
                    {
                        $banmask = $ip;
                    }

                    $printban = str_replace("%", "*", $banmask);
                    echo "<font size=2 color=white><strong>Successfully banned $printban</strong>.<p>";

                    $resx = $db->Execute("INSERT INTO {$db->prefix}ip_bans values(NULL, ?);", array($banmask));
                    db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    $res = $db->Execute("SELECT DISTINCT character_name FROM {$db->prefix}ships, {$db->prefix}ip_bans WHERE ip_address LIKE ban_mask;");
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    echo "Affected players :<p>";
                    while (!$res->EOF)
                    {
                        echo " - " . $res->fields['character_name'] . "<br>";
                        $res->MoveNext();
                    }

                    echo "<form action=admin.php method=post>" .
                         "<input type='hidden' name=swordfish value=$swordfish>" .
                         "<input type='hidden' name=menu value=ipedit>" .
                         "<input type=submit value=\"Return to IP bans menu\">" .
                         "</form>";
                }
                elseif ($command == 'unbanip')
                {
                    $ip = $_POST['ip'];
                    if (!empty($ban))
                    {
                        $res = $db->Execute("SELECT * FROM {$db->prefix}ip_bans WHERE ban_mask=?;", array($ban));
                        db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    }
                    else
                    {
                        $res = $db->Execute("SELECT * FROM {$db->prefix}ip_bans WHERE ? LIKE ban_mask;", array($ip));
                        db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    }

                    $nbbans = $res->RecordCount();
                    while (!$res->EOF)
                    {
                        $res->fields['print_mask'] = str_replace("%", "*", $res->fields['ban_mask']);
                        $bans[] = $res->fields;
                        $res->MoveNext();
                    }

                    if (!empty($ban))
                    {
                        $resx = $db->Execute("DELETE FROM {$db->prefix}ip_bans WHERE ban_mask=?;", array($ban));
                        db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    }
                    else
                    {
                        $resx = $db->Execute("DELETE FROM {$db->prefix}ip_bans WHERE ? LIKE ban_mask;", array($ip));
                        db_op_result ($db, $resx, __LINE__, __FILE__, $db_logging);
                    }

                    $query_string = "ip_address LIKE '" . $bans[0][ban_mask] ."'";
                    for ($i = 1; $i < $nbbans ; $i++)
                    {
                        $query_string = $query_string . " OR ip_address LIKE '" . $bans[$i][ban_mask] . "'";
                    }

                    $res = $db->Execute("SELECT DISTINCT character_name FROM {$db->prefix}ships WHERE ?;", array($query_string));
                    db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
                    $nbplayers = $res->RecordCount();
                    while (!$res->EOF)
                    {
                        $players[] = $res->fields['character_name'];
                        $res->MoveNext();
                }

                echo "<font size=2 color=white><strong>Successfully removed $nbbans bans</strong> :<p>";

                foreach ($bans as $ban)
                {
                    echo " - $ban[print_mask]<br>";
                }

                echo "<p><strong>Affected players :</strong><p>";
                if (empty($players))
                {
                    echo " - None<br>";
                }
                else
                {
                    foreach ($players as $player)
                    {
                        echo " - $player<br>";
                    }
                }

                echo "<form action=admin.php method=post>" .
                     "<input type='hidden' name=swordfish value=$swordfish>" .
                     "<input type='hidden' name=menu value=ipedit>" .
                     "<input type=submit value=\"Return to IP bans menu\">" .
                     "</form>";
            }
        }
        elseif ($module == "logview")
        {
            echo "<form action=log.php method=post>" .
                 "<input type='hidden' name=swordfish value=$swordfish>" .
                 "<input type='hidden' name=player value=0>" .
                 "<input type=submit value=\"View admin log\">" .
                 "</form>" .
                 "<form action=log.php method=post>" .
                 "<input type='hidden' name=swordfish value=$swordfish>" .
                 "<select name=player>";

            $res = $db->execute("SELECT ship_id, character_name FROM {$db->prefix}ships ORDER BY character_name ASC;");
            db_op_result ($db, $res, __LINE__, __FILE__, $db_logging);
            while (!$res->EOF)
            {
                $players[] = $res->fields;
                $res->MoveNext();
            }

            foreach ($players as $player)
            {
                echo "<option value=$player[ship_id]>$player[character_name]</option>";
            }

            echo "</select>&nbsp;&nbsp;" .
                 "<input type=submit value=\"View player log\">" .
                 "</form><HR size=1 width=80%>";
        }
        else
        {
            echo "Unknown function";
        }

        bnt_admin_render_section_end($button_main ? $module : null);
        echo "</div>";
    }
}

include "footer.php";
?>
