<?php
declare(strict_types=1);

namespace App\Model;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $code
 * @property int $commodity_id
 * @property int|null $card_id
 * @property int|null $order_id
 * @property string|null $batch_no
 * @property string|null $source_filename
 * @property string $filename
 * @property string $content
 * @property string $content_hash
 * @property int $size
 * @property int $status
 * @property int $max_downloads
 * @property int $download_count
 * @property string|null $expire_time
 * @property string $create_time
 * @property string $update_time
 * @property string|null $last_download_time
 */
class JsonPickup extends Model
{
    protected $table = 'json_pickup';
    public $timestamps = false;

    protected $casts = [
        'id' => 'integer',
        'commodity_id' => 'integer',
        'card_id' => 'integer',
        'order_id' => 'integer',
        'size' => 'integer',
        'status' => 'integer',
        'max_downloads' => 'integer',
        'download_count' => 'integer',
    ];

    public function commodity(): ?HasOne
    {
        return $this->hasOne(Commodity::class, 'id', 'commodity_id');
    }

    public function card(): ?HasOne
    {
        return $this->hasOne(Card::class, 'id', 'card_id');
    }

    public function order(): ?HasOne
    {
        return $this->hasOne(Order::class, 'id', 'order_id');
    }
}
