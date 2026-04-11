<?php

require_once __DIR__ . '/bootstrap.php';

$auth = bnt_api_require_auth(array('alerts:read'));
$turnThreshold = bnt_api_int_param('turn_threshold', 250, 1, 5000);

bnt_api_success(
    bnt_api_get_alert_bundle((int) $auth['ship_id'], $turnThreshold),
    array('turn_threshold' => $turnThreshold)
);

