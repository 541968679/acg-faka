<?php
declare(strict_types=1);

return [
    [
        'name' => 'api_url',
        'title' => 'API地址',
        'type' => 'input',
        'placeholder' => 'https://api.xunhupay.com/payment/do.html',
        'default' => getenv('EPAY_API_URL') ?: 'https://api.xunhupay.com/payment/do.html',
    ],
];
