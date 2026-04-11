<?php

require_once __DIR__ . '/bootstrap.php';

$auth = bnt_api_require_auth(array('planets:read'));
$alertsOnly = (bnt_api_int_param('alerts_only', 0, 0, 1) === 1);

bnt_api_success(
    array(
        'ship_id' => (int) $auth['ship_id'],
        'planets' => bnt_api_get_owned_planets((int) $auth['ship_id'], $alertsOnly),
    ),
    array('alerts_only' => $alertsOnly)
);

