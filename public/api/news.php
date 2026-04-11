<?php

require_once __DIR__ . '/bootstrap.php';

$limit = bnt_api_int_param('limit', 10, 1, 50);
$date = bnt_api_string_param('date', '');
$date = ($date !== '') ? $date : null;
$motd = bnt_get_motd();

bnt_api_success(
    array(
        'motd' => array(
            'headline' => (string) ($motd['headline'] ?? ''),
            'body' => (string) ($motd['body'] ?? ''),
            'is_active' => ((string) ($motd['is_active'] ?? 'N') === 'Y'),
            'updated_at' => bnt_api_iso_date_or_null($motd['updated_at'] ?? null),
        ),
        'items' => bnt_api_get_news_items($limit, $date),
    ),
    array('limit' => $limit, 'date' => $date)
);

