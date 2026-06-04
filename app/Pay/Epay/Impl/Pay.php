<?php
declare(strict_types=1);

namespace App\Pay\Epay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Pay\Pay as PayInterface;
use Kernel\Exception\JSONException;

class Pay extends Base implements PayInterface
{
    private function sign(array $params, string $appsecret): string
    {
        $params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
        unset($params['hash']);
        ksort($params);
        $str = '';
        foreach ($params as $k => $v) {
            $str .= $k . '=' . $v . '&';
        }
        $str = rtrim($str, '&');
        return md5($str . $appsecret);
    }

    public function trade(): PayEntity
    {
        $appid = $this->config['appid'];
        $appsecret = $this->config['appsecret'];
        $apiUrl = 'https://api.xunhupay.com/payment/do.html';

        $params = [
            'version' => '1.1',
            'appid' => $appid,
            'trade_order_id' => $this->tradeNo,
            'total_fee' => sprintf('%.2f', $this->amount),
            'title' => $this->tradeNo,
            'time' => (string)time(),
            'notify_url' => $this->callbackUrl,
            'nonce_str' => bin2hex(random_bytes(16)),
        ];

        // return_url must be included BEFORE signing
        if (!empty($this->returnUrl)) {
            $params['return_url'] = $this->returnUrl;
        }

        $params['hash'] = $this->sign($params, $appsecret);

        try {
            $response = $this->http()->post($apiUrl, [
                'form_params' => $params,
                'timeout' => 10
            ]);
            $result = json_decode($response->getBody()->getContents(), true);
            $this->log('�µ���Ӧ: ' . json_encode($result, JSON_UNESCAPED_UNICODE));

            if (isset($result['errcode']) && $result['errcode'] == 0) {
                $payEntity = new PayEntity();
                if (!empty($result['url'])) {
                    $payEntity->setType(PayInterface::TYPE_REDIRECT);
                    $payEntity->setUrl($result['url']);
                } elseif (!empty($result['url_qrcode'])) {
                    $payEntity->setType(PayInterface::TYPE_REDIRECT);
                    $payEntity->setUrl($result['url_qrcode']);
                } else {
                    throw new \Exception('δ����֧������');
                }
                return $payEntity;
            } else {
                $msg = $result['errmsg'] ?? 'δ֪����';
                $this->log('�µ�ʧ��: ' . $msg);
                throw new \Exception('֧���µ�ʧ��: ' . $msg);
            }
        } catch (\Exception $e) {
            $this->log('下单异常: ' . $e->getMessage());
            throw new JSONException("支付下单失败: " . $e->getMessage());
        }
    }
}
