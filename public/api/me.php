<?php

require_once __DIR__ . '/bootstrap.php';

$auth = bnt_api_require_auth(array('player:read'));
$ship = bnt_api_get_player_snapshot_by_id((int) $auth['ship_id']);
if ($ship === null) {
    bnt_api_error('not_found', 'Pilot not found.', 404);
}

bnt_api_success(
    array(
        'auth' => array(
            'source' => $auth['source'],
            'ship_id' => (int) $auth['ship_id'],
            'character_name' => (string) $auth['character_name'],
        ),
        'ship' => bnt_api_ship_payload($ship, true),
    )
);

