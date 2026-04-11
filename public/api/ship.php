<?php

require_once __DIR__ . '/bootstrap.php';

$shipId = bnt_api_int_param('ship_id', 0, 0);
$name = bnt_api_string_param('name', '');
$profile = bnt_api_find_player_snapshot($shipId > 0 ? $shipId : null, $name !== '' ? $name : null);

if ($profile === null) {
    bnt_api_error('not_found', 'Ship not found.', 404);
}

$auth = bnt_api_get_token_context(bnt_api_extract_bearer_token());
if ($auth === null) {
    $auth = bnt_api_get_session_context();
}
$includePrivate = ($auth !== null && (int) $auth['ship_id'] === (int) $profile['ship_id']);

bnt_api_success(
    array(
        'ship' => bnt_api_ship_payload($profile, $includePrivate),
    ),
    array('private_view' => $includePrivate)
);
