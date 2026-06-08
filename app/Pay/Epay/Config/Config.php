<?php
declare(strict_types=1);

return [
    'api_url' => getenv('EPAY_API_URL') ?: 'https://api.xunhupay.com/payment/do.html',
    'placeholder' => 'placeholder',
    'channels' => [
        'alipay' => [
            'name' => '支付宝',
            'plugins' => 'alipay',
            'appid_env' => 'EPAY_ALIPAY_APPID',
            'appsecret_env' => 'EPAY_ALIPAY_APPSECRET',
            'legacy_appid_env' => 'EPAY_APPID',
            'legacy_appsecret_env' => 'EPAY_APPSECRET',
        ],
        'wechat' => [
            'name' => '微信',
            'plugins' => 'wechat',
            'appid_env' => 'EPAY_WECHAT_APPID',
            'appsecret_env' => 'EPAY_WECHAT_APPSECRET',
        ],
    ],
];
