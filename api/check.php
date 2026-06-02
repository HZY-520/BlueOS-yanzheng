<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

json_response(check_card_status(app_input('km'), app_input('sn')));
