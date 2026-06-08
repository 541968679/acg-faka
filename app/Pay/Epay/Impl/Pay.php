<?php
declare(strict_types=1);

namespace App\Pay\Epay\Impl;

use App\Entity\PayEntity;
use App\Pay\Base;
use App\Pay\Pay as PayInterface;
use Kernel\Exception\JSONException;

class Pay extends Base implements PayInterface
{
    private const DEFAULT_API_URL = 'https://api.xunhupay.com/payment/do.html';

    private function sign(array $params, string $appsecret): string
    {
        unset($params['hash']);
        $params = array_filter($params, static fn($value): bool => $value !== '' && $value !== null);
        ksort($params, SORT_STRING);

        $payload = [];
        foreach ($params as $key => $value) {
            $payload[] = $key . '=' . $value;
        }

        return md5(implode('&', $payload) . $appsecret);
    }

    /**
     * @throws JSONException
     */
    private function resolveChannel(): array
    {
        $code = strtolower(trim($this->code));
        $aliases = [
            'alipay' => 'alipay',
            'wechat' => 'wechat',
            'weixin' => 'wechat',
            'wxpay' => 'wechat',
        ];

        $channelKey = $aliases[$code] ?? null;
        $channels = (array)($this->config['channels'] ?? []);

        if (!$channelKey || !isset($channels[$channelKey])) {
            throw new JSONException("易支付不支持当前支付通道：{$this->code}");
        }

        $channel = (array)$channels[$channelKey];
        $appid = $this->readCredential($channel, 'appid_env', 'legacy_appid_env');
        $appsecret = $this->readCredential($channel, 'appsecret_env', 'legacy_appsecret_env');

        if ($this->isPlaceholder($appid) || $this->isPlaceholder($appsecret)) {
            $name = (string)($channel['name'] ?? $channelKey);
            throw new JSONException("易支付{$name}通道未配置或凭据仍为占位值");
        }

        return [
            'appid' => $appid,
            'appsecret' => $appsecret,
            'plugins' => (string)($channel['plugins'] ?? $channelKey),
        ];
    }

    private function readEnv(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $value = getenv($name);
        return $value === false ? '' : trim((string)$value);
    }

    private function readCredential(array $channel, string $envKey, string $legacyEnvKey): string
    {
        foreach ([$channel[$envKey] ?? '', $channel[$legacyEnvKey] ?? ''] as $envName) {
            $value = $this->readEnv((string)$envName);
            if (!$this->isPlaceholder($value)) {
                return $value;
            }
        }

        return '';
    }

    private function isPlaceholder(string $value): bool
    {
        $placeholder = (string)($this->config['placeholder'] ?? 'placeholder');
        return $value === '' || hash_equals($placeholder, $value);
    }

    public function trade(): PayEntity
    {
        $channel = $this->resolveChannel();
        $apiUrl = trim((string)($this->config['api_url'] ?? self::DEFAULT_API_URL)) ?: self::DEFAULT_API_URL;

        $params = [
            'version' => '1.1',
            'appid' => $channel['appid'],
            'trade_order_id' => $this->tradeNo,
            'total_fee' => sprintf('%.2f', $this->amount),
            'title' => $this->tradeNo,
            'time' => (string)time(),
            'notify_url' => $this->callbackUrl,
            'return_url' => isset($this->returnUrl) ? $this->returnUrl : '',
            'nonce_str' => bin2hex(random_bytes(16)),
            'plugins' => $channel['plugins'],
        ];

        $params['hash'] = $this->sign($params, $channel['appsecret']);

        try {
            $response = $this->http()->post($apiUrl, [
                'form_params' => $params,
                'timeout' => 10,
            ]);
            $body = (string)$response->getBody();
            $result = json_decode($body, true);
            $this->log('下单响应: ' . $body);

            if (!is_array($result)) {
                throw new \RuntimeException('易支付返回内容无法解析');
            }

            if ((int)($result['errcode'] ?? -1) !== 0) {
                $message = (string)($result['errmsg'] ?? $result['msg'] ?? '未知错误');
                $this->log('下单失败: ' . $message);
                throw new \RuntimeException('易支付下单失败: ' . $message);
            }

            $url = (string)($result['url'] ?? '');
            if ($url === '') {
                $url = (string)($result['url_qrcode'] ?? '');
            }

            if ($url === '') {
                throw new \RuntimeException('易支付返回成功但没有支付地址');
            }

            $payEntity = new PayEntity();
            $payEntity->setType(PayInterface::TYPE_REDIRECT);
            $payEntity->setUrl($url);
            return $payEntity;
        } catch (\Throwable $e) {
            $this->log('下单异常: ' . $e->getMessage());
            throw new JSONException('支付下单失败: ' . $e->getMessage());
        }
    }
}
