<?php
declare(strict_types=1);

namespace App\Pay\Epay\Impl;

use App\Pay\Signature as SignatureInterface;

class Signature implements SignatureInterface
{
    public function verification(array $data, array $config): bool
    {
        $hash = (string)($data['hash'] ?? '');
        $appid = (string)($data['appid'] ?? '');

        if ($hash === '' || $appid === '') {
            return false;
        }

        $appsecret = $this->resolveAppSecret($appid, $config);
        if ($appsecret === null) {
            return false;
        }

        $params = $data;
        unset($params['hash']);
        $params = array_filter($params, static fn($value): bool => $value !== '' && $value !== null);
        ksort($params, SORT_STRING);

        $payload = [];
        foreach ($params as $key => $value) {
            $payload[] = $key . '=' . $value;
        }

        $calcHash = md5(implode('&', $payload) . $appsecret);
        return hash_equals(strtolower($calcHash), strtolower($hash));
    }

    private function resolveAppSecret(string $appid, array $config): ?string
    {
        $placeholder = (string)($config['placeholder'] ?? 'placeholder');

        foreach ((array)($config['channels'] ?? []) as $channel) {
            $channel = (array)$channel;
            $localAppid = $this->readCredential($channel, 'appid_env', 'legacy_appid_env', $placeholder);
            $localAppsecret = $this->readCredential($channel, 'appsecret_env', 'legacy_appsecret_env', $placeholder);

            if ($localAppid === '' || $localAppsecret === '') {
                continue;
            }

            if (hash_equals($localAppid, $appid)) {
                return $localAppsecret;
            }
        }

        return null;
    }

    private function readCredential(array $channel, string $envKey, string $legacyEnvKey, string $placeholder): string
    {
        foreach ([$channel[$envKey] ?? '', $channel[$legacyEnvKey] ?? ''] as $envName) {
            $value = $this->readEnv((string)$envName);
            if ($value !== '' && !hash_equals($placeholder, $value)) {
                return $value;
            }
        }

        return '';
    }

    private function readEnv(string $name): string
    {
        if ($name === '') {
            return '';
        }

        $value = getenv($name);
        return $value === false ? '' : trim((string)$value);
    }
}
