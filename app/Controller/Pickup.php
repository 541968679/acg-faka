<?php
declare(strict_types=1);

namespace App\Controller;

use Kernel\Annotation\Inject;
use Kernel\Context\Interface\Request;
use Kernel\Exception\JSONException;
use Kernel\Util\View;

class Pickup
{
    #[Inject]
    private \App\Service\JsonPickup $jsonPickup;

    public function index(): string
    {
        return View::render('Pickup/Index.html', ['title' => 'JSON提卡']);
    }

    /**
     * @throws JSONException
     */
    public function query(Request $request): array
    {
        $code = $this->readCode($request);
        return ['code' => 200, 'data' => $this->jsonPickup->getPublicInfo($code)];
    }

    /**
     * @throws JSONException
     */
    public function download(Request $request): null
    {
        $pickup = $this->jsonPickup->download($this->readCode($request));
        return $this->outputJson($pickup);
    }

    /**
     * @throws JSONException
     */
    public function batchQuery(Request $request): array
    {
        $items = $this->jsonPickup->getPublicInfoMany($this->readCodes($request));
        $canDownloadAll = count($items) > 0;
        foreach ($items as $item) {
            if (empty($item['can_download'])) {
                $canDownloadAll = false;
                break;
            }
        }

        return [
            'code' => 200,
            'data' => [
                'total' => count($items),
                'can_download_all' => $canDownloadAll,
                'items' => $items,
            ],
        ];
    }

    /**
     * @throws JSONException
     */
    public function batchDownload(Request $request): null
    {
        $pickups = $this->jsonPickup->downloadMany($this->readCodes($request));
        if (count($pickups) === 1) {
            return $this->outputJson($pickups[0]);
        }

        return $this->outputZip($pickups);
    }

    private function outputJson(\App\Model\JsonPickup $pickup): null
    {
        $content = (string)$pickup->content;
        $filename = $this->safeFilename((string)$pickup->filename);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
        return null;
    }

    /**
     * @param array<int, \App\Model\JsonPickup> $pickups
     * @throws JSONException
     */
    private function outputZip(array $pickups): null
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new JSONException('服务器未启用 ZipArchive，无法批量下载');
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'json-pickup-');
        if ($tmpFile === false) {
            throw new JSONException('无法创建批量下载临时文件');
        }

        $zip = new \ZipArchive();
        if ($zip->open($tmpFile, \ZipArchive::OVERWRITE) !== true) {
            @unlink($tmpFile);
            throw new JSONException('无法创建批量下载压缩包');
        }

        foreach ($pickups as $index => $pickup) {
            $zip->addFromString($this->safeZipEntryName($index + 1, $pickup), (string)$pickup->content);
        }
        $zip->close();

        $size = filesize($tmpFile);
        if ($size === false) {
            @unlink($tmpFile);
            throw new JSONException('无法读取批量下载压缩包');
        }

        $filename = 'json-pickup-' . date('YmdHis') . '.zip';
        header('Content-Type: application/zip');
        header('Content-Length: ' . $size);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        readfile($tmpFile);
        @unlink($tmpFile);
        return null;
    }

    /**
     * @throws JSONException
     */
    private function readCode(Request $request): string
    {
        $code = (string)($request->unsafePost('code') ?: $request->unsafeGet('code') ?: $request->post('code') ?: $request->get('code'));
        $code = \App\Service\Bind\JsonPickup::normalizeCode($code);
        if ($code === '') {
            throw new JSONException('请输入提卡码');
        }
        return $code;
    }

    /**
     * @return array<int, string>
     * @throws JSONException
     */
    private function readCodes(Request $request): array
    {
        foreach ([
            ['unsafePost', 'codes'],
            ['unsafeGet', 'codes'],
            ['post', 'codes'],
            ['get', 'codes'],
            ['unsafePost', 'code'],
            ['unsafeGet', 'code'],
            ['post', 'code'],
            ['get', 'code'],
        ] as [$method, $key]) {
            $codes = $request->$method($key);
            if (is_array($codes) || trim((string)$codes) !== '') {
                return \App\Service\Bind\JsonPickup::parseCodes($codes);
            }
        }

        return \App\Service\Bind\JsonPickup::parseCodes('');
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename) ?: 'json-pickup.json';
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'json-pickup.json';
        if (!str_ends_with(strtolower($filename), '.json')) {
            $filename .= '.json';
        }
        return $filename;
    }

    private function safeZipEntryName(int $index, \App\Model\JsonPickup $pickup): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]/', '-', (string)$pickup->code) ?: 'pickup';

        return str_pad((string)$index, 3, '0', STR_PAD_LEFT)
            . '-' . $code
            . '-' . $this->safeFilename((string)$pickup->filename);
    }
}
