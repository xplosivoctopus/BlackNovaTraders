<?php

require_once __DIR__ . '/bootstrap.php';

$board = strtolower(bnt_api_string_param('board', 'wealth'));
$limit = bnt_api_int_param('limit', 10, 1, 50);

bnt_api_success(
    array(
        'board' => $board,
        'entries' => bnt_api_get_rankings_board($board, $limit),
    ),
    array('limit' => $limit)
);

