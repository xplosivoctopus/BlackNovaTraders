<?php

require_once __DIR__ . '/bootstrap.php';

$limit = bnt_api_int_param('limit', 10, 1, 50);

bnt_api_success(
    array(
        'entries' => bnt_api_get_bounty_board($limit),
    ),
    array('limit' => $limit)
);

