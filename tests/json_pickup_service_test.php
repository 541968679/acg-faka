<?php
declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
require BASE_PATH . 'vendor/autoload.php';

use App\Service\Bind\JsonPickup;
use Kernel\Exception\JSONException;

function assertSameValue(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL);
        fwrite(STDERR, 'Expected: ' . var_export($expected, true) . PHP_EOL);
        fwrite(STDERR, 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assertThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (JSONException) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

function assertTrueValue(bool $actual, string $message): void
{
    if (!$actual) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$single = JsonPickup::parsePayload('{"account":"alpha","token":"secret"}', 'whole');
assertSameValue(1, count($single), 'whole mode should keep the payload as one pickup item');
assertSameValue('json-pickup-1.json', $single[0]['filename'], 'whole mode should assign a stable default filename');

$arrayItems = JsonPickup::parsePayload('[{"id":1},{"id":2}]', 'array_items');
assertSameValue(2, count($arrayItems), 'array_items mode should split a top-level JSON array');
assertSameValue('json-pickup-2.json', $arrayItems[1]['filename'], 'array_items mode should number filenames');

$sub2apiExport = JsonPickup::parsePayload("\xEF\xBB\xBF" . '{"type":"sub2api","version":1,"proxies":[],"accounts":[{"id":1},{"id":2}]}', 'array_items');
assertSameValue(2, count($sub2apiExport), 'array_items mode should split object envelopes that contain an accounts array');
$sub2apiCard = json_decode($sub2apiExport[0]['content'], true);
assertSameValue('sub2api', $sub2apiCard['type'], 'object-envelope split should preserve top-level metadata');
assertSameValue(1, count($sub2apiCard['accounts']), 'object-envelope split should keep one account per pickup item');
assertTrueValue(array_key_exists('proxies', $sub2apiCard), 'object-envelope split should preserve sibling fields');

$jsonl = JsonPickup::parsePayload("{\"id\":1}\n\n{\"id\":2}", 'jsonl');
assertSameValue(2, count($jsonl), 'jsonl mode should parse non-empty JSON lines');

assertThrows(
    static fn() => JsonPickup::parsePayload('{"broken":', 'whole'),
    'invalid JSON should be rejected'
);

$code = JsonPickup::generateCode('JP');
assertSameValue(1, preg_match('/^JP-[A-Z0-9]{6}-[A-Z0-9]{6}$/', $code), 'pickup code should use the expected readable format');

$codes = JsonPickup::parseCodes(" jp-aaa111-bbb222 \nJP-CCC333-DDD444, jp-aaa111-bbb222\r\n");
assertSameValue(
    ['JP-AAA111-BBB222', 'JP-CCC333-DDD444'],
    $codes,
    'batch pickup code parsing should normalize, dedupe, and preserve first-seen order'
);

assertThrows(
    static fn() => JsonPickup::parseCodes(" \n\t "),
    'empty batch pickup code input should be rejected'
);

echo 'json pickup service tests passed' . PHP_EOL;
