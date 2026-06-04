<?php
declare(strict_types=1);

return [
    'appid' => getenv('EPAY_APPID') ?: '',
    'appsecret' => getenv('EPAY_APPSECRET') ?: '',
];
