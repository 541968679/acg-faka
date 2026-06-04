<?php
declare(strict_types=1);

namespace App\View\User\Theme\AiHub;

use App\Consts\Render;

interface Config
{
    const INFO = [
        "NAME" => "AI Hub",
        "AUTHOR" => "KaynLab",
        "VERSION" => "1.0.0",
        "WEB_SITE" => "#",
        "DESCRIPTION" => "AI大模型API中转站主题",
        "RENDER" => Render::ENGINE_SMARTY
    ];

    const SUBMIT = [
        [
            "title" => "ICP备案号",
            "name" => "icp",
            "type" => "input",
            "placeholder" => "填写后将会在底部显示ICP备案号"
        ],
        [
            "title" => "站点副标题",
            "name" => "subtitle",
            "type" => "input",
            "placeholder" => "Hero区域副标题文案"
        ]
    ];

    const THEME = [
        "INDEX" => "Index/Index.html",
        "CLOSED" => "Index/Closed.html",
        "QUERY" => "Index/Query.html",
        "ITEM" => "Index/Item.html",
        "LOGIN" => "Authentication/Login.html",
        "REGISTER" => "Authentication/Register.html",
        "FORGET_EMAIL" => "Authentication/ForgetEmail.html",
        "FORGET_PHONE" => "Authentication/ForgetPhone.html",
        "RECHARGE" => "User/Recharge.html",
        "BILL" => "User/Bill.html",
        "BUSINESS" => "User/Business.html",
        "CATEGORY" => "User/Category.html",
        "COMMODITY" => "User/Commodity.html",
        "CARD" => "User/Card.html",
        "COUPON" => "User/Coupon.html",
        "CASH" => "User/Cash.html",
        "CASH_RECORD" => "User/CashRecord.html",
        "PERSONAL" => "User/Personal.html",
        "EMAIL" => "User/Email.html",
        "PHONE" => "User/Phone.html",
        "PASSWORD" => "User/Password.html",
        "ORDER" => "User/Order.html",
        "PURCHASE_RECORD" => "User/PurchaseRecord.html",
        "DASHBOARD" => "Dashboard/Index.html",
        "AGENT_MEMBER" => "Agent/Member.html",
    ];
}
