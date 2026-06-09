<?php
declare(strict_types=1);

namespace App\Service;

use App\Model\JsonPickup as JsonPickupModel;
use Kernel\Annotation\Bind;

#[Bind(class: \App\Service\Bind\JsonPickup::class)]
interface JsonPickup
{
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
    ): array;

    public function getPublicInfo(string $code): array;

    public function getPublicInfoMany(array|string $codes): array;

    public function download(string $code): JsonPickupModel;

    /**
     * @return array<int, JsonPickupModel>
     */
    public function downloadMany(array|string $codes): array;
}
