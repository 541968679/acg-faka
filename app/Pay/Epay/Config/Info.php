<?php
declare(strict_types=1);

return [
    'name' => '易支付（虎皮椒）',
    'author' => 'kaynlab',
    'describe' => '虎皮椒个人支付接口，支持支付宝和微信通道',
    'version' => '1.1.0',
    'callback' => [
        0x1 => true,
        0x2 => 'status',
        0x3 => 'OD',
        0x4 => true,
        0x5 => 'trade_order_id',
        0x6 => 'total_fee',
        0x7 => 'success',
    ],
];
