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
        $content = (string)$pickup->content;
        $filename = $this->safeFilename((string)$pickup->filename);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Length: ' . strlen($content));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $content;
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

    private function safeFilename(string $filename): string
    {
        $filename = trim($filename) ?: 'json-pickup.json';
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', $filename) ?: 'json-pickup.json';
        if (!str_ends_with(strtolower($filename), '.json')) {
            $filename .= '.json';
        }
        return $filename;
    }
}
