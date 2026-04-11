<?php

require_once __DIR__ . '/bootstrap.php';

$session = bnt_api_require_session_auth();
$ship = bnt_api_get_player_snapshot_by_id((int) $session['ship_id']);
if ($ship === null) {
    bnt_api_error('not_found', 'Pilot not found.', 404);
}

$method = bnt_api_request_method();
if ($method === 'GET') {
    bnt_api_success(
        array(
            'ship_id' => (int) $session['ship_id'],
            'tokens' => bnt_api_list_tokens((int) $session['ship_id']),
        )
    );
}

if ($method !== 'POST') {
    bnt_api_error('method_not_allowed', 'Only GET and POST are supported.', 405);
}

$action = strtolower(bnt_api_string_param('action', 'create'));
if ($action === 'create') {
    $password = (string) bnt_api_input_value('password', '');
    $storedPassword = bnt_api_get_ship_password_hash((int) $session['ship_id']);
    if (!is_string($storedPassword) || !bnt_password_verify_legacy($password, $storedPassword)) {
        bnt_api_error('invalid_credentials', 'Current account password is required to create an API token.', 403);
    }

    $label = bnt_api_string_param('label', 'Discord Bot');
    $scopes = bnt_api_input_value('scopes', array('player:read', 'notifications:read', 'alerts:read', 'planets:read'));
    $token = bnt_api_generate_token((int) $session['ship_id'], $label, bnt_api_normalize_scopes($scopes));

    bnt_api_success(
        array(
            'token' => $token,
            'tokens' => bnt_api_list_tokens((int) $session['ship_id']),
        ),
        array('warning' => 'Store the plaintext token now. It will not be shown again.'),
        201
    );
}

if ($action === 'revoke') {
    $tokenId = bnt_api_int_param('token_id', 0, 1);
    if ($tokenId < 1) {
        bnt_api_error('invalid_token_id', 'A valid token_id is required to revoke a token.', 422);
    }

    if (!bnt_api_revoke_token((int) $session['ship_id'], $tokenId)) {
        bnt_api_error('not_found', 'Token not found or already revoked.', 404);
    }

    bnt_api_success(
        array(
            'revoked_token_id' => $tokenId,
            'tokens' => bnt_api_list_tokens((int) $session['ship_id']),
        )
    );
}

bnt_api_error('invalid_action', 'Supported actions are create and revoke.', 422);
