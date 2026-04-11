<?php

require_once __DIR__ . '/bootstrap.php';

$auth = bnt_api_require_auth(array('notifications:read'));
$filter = strtolower(bnt_api_string_param('filter', 'all'));
$limit = bnt_api_int_param('limit', 20, 1, 100);
$markViewed = (bnt_api_int_param('mark_viewed', 0, 0, 1) === 1);

$allowedFilters = array('all', 'messages', 'activity', 'unread');
if (!in_array($filter, $allowedFilters, true)) {
    $filter = 'all';
}

if ($markViewed && $filter === 'all') {
    bnt_mark_notifications_viewed((int) $auth['ship_id']);
}

bnt_api_success(
    array(
        'counts' => bnt_get_notification_counts((int) $auth['ship_id']),
        'items' => bnt_get_recent_notifications((int) $auth['ship_id'], $limit, $filter),
    ),
    array(
        'filter' => $filter,
        'limit' => $limit,
        'marked_viewed' => $markViewed && $filter === 'all',
    )
);

