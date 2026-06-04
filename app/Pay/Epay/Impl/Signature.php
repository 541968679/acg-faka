<?php
declare(strict_types=1);

namespace App\Pay\Epay\Impl;

use App\Pay\Signature as SignatureInterface;

class Signature implements SignatureInterface
{
    public function verification(array $data, array $config): bool
    {
        $appsecret = $config['appsecret'];

        if (!isset($data['hash'])) {
            return false;
        }

        $hash = $data['hash'];
        $params = $data;
        unset($params['hash']);

        // È¥³ý¿ÕÖµ
        $params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
        ksort($params);

        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str = rtrim($str, '&');
        $calcHash = md5($str . $appsecret);

        return $calcHash === $hash;
    }
}
