<?php
// File: includes/api.php

function bnt_api_request_method(): string
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
}

function bnt_api_json_input(): array
{
    static $decoded = null;
    if ($decoded !== null) {
        return $decoded;
    }

    $raw = file_get_contents('php://input');
    if (!is_string($raw) || trim($raw) === '') {
        $decoded = array();
        return $decoded;
    }

    $json = json_decode($raw, true);
    $decoded = is_array($json) ? $json : array();
    return $decoded;
}

function bnt_api_input_value(string $key, $default = null)
{
    if (array_key_exists($key, $_GET)) {
        return $_GET[$key];
    }

    if (array_key_exists($key, $_POST)) {
        return $_POST[$key];
    }

    $json = bnt_api_json_input();
    if (array_key_exists($key, $json)) {
        return $json[$key];
    }

    return $default;
}

function bnt_api_int_param(string $key, int $default = 0, int $min = PHP_INT_MIN, int $max = PHP_INT_MAX): int
{
    $value = bnt_api_input_value($key, $default);
    $value = (int) $value;
    if ($value < $min) {
        return $min;
    }
    if ($value > $max) {
        return $max;
    }
    return $value;
}

function bnt_api_string_param(string $key, string $default = ''): string
{
    $value = bnt_api_input_value($key, $default);
    return trim((string) $value);
}

function bnt_api_respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function bnt_api_success(array $data, array $meta = array(), int $statusCode = 200): void
{
    bnt_api_respond(
        array(
            'ok' => true,
            'data' => $data,
            'meta' => $meta,
        ),
        $statusCode
    );
}

function bnt_api_error(string $code, string $message, int $statusCode = 400, array $details = array()): void
{
    bnt_api_respond(
        array(
            'ok' => false,
            'error' => array(
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ),
        ),
        $statusCode
    );
}

function bnt_api_iso_date_or_null(?string $value): ?string
{
    if (!is_string($value) || trim($value) === '') {
        return null;
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return null;
    }

    return gmdate('c', $timestamp);
}

function bnt_api_ensure_tokens_table(): void
{
    global $db;

    static $initialized = false;
    if ($initialized) {
        return;
    }

    $db->Execute(
        "CREATE TABLE IF NOT EXISTS {$db->prefix}api_tokens (" .
        "token_id int unsigned NOT NULL auto_increment," .
        "ship_id int unsigned NOT NULL," .
        "label varchar(100) NOT NULL default ''," .
        "token_prefix varchar(16) NOT NULL default ''," .
        "token_hash char(64) NOT NULL," .
        "scopes varchar(255) NOT NULL default '*'," .
        "created_at datetime NOT NULL," .
        "last_used_at datetime NULL," .
        "revoked_at datetime NULL," .
        "PRIMARY KEY (token_id)," .
        "UNIQUE KEY token_hash (token_hash)," .
        "KEY ship_id (ship_id)" .
        ")"
    );

    $initialized = true;
}

function bnt_api_normalize_scopes($scopes): array
{
    if (!is_array($scopes)) {
        $scopes = explode(',', (string) $scopes);
    }

    $normalized = array();
    foreach ($scopes as $scope) {
        $scope = strtolower(trim((string) $scope));
        if ($scope === '') {
            continue;
        }
        $normalized[$scope] = true;
    }

    if (empty($normalized)) {
        $normalized['*'] = true;
    }

    return array_keys($normalized);
}

function bnt_api_scopes_allow(array $tokenScopes, array $requiredScopes): bool
{
    if (empty($requiredScopes)) {
        return true;
    }

    if (in_array('*', $tokenScopes, true)) {
        return true;
    }

    foreach ($requiredScopes as $requiredScope) {
        if (in_array($requiredScope, $tokenScopes, true)) {
            continue;
        }

        $parts = explode(':', $requiredScope);
        if (count($parts) === 2 && in_array($parts[0] . ':*', $tokenScopes, true)) {
            continue;
        }

        return false;
    }

    return true;
}

function bnt_api_generate_token(int $shipId, string $label = '', array $scopes = array('*')): array
{
    global $db;

    bnt_api_ensure_tokens_table();

    $plaintext = 'bnt_' . bin2hex(random_bytes(24));
    $hash = hash('sha256', $plaintext);
    $prefix = substr($plaintext, 0, 12);
    $scopeList = implode(',', bnt_api_normalize_scopes($scopes));

    $db->Execute(
        "INSERT INTO {$db->prefix}api_tokens (ship_id, label, token_prefix, token_hash, scopes, created_at) VALUES (?, ?, ?, ?, ?, UTC_TIMESTAMP())",
        array($shipId, $label, $prefix, $hash, $scopeList)
    );

    $tokenId = (int) $db->Insert_ID();

    return array(
        'token_id' => $tokenId,
        'token' => $plaintext,
        'token_prefix' => $prefix,
        'label' => $label,
        'scopes' => bnt_api_normalize_scopes($scopeList),
    );
}

function bnt_api_list_tokens(int $shipId): array
{
    global $db;

    bnt_api_ensure_tokens_table();

    $result = $db->Execute(
        "SELECT token_id, label, token_prefix, scopes, created_at, last_used_at, revoked_at
           FROM {$db->prefix}api_tokens
          WHERE ship_id=?
       ORDER BY token_id DESC",
        array($shipId)
    );

    $tokens = array();
    if ($result) {
        while (!$result->EOF) {
            $row = $result->fields;
            $tokens[] = array(
                'token_id' => (int) $row['token_id'],
                'label' => (string) $row['label'],
                'token_prefix' => (string) $row['token_prefix'],
                'scopes' => bnt_api_normalize_scopes((string) $row['scopes']),
                'created_at' => bnt_api_iso_date_or_null($row['created_at'] ?? null),
                'last_used_at' => bnt_api_iso_date_or_null($row['last_used_at'] ?? null),
                'revoked_at' => bnt_api_iso_date_or_null($row['revoked_at'] ?? null),
                'is_active' => empty($row['revoked_at']),
            );
            $result->MoveNext();
        }
    }

    return $tokens;
}

function bnt_api_revoke_token(int $shipId, int $tokenId): bool
{
    global $db;

    bnt_api_ensure_tokens_table();

    $db->Execute(
        "UPDATE {$db->prefix}api_tokens SET revoked_at=UTC_TIMESTAMP() WHERE token_id=? AND ship_id=? AND revoked_at IS NULL",
        array($tokenId, $shipId)
    );

    return ($db->Affected_Rows() > 0);
}

function bnt_api_extract_bearer_token(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!is_string($header) || $header === '') {
        return null;
    }

    if (!preg_match('/Bearer\s+(.+)$/i', $header, $matches)) {
        return null;
    }

    return trim((string) $matches[1]);
}

function bnt_api_get_token_context(?string $rawToken): ?array
{
    global $db;

    if (!is_string($rawToken) || $rawToken === '') {
        return null;
    }

    bnt_api_ensure_tokens_table();

    $hash = hash('sha256', $rawToken);
    $result = $db->Execute(
        "SELECT t.token_id,
                t.ship_id,
                t.label,
                t.token_prefix,
                t.scopes,
                t.created_at,
                t.last_used_at,
                s.email,
                s.character_name
           FROM {$db->prefix}api_tokens t
      LEFT JOIN {$db->prefix}ships s ON s.ship_id = t.ship_id
          WHERE t.token_hash=?
            AND t.revoked_at IS NULL
            AND s.ship_destroyed='N'
          LIMIT 1",
        array($hash)
    );

    if (!$result || $result->EOF) {
        return null;
    }

    $row = $result->fields;
    $db->Execute("UPDATE {$db->prefix}api_tokens SET last_used_at=UTC_TIMESTAMP() WHERE token_id=?", array((int) $row['token_id']));

    return array(
        'source' => 'token',
        'token_id' => (int) $row['token_id'],
        'ship_id' => (int) $row['ship_id'],
        'email' => (string) $row['email'],
        'character_name' => (string) $row['character_name'],
        'scopes' => bnt_api_normalize_scopes((string) $row['scopes']),
        'label' => (string) $row['label'],
        'token_prefix' => (string) $row['token_prefix'],
    );
}

function bnt_api_get_session_context(): ?array
{
    $player = bnt_get_current_playerinfo();
    if (!$player) {
        return null;
    }

    return array(
        'source' => 'session',
        'ship_id' => (int) $player['ship_id'],
        'email' => (string) $player['email'],
        'character_name' => (string) $player['character_name'],
        'scopes' => array('*'),
    );
}

function bnt_api_require_auth(array $requiredScopes = array()): array
{
    $token = bnt_api_extract_bearer_token();
    $context = bnt_api_get_token_context($token);

    if ($context === null) {
        $context = bnt_api_get_session_context();
    }

    if ($context === null) {
        bnt_api_error('auth_required', 'Authentication required.', 401);
    }

    if (!bnt_api_scopes_allow($context['scopes'], $requiredScopes)) {
        bnt_api_error('insufficient_scope', 'API token does not have the required scope.', 403, array('required_scopes' => $requiredScopes));
    }

    return $context;
}

function bnt_api_require_session_auth(): array
{
    $context = bnt_api_get_session_context();
    if ($context === null) {
        bnt_api_error('session_required', 'A browser session is required for this endpoint.', 401);
    }

    return $context;
}

function bnt_api_get_player_snapshot_by_id(int $shipId): ?array
{
    global $db;

    $rankedPlayerSql = bnt_rankings_base_player_sql(false);
    $result = $db->Execute(
        "SELECT ranked.live_score AS score,
                ranked.raw_asset_value,
                ranked.liquid_wealth,
                ranked.planet_count,
                ranked.bounty_total,
                ranked.bank_balance,
                ranked.bank_loan,
                ranked.bank_net,
                ranked.ship_asset,
                ranked.planet_asset,
                ranked.efficiency,
                s.ship_id,
                s.email,
                s.character_name,
                s.ship_name,
                s.ship_destroyed,
                s.turns,
                s.turns_used,
                s.rating,
                s.credits,
                s.sector,
                s.on_planet,
                s.planet_id,
                s.hull,
                s.engines,
                s.power,
                s.computer,
                s.sensors,
                s.beams,
                s.torp_launchers,
                s.shields,
                s.armor,
                s.cloak,
                s.torps,
                s.armor_pts,
                s.ship_ore,
                s.ship_organics,
                s.ship_goods,
                s.ship_energy,
                s.ship_colonists,
                s.ship_fighters,
                s.ship_damage,
                s.dev_warpedit,
                s.dev_genesis,
                s.dev_beacon,
                s.dev_emerwarp,
                s.dev_escapepod,
                s.dev_fuelscoop,
                s.dev_minedeflector,
                s.dev_lssd,
                s.last_login,
                COALESCE(t.team_name, '') AS team_name,
                COALESCE(u.sector_name, '') AS sector_name,
                COALESCE(u.port_type, 'none') AS port_type
           FROM {$db->prefix}ships s
      LEFT JOIN {$db->prefix}teams t ON t.id = s.team
      LEFT JOIN {$db->prefix}universe u ON u.sector_id = s.sector
      LEFT JOIN ({$rankedPlayerSql}) ranked ON ranked.ship_id = s.ship_id
          WHERE s.ship_id=?
          LIMIT 1",
        array($shipId)
    );

    if (!$result || $result->EOF) {
        return null;
    }

    return $result->fields;
}

function bnt_api_get_ship_password_hash(int $shipId): ?string
{
    global $db;

    $result = $db->Execute("SELECT password FROM {$db->prefix}ships WHERE ship_id=? LIMIT 1", array($shipId));
    if (!$result || $result->EOF) {
        return null;
    }

    return (string) $result->fields['password'];
}

function bnt_api_find_player_snapshot(?int $shipId = null, ?string $name = null): ?array
{
    global $db;

    if ($shipId !== null && $shipId > 0) {
        return bnt_api_get_player_snapshot_by_id($shipId);
    }

    $name = trim((string) $name);
    if ($name === '') {
        return null;
    }

    $result = $db->Execute("SELECT ship_id FROM {$db->prefix}ships WHERE character_name=? LIMIT 1", array($name));
    if (!$result || $result->EOF) {
        return null;
    }

    return bnt_api_get_player_snapshot_by_id((int) $result->fields['ship_id']);
}

function bnt_api_get_rank_position(int $rawAssetValue, int $turnsUsed): ?int
{
    global $db;

    if ($turnsUsed < 1) {
        return null;
    }

    $result = $db->Execute(
        "SELECT COUNT(*) + 1 AS rank_position FROM (" . bnt_rankings_base_player_sql(true) . ") ranked_players WHERE ranked_players.raw_asset_value > ?",
        array($rawAssetValue)
    );

    if (!$result || $result->EOF) {
        return null;
    }

    return (int) $result->fields['rank_position'];
}

function bnt_api_ship_payload(array $row, bool $includePrivate = false): array
{
    $payload = array(
        'ship_id' => (int) $row['ship_id'],
        'character_name' => (string) $row['character_name'],
        'ship_name' => (string) $row['ship_name'],
        'team_name' => (string) ($row['team_name'] ?? ''),
        'destroyed' => ((string) ($row['ship_destroyed'] ?? 'N') === 'Y'),
        'score' => (int) ($row['score'] ?? 0),
        'rank_position' => bnt_api_get_rank_position((int) ($row['raw_asset_value'] ?? 0), (int) ($row['turns_used'] ?? 0)),
        'raw_asset_value' => (int) ($row['raw_asset_value'] ?? 0),
        'liquid_wealth' => (int) ($row['liquid_wealth'] ?? 0),
        'planet_count' => (int) ($row['planet_count'] ?? 0),
        'bounty_total' => (int) ($row['bounty_total'] ?? 0),
        'rating' => (int) ($row['rating'] ?? 0),
        'turns_used' => (int) ($row['turns_used'] ?? 0),
        'last_login' => bnt_api_iso_date_or_null($row['last_login'] ?? null),
        'avg_tech' => round((float) get_avg_tech($row), 1),
        'tech' => array(
            'hull' => (int) $row['hull'],
            'engines' => (int) $row['engines'],
            'power' => (int) $row['power'],
            'computer' => (int) $row['computer'],
            'sensors' => (int) $row['sensors'],
            'beams' => (int) $row['beams'],
            'torp_launchers' => (int) $row['torp_launchers'],
            'shields' => (int) $row['shields'],
            'armor' => (int) $row['armor'],
            'cloak' => (int) $row['cloak'],
        ),
    );

    if ($includePrivate) {
        $payload['email'] = (string) $row['email'];
        $payload['turns'] = (int) $row['turns'];
        $payload['sector'] = array(
            'sector_id' => (int) $row['sector'],
            'sector_name' => (string) ($row['sector_name'] ?? ''),
            'port_type' => (string) ($row['port_type'] ?? 'none'),
        );
        $payload['status'] = array(
            'on_planet' => ((string) ($row['on_planet'] ?? 'N') === 'Y'),
            'planet_id' => (int) ($row['planet_id'] ?? 0),
            'ship_damage' => (int) ($row['ship_damage'] ?? 0),
            'armor_points' => (int) ($row['armor_pts'] ?? 0),
            'torpedoes' => (int) ($row['torps'] ?? 0),
            'holds_total' => (int) NUM_HOLDS((int) $row['hull']),
        );
        $payload['cargo'] = array(
            'ore' => (int) $row['ship_ore'],
            'organics' => (int) $row['ship_organics'],
            'goods' => (int) $row['ship_goods'],
            'energy' => (int) $row['ship_energy'],
            'colonists' => (int) $row['ship_colonists'],
            'fighters' => (int) $row['ship_fighters'],
        );
        $payload['devices'] = array(
            'warpedit' => (int) $row['dev_warpedit'],
            'genesis' => (int) $row['dev_genesis'],
            'beacon' => (int) $row['dev_beacon'],
            'emerwarp' => (int) $row['dev_emerwarp'],
            'escapepod' => ((string) ($row['dev_escapepod'] ?? 'N') === 'Y'),
            'fuelscoop' => ((string) ($row['dev_fuelscoop'] ?? 'N') === 'Y'),
            'minedeflector' => (int) $row['dev_minedeflector'],
            'lssd' => ((string) ($row['dev_lssd'] ?? 'N') === 'Y'),
        );
        $payload['economy'] = array(
            'credits' => (int) $row['credits'],
            'bank_balance' => (int) ($row['bank_balance'] ?? 0),
            'bank_loan' => (int) ($row['bank_loan'] ?? 0),
            'bank_net' => (int) ($row['bank_net'] ?? 0),
            'ship_asset' => (int) ($row['ship_asset'] ?? 0),
            'planet_asset' => (int) ($row['planet_asset'] ?? 0),
            'efficiency' => (int) ($row['efficiency'] ?? 0),
        );
    }

    return $payload;
}

function bnt_api_get_rankings_board(string $boardKey, int $limit = 10): array
{
    global $db;

    $limit = max(1, min(50, $limit));
    $baseSql = bnt_rankings_base_player_sql(true);
    $boards = array(
        'score' => array('order' => 'live_score DESC, raw_asset_value DESC, character_name ASC', 'metric' => 'score'),
        'wealth' => array('order' => 'raw_asset_value DESC, live_score DESC, character_name ASC', 'metric' => 'raw_asset_value'),
        'liquid' => array('order' => 'liquid_wealth DESC, raw_asset_value DESC, character_name ASC', 'metric' => 'liquid_wealth'),
        'planets' => array('order' => 'planet_count DESC, planet_asset DESC, character_name ASC', 'metric' => 'planet_count'),
        'honored' => array('order' => 'rating DESC, raw_asset_value DESC, character_name ASC', 'where' => 'rating > 0', 'metric' => 'rating'),
        'notorious' => array('order' => 'rating ASC, raw_asset_value DESC, character_name ASC', 'where' => 'rating < 0', 'metric' => 'rating'),
        'bounty' => array('order' => 'bounty_total DESC, raw_asset_value DESC, character_name ASC', 'metric' => 'bounty_total'),
        'efficiency' => array('order' => 'efficiency DESC, raw_asset_value DESC, character_name ASC', 'metric' => 'efficiency'),
        'recent' => array('order' => 'last_login DESC, character_name ASC', 'metric' => 'last_login'),
    );

    if (!isset($boards[$boardKey])) {
        $boardKey = 'wealth';
    }

    $board = $boards[$boardKey];
    $sql = "SELECT * FROM ({$baseSql}) ranked_players";
    if (!empty($board['where'])) {
        $sql .= " WHERE " . $board['where'];
    }
    $sql .= " ORDER BY " . $board['order'];

    $result = $db->SelectLimit($sql, $limit);
    $rows = array();
    $rank = 1;
    if ($result) {
        while (!$result->EOF) {
            $row = $result->fields;
            $rows[] = array(
                'rank' => $rank,
                'ship_id' => (int) $row['ship_id'],
                'character_name' => (string) $row['character_name'],
                'ship_name' => (string) $row['ship_name'],
                'team_name' => (string) ($row['team_name'] ?? ''),
                'score' => (int) ($row['live_score'] ?? 0),
                'raw_asset_value' => (int) ($row['raw_asset_value'] ?? 0),
                'liquid_wealth' => (int) ($row['liquid_wealth'] ?? 0),
                'planet_count' => (int) ($row['planet_count'] ?? 0),
                'bounty_total' => (int) ($row['bounty_total'] ?? 0),
                'rating' => (int) ($row['rating'] ?? 0),
                'efficiency' => (int) ($row['efficiency'] ?? 0),
                'last_login' => bnt_api_iso_date_or_null($row['last_login'] ?? null),
                'metric' => $row[$board['metric']] ?? null,
            );
            $rank++;
            $result->MoveNext();
        }
    }

    return $rows;
}

function bnt_api_get_news_items(int $limit = 20, ?string $date = null): array
{
    global $db;

    $limit = max(1, min(50, $limit));
    $params = array();
    $sql = "SELECT news_id, headline, newstext, user_id, date, news_type FROM {$db->prefix}news";
    if ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        $sql .= " WHERE date >= ? AND date < DATE_ADD(?, INTERVAL 1 DAY)";
        $params[] = $date . " 00:00:00";
        $params[] = $date . " 00:00:00";
    }
    $sql .= " ORDER BY news_id DESC LIMIT {$limit}";

    $result = $db->Execute($sql, $params);
    $items = array();
    if ($result) {
        while (!$result->EOF) {
            $row = $result->fields;
            $items[] = array(
                'news_id' => (int) $row['news_id'],
                'headline' => (string) $row['headline'],
                'text' => (string) $row['newstext'],
                'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
                'news_type' => (string) ($row['news_type'] ?? ''),
                'published_at' => bnt_api_iso_date_or_null($row['date'] ?? null),
            );
            $result->MoveNext();
        }
    }

    return $items;
}

function bnt_api_get_bounty_board(int $limit = 20): array
{
    global $db;

    $limit = max(1, min(50, $limit));
    $result = $db->SelectLimit(
        "SELECT b.bounty_on,
                SUM(b.amount) AS total_bounty,
                s.character_name,
                s.ship_name
           FROM {$db->prefix}bounty b
      LEFT JOIN {$db->prefix}ships s ON s.ship_id = b.bounty_on
       GROUP BY b.bounty_on, s.character_name, s.ship_name
       ORDER BY total_bounty DESC, s.character_name ASC",
        $limit
    );

    $rows = array();
    if ($result) {
        while (!$result->EOF) {
            $row = $result->fields;
            $rows[] = array(
                'ship_id' => (int) $row['bounty_on'],
                'character_name' => (string) ($row['character_name'] ?? 'Unknown'),
                'ship_name' => (string) ($row['ship_name'] ?? ''),
                'bounty_total' => (int) ($row['total_bounty'] ?? 0),
            );
            $result->MoveNext();
        }
    }

    return $rows;
}

function bnt_api_get_owned_planets(int $shipId, bool $alertsOnly = false): array
{
    global $db, $max_credits_without_base, $organics_consumption;

    $result = $db->Execute(
        "SELECT planet_id, sector_id, name, owner, corp, base, sells, defeated,
                organics, ore, goods, energy, colonists, credits, fighters, torps,
                prod_organics, prod_ore, prod_goods, prod_energy, prod_fighters, prod_torp
           FROM {$db->prefix}planets
          WHERE owner=?
       ORDER BY sector_id ASC, planet_id ASC",
        array($shipId)
    );

    $planets = array();
    if ($result) {
        while (!$result->EOF) {
            $row = $result->fields;
            $flags = array();
            if ((string) $row['defeated'] === 'Y') {
                $flags[] = 'defeated';
            }
            if ((string) $row['base'] !== 'Y') {
                $flags[] = 'no_base';
            }
            if ((string) $row['base'] !== 'Y' && (int) $row['credits'] > (int) $max_credits_without_base) {
                $flags[] = 'credits_over_unbased_limit';
            }
            if ((int) $row['colonists'] > 0) {
                $requiredOrganics = (int) ceil(((int) $row['colonists']) * (float) $organics_consumption);
                if ((int) $row['organics'] < $requiredOrganics) {
                    $flags[] = 'organics_shortage_risk';
                }
            }

            if (!$alertsOnly || !empty($flags)) {
                $planets[] = array(
                    'planet_id' => (int) $row['planet_id'],
                    'sector_id' => (int) $row['sector_id'],
                    'name' => trim((string) $row['name']) !== '' ? (string) $row['name'] : 'Unnamed Planet',
                    'base' => ((string) $row['base'] === 'Y'),
                    'sells' => ((string) $row['sells'] === 'Y'),
                    'defeated' => ((string) $row['defeated'] === 'Y'),
                    'corp_id' => (int) $row['corp'],
                    'resources' => array(
                        'organics' => (int) $row['organics'],
                        'ore' => (int) $row['ore'],
                        'goods' => (int) $row['goods'],
                        'energy' => (int) $row['energy'],
                        'colonists' => (int) $row['colonists'],
                        'credits' => (int) $row['credits'],
                        'fighters' => (int) $row['fighters'],
                        'torps' => (int) $row['torps'],
                    ),
                    'production' => array(
                        'organics' => (int) $row['prod_organics'],
                        'ore' => (int) $row['prod_ore'],
                        'goods' => (int) $row['prod_goods'],
                        'energy' => (int) $row['prod_energy'],
                        'fighters' => (int) $row['prod_fighters'],
                        'torps' => (int) $row['prod_torp'],
                    ),
                    'attention_flags' => $flags,
                );
            }

            $result->MoveNext();
        }
    }

    return $planets;
}

function bnt_api_get_player_bounty_total(int $shipId): int
{
    global $db;

    $result = $db->Execute("SELECT SUM(amount) AS total FROM {$db->prefix}bounty WHERE bounty_on=?", array($shipId));
    if (!$result || $result->EOF) {
        return 0;
    }

    return (int) ($result->fields['total'] ?? 0);
}

function bnt_api_get_alert_bundle(int $shipId, int $turnThreshold = 250): array
{
    $ship = bnt_api_get_player_snapshot_by_id($shipId);
    if ($ship === null) {
        return array();
    }

    $turns = (int) ($ship['turns'] ?? 0);
    $bountyTotal = bnt_api_get_player_bounty_total($shipId);
    $notificationCounts = bnt_get_notification_counts($shipId);
    $notificationItems = bnt_get_recent_notifications($shipId, 10, 'unread');
    $planetAlerts = bnt_api_get_owned_planets($shipId, true);

    $alerts = array();
    if ($turns <= $turnThreshold) {
        $alerts[] = array(
            'type' => 'turns_low',
            'severity' => ($turns <= max(25, (int) floor($turnThreshold / 4))) ? 'high' : 'medium',
            'message' => 'Turns are low.',
            'turns_remaining' => $turns,
            'threshold' => $turnThreshold,
        );
    }

    if ($bountyTotal > 0) {
        $alerts[] = array(
            'type' => 'bounty_active',
            'severity' => 'high',
            'message' => 'An active bounty is posted on this pilot.',
            'bounty_total' => $bountyTotal,
        );
    }

    foreach ($planetAlerts as $planet) {
        $alerts[] = array(
            'type' => 'planet_attention',
            'severity' => in_array('defeated', $planet['attention_flags'], true) ? 'high' : 'medium',
            'message' => 'A planet needs attention.',
            'planet' => array(
                'planet_id' => $planet['planet_id'],
                'name' => $planet['name'],
                'sector_id' => $planet['sector_id'],
                'attention_flags' => $planet['attention_flags'],
            ),
        );
    }

    return array(
        'ship' => bnt_api_ship_payload($ship, true),
        'status' => array(
            'turns_remaining' => $turns,
            'turn_threshold' => $turnThreshold,
            'bounty_total' => $bountyTotal,
            'unread_notifications' => (int) ($notificationCounts['total'] ?? 0),
            'unread_messages' => (int) ($notificationCounts['messages'] ?? 0),
            'unread_activity' => (int) ($notificationCounts['activity'] ?? 0),
        ),
        'alerts' => $alerts,
        'recent_notifications' => $notificationItems,
        'planet_alerts' => $planetAlerts,
    );
}
