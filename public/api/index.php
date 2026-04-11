<?php

require_once __DIR__ . '/bootstrap.php';

bnt_api_success(
    array(
        'name' => $game_name,
        'version' => $release_version,
        'endpoints' => array(
            'GET /api/rankings.php?board=wealth&limit=10',
            'GET /api/news.php?limit=10',
            'GET /api/profile.php?ship_id=1',
            'GET /api/profile.php?name=PilotName',
            'GET /api/ship.php?ship_id=1',
            'GET /api/bounties.php?limit=10',
            'GET /api/me.php',
            'GET /api/notifications.php?filter=unread&limit=20',
            'GET /api/alerts.php?turn_threshold=250',
            'GET /api/planets.php?alerts_only=1',
            'GET /api/token.php',
            'POST /api/token.php',
        ),
        'auth' => array(
            'public_endpoints' => array('rankings', 'news', 'profile', 'bounties'),
            'authenticated_endpoints' => array('me', 'notifications', 'alerts', 'planets', 'token'),
            'bearer_format' => 'Authorization: Bearer bnt_<token>',
        ),
    )
);
