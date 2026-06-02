<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

$km = app_input('km');
$sn = app_input('sn');

if ($km === null || $sn === null) {
    json_response(['status' => 1, 'msg' => 'invalid_or_banned']);
}

json_response(bind_card($km, $sn));
