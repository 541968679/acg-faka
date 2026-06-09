<?php
declare(strict_types=1);

namespace App\Service\Bind;

use App\Model\Card;
use App\Model\Commodity;
use App\Model\JsonPickup as JsonPickupModel;
use App\Util\Date;
use Illuminate\Database\Capsule\Manager as DB;
use Kernel\Exception\JSONException;

class JsonPickup implements \App\Service\JsonPickup
{
    private const MODES = ['whole', 'array_items', 'jsonl'];
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function importForCommodity(
        int $commodityId,
        string $payload,
        string $mode = 'whole',
        string $prefix = 'JP',
        int $maxDownloads = 1,
        ?string $expireTime = null,
        ?string $batchNo = null,
        ?string $sourceFilename = null,
        array $sku = [],
        ?string $race = null
    ): array
    {
        $commodity = Commodity::query()->find($commodityId);
        if (!$commodity) {
            throw new JSONException('商品不存在');
        }

        if ((int)$commodity->owner !== 0 || (int)$commodity->delivery_way !== 0) {
            throw new JSONException('只能给主站自动发货商品导入 JSON 提卡库存');
        }

        $items = self::parsePayload($payload, $mode);
        $maxDownloads = max(1, min(100, $maxDownloads));
        $expireTime = $this->normalizeExpireTime($expireTime);
        $batchNo = trim((string)$batchNo) ?: 'JSON-' . Date::current('YmdHis');
        $date = Date::current();
        $codes = [];

        DB::connection()->transaction(function () use (
            $items,
            $commodityId,
            $prefix,
            $maxDownloads,
            $expireTime,
            $batchNo,
            $sourceFilename,
            $sku,
            $race,
            $date,
            &$codes
        ) {
            foreach ($items as $item) {
                $code = $this->makeUniqueCode($prefix);

                $pickup = new JsonPickupModel();
                $pickup->commodity_id = $commodityId;
                $pickup->code = $code;
                $pickup->batch_no = $batchNo;
                $pickup->source_filename = $sourceFilename;
                $pickup->filename = $item['filename'];
                $pickup->content = $item['content'];
                $pickup->content_hash = hash('sha256', $item['content']);
                $pickup->size = strlen($item['content']);
                $pickup->status = 0;
                $pickup->max_downloads = $maxDownloads;
                $pickup->download_count = 0;
                $pickup->expire_time = $expireTime;
                $pickup->create_time = $date;
                $pickup->update_time = $date;
                $pickup->save();

                $card = new Card();
                $card->commodity_id = $commodityId;
                $card->owner = 0;
                $card->secret = $code;
                $card->status = 0;
                $card->create_time = $date;
                $card->note = '[JSON提卡] ' . $batchNo;
                if (!empty($sku)) {
                    $card->sku = $sku;
                }
                if ($race) {
                    $card->race = $race;
                }
                $card->save();

                $pickup->card_id = $card->id;
                $pickup->save();
                $codes[] = $code;
            }
        });

        return [
            'success' => count($codes),
            'error' => 0,
            'codes' => implode(PHP_EOL, $codes),
            'batch_no' => $batchNo,
        ];
    }

    public function getPublicInfo(string $code): array
    {
        $pickup = $this->getPickup($code);
        [$canDownload, $statusText] = $this->downloadableState($pickup);

        return [
            'code' => $pickup->code,
            'filename' => $pickup->filename,
            'commodity_name' => $pickup->commodity?->name,
            'size' => $pickup->size,
            'max_downloads' => $pickup->max_downloads,
            'download_count' => $pickup->download_count,
            'expire_time' => $pickup->expire_time,
            'status' => $pickup->status,
            'status_text' => $statusText,
            'can_download' => $canDownload,
        ];
    }

    public function download(string $code): JsonPickupModel
    {
        return DB::connection()->transaction(function () use ($code) {
            $pickup = JsonPickupModel::query()
                ->where('code', self::normalizeCode($code))
                ->lockForUpdate()
                ->first();

            if (!$pickup) {
                throw new JSONException('提卡码不存在');
            }

            [$canDownload, $statusText] = $this->downloadableState($pickup);
            if (!$canDownload) {
                throw new JSONException($statusText);
            }

            $card = $pickup->card_id ? Card::query()->find($pickup->card_id) : null;
            $pickup->download_count = (int)$pickup->download_count + 1;
            $pickup->last_download_time = Date::current();
            $pickup->update_time = Date::current();
            if (!$pickup->order_id && $card?->order_id) {
                $pickup->order_id = $card->order_id;
            }
            if ((int)$pickup->download_count >= (int)$pickup->max_downloads) {
                $pickup->status = 1;
            }
            $pickup->save();

            return $pickup;
        });
    }

    /**
     * @return array<int, array{filename: string, content: string}>
     * @throws JSONException
     */
    public static function parsePayload(string $payload, string $mode): array
    {
        $payload = trim(self::stripUtf8Bom($payload));
        $mode = trim($mode) ?: 'whole';

        if ($payload === '') {
            throw new JSONException('JSON 内容不能为空');
        }
        if (!in_array($mode, self::MODES, true)) {
            throw new JSONException('不支持的 JSON 导入模式');
        }

        if ($mode === 'whole') {
            return [[
                'filename' => 'json-pickup-1.json',
                'content' => self::normalizeJson(self::decodeJson($payload)),
            ]];
        }

        if ($mode === 'array_items') {
            $data = self::decodeJson($payload);

            if (is_array($data) && self::isListArray($data) && count($data) > 0) {
                $items = [];
                foreach ($data as $index => $item) {
                    $items[] = [
                        'filename' => 'json-pickup-' . ($index + 1) . '.json',
                        'content' => self::normalizeJson($item),
                    ];
                }
                return $items;
            }

            return self::splitObjectEnvelope($data);
        }

        $items = [];
        $lines = preg_split('/\R/u', $payload) ?: [];
        foreach ($lines as $line) {
            $line = trim(self::stripUtf8Bom($line));
            if ($line === '') {
                continue;
            }
            $items[] = [
                'filename' => 'json-pickup-' . (count($items) + 1) . '.json',
                'content' => self::normalizeJson(self::decodeJson($line)),
            ];
        }
        if (count($items) === 0) {
            throw new JSONException('JSONL 内容不能为空');
        }

        return $items;
    }

    public static function generateCode(string $prefix = 'JP'): string
    {
        $prefix = strtoupper((string)preg_replace('/[^A-Z0-9]/i', '', $prefix));
        $prefix = substr($prefix ?: 'JP', 0, 12);

        return $prefix . '-' . self::randomChunk(6) . '-' . self::randomChunk(6);
    }

    public static function normalizeCode(string $code): string
    {
        return strtoupper(trim($code));
    }

    private function makeUniqueCode(string $prefix): string
    {
        for ($i = 0; $i < 20; $i++) {
            $code = self::generateCode($prefix);
            $exists = JsonPickupModel::query()->where('code', $code)->exists()
                || Card::query()->where('owner', 0)->where('secret', $code)->exists();
            if (!$exists) {
                return $code;
            }
        }

        throw new JSONException('提卡码生成失败，请重试');
    }

    private function getPickup(string $code): JsonPickupModel
    {
        $pickup = JsonPickupModel::with(['commodity', 'card'])
            ->where('code', self::normalizeCode($code))
            ->first();

        if (!$pickup) {
            throw new JSONException('提卡码不存在');
        }

        return $pickup;
    }

    /**
     * @return array{0: bool, 1: string}
     */
    private function downloadableState(JsonPickupModel $pickup): array
    {
        if ((int)$pickup->status === 2) {
            return [false, '提卡码已锁定'];
        }
        if ((int)$pickup->status === 1 || (int)$pickup->download_count >= (int)$pickup->max_downloads) {
            return [false, '提卡码已领取完'];
        }
        if ($pickup->expire_time && strtotime((string)$pickup->expire_time) < time()) {
            return [false, '提卡码已过期'];
        }

        $card = $pickup->card_id ? Card::query()->find($pickup->card_id) : null;
        if (!$card || (int)$card->status !== 1) {
            return [false, '提卡码尚未售出'];
        }

        return [true, '可下载'];
    }

    private function normalizeExpireTime(?string $expireTime): ?string
    {
        $expireTime = trim((string)$expireTime);
        if ($expireTime === '') {
            return null;
        }

        $time = strtotime($expireTime);
        if ($time === false) {
            throw new JSONException('过期时间格式不正确');
        }

        return date('Y-m-d H:i:s', $time);
    }

    private static function decodeJson(string $payload): mixed
    {
        $payload = self::stripUtf8Bom($payload);
        try {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new JSONException('JSON 格式错误：' . $e->getMessage());
        }
    }

    private static function normalizeJson(mixed $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new JSONException('JSON 编码失败');
        }
        return $json;
    }

    private static function isListArray(array $data): bool
    {
        if ($data === []) {
            return true;
        }
        return array_keys($data) === range(0, count($data) - 1);
    }

    /**
     * @return array<int, array{filename: string, content: string}>
     * @throws JSONException
     */
    private static function splitObjectEnvelope(mixed $data): array
    {
        if (!is_array($data) || self::isListArray($data)) {
            throw new JSONException('数组拆卡模式要求顶层 JSON 是非空数组，或顶层对象内包含 accounts/items/data/list 等数组字段');
        }

        $arrayKey = self::detectPayloadArrayKey($data);
        $array = $data[$arrayKey];

        if (!is_array($array) || count($array) === 0) {
            throw new JSONException('未找到可拆分的 JSON 数组字段');
        }

        $items = [];
        foreach ($array as $index => $item) {
            $envelope = $data;
            $envelope[$arrayKey] = [$item];
            $items[] = [
                'filename' => $arrayKey . '-' . ($index + 1) . '.json',
                'content' => self::normalizeJson($envelope),
            ];
        }

        return $items;
    }

    /**
     * @throws JSONException
     */
    private static function detectPayloadArrayKey(array $data): string
    {
        foreach (['accounts', 'cards', 'items', 'data', 'list'] as $key) {
            if (array_key_exists($key, $data) && is_array($data[$key]) && count($data[$key]) > 0 && self::isListArray($data[$key])) {
                return $key;
            }
        }

        $candidates = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && count($value) > 0 && self::isListArray($value)) {
                $candidates[] = (string)$key;
            }
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        throw new JSONException('未找到唯一可拆分的 JSON 数组字段，请使用 accounts/items/data/list 等字段名');
    }

    private static function stripUtf8Bom(string $payload): string
    {
        return preg_replace('/^\xEF\xBB\xBF/', '', $payload) ?? $payload;
    }

    private static function randomChunk(int $length): string
    {
        $code = '';
        $max = strlen(self::CODE_ALPHABET) - 1;
        for ($i = 0; $i < $length; $i++) {
            $code .= self::CODE_ALPHABET[random_int(0, $max)];
        }
        return $code;
    }
}
