<?php
declare(strict_types=1);

namespace App\Controller\Admin\Api;

use App\Controller\Base\API\Manage;
use App\Entity\Query\Get;
use App\Interceptor\ManageSession;
use App\Model\Card;
use App\Model\ManageLog;
use App\Service\Query;
use App\Util\Date;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Kernel\Annotation\Inject;
use Kernel\Annotation\Interceptor;
use Kernel\Context\Interface\Request;
use Kernel\Exception\JSONException;
use Kernel\Util\File;
use Kernel\Waf\Filter;

#[Interceptor(ManageSession::class, Interceptor::TYPE_API)]
class JsonPickup extends Manage
{
    #[Inject]
    private Query $query;

    #[Inject]
    private \App\Service\Upload $upload;

    #[Inject]
    private \App\Service\JsonPickup $jsonPickup;

    public function data(): array
    {
        $map = $_POST;
        $get = new Get(\App\Model\JsonPickup::class);
        $get->setPaginate((int)$this->request->post('page'), (int)$this->request->post('limit'));
        $get->setWhere($map);
        $data = $this->query->get($get, function (Builder $builder) {
            return $builder->with([
                'commodity' => function (Relation $relation) {
                    $relation->select(['id', 'cover', 'name']);
                },
                'card' => function (Relation $relation) {
                    $relation->select(['id', 'secret', 'status', 'order_id', 'purchase_time']);
                },
                'order' => function (Relation $relation) {
                    $relation->select(['id', 'trade_no']);
                },
            ]);
        });

        return $this->json(data: $data);
    }

    /**
     * @throws JSONException
     */
    public function upload(): array
    {
        if (!isset($_FILES['file'])) {
            throw new JSONException('请选择 JSON 文件');
        }

        $staticPath = '/assets/cache/json-pickup/';
        $handle = $this->upload->handle(
            $_FILES['file'],
            BASE_PATH . $staticPath,
            ['json', 'jsonl'],
            51200
        );

        if (!is_array($handle)) {
            throw new JSONException((string)$handle);
        }

        return $this->json(200, '上传成功', [
            'url' => $staticPath . $handle['new_name'],
            'name' => $handle['name'],
            'size' => $handle['size'],
        ]);
    }

    /**
     * @throws JSONException
     */
    public function import(Request $request): array
    {
        $commodityId = (int)$request->post('commodity_id', Filter::INTEGER);
        $mode = (string)$request->post('mode', Filter::NORMAL);
        $prefix = (string)$request->post('prefix', Filter::NORMAL);
        $maxDownloads = (int)$request->post('max_downloads', Filter::INTEGER);
        $batchNo = (string)$request->post('batch_no', Filter::NORMAL);
        $expireTime = (string)$request->post('expire_time', Filter::NORMAL);
        $sourceFile = (string)$request->post('source_file', Filter::NORMAL);
        $raceGetMode = (int)$request->post('race_get_mode', Filter::INTEGER);
        $race = $raceGetMode === 1
            ? (string)$request->post('race_input', Filter::NORMAL)
            : (string)$request->post('race', Filter::NORMAL);
        $sku = $request->post('sku', Filter::NORMAL) ?: [];
        $payload = (string)$request->unsafePost('payload');
        $sourceFilename = null;
        $sourcePath = null;

        if ($commodityId <= 0) {
            throw new JSONException('请选择商品');
        }

        if ($sourceFile !== '') {
            if (!preg_match('#^/assets/cache/json-pickup/[A-Za-z0-9._-]+$#', $sourceFile)) {
                throw new JSONException('JSON 文件路径不合法');
            }
            $sourcePath = BASE_PATH . ltrim($sourceFile, '/');
            if (!is_file($sourcePath)) {
                throw new JSONException('JSON 文件不存在，请重新上传');
            }
            $payload = (string)file_get_contents($sourcePath);
            $sourceFilename = basename($sourceFile);
        }

        if (trim($payload) === '') {
            throw new JSONException('请上传 JSON 文件或粘贴 JSON 内容');
        }

        $result = $this->jsonPickup->importForCommodity(
            commodityId: $commodityId,
            payload: $payload,
            mode: $mode ?: 'whole',
            prefix: $prefix ?: 'JP',
            maxDownloads: $maxDownloads ?: 1,
            expireTime: $expireTime ?: null,
            batchNo: $batchNo ?: null,
            sourceFilename: $sourceFilename,
            sku: is_array($sku) ? $sku : [],
            race: $race ?: null
        );

        if ($sourcePath) {
            File::remove($sourcePath);
        }

        ManageLog::log($this->getManage(), '[JSON提卡导入] 导入 ' . $result['success'] . ' 条，批次：' . $result['batch_no']);
        return $this->json(200, '导入成功', $result);
    }

    /**
     * @throws JSONException
     */
    public function edit(): array
    {
        $id = (int)($_POST['id'] ?? 0);
        $pickup = \App\Model\JsonPickup::query()->find($id);
        if (!$pickup) {
            throw new JSONException('提卡记录不存在');
        }

        if (isset($_POST['filename']) && trim((string)$_POST['filename']) !== '') {
            $pickup->filename = trim((string)$_POST['filename']);
        }
        if (isset($_POST['max_downloads']) && (int)$_POST['max_downloads'] > 0) {
            $pickup->max_downloads = min(100, (int)$_POST['max_downloads']);
        }
        if (isset($_POST['expire_time'])) {
            $expireTime = trim((string)$_POST['expire_time']);
            if ($expireTime === '') {
                $pickup->expire_time = null;
            } else {
                $time = strtotime($expireTime);
                if ($time === false) {
                    throw new JSONException('过期时间格式不正确');
                }
                $pickup->expire_time = date('Y-m-d H:i:s', $time);
            }
        }
        if (isset($_POST['status'])) {
            $status = (int)$_POST['status'];
            if (!in_array($status, [0, 1, 2], true)) {
                throw new JSONException('状态不合法');
            }
            $pickup->status = $status;
            if ($pickup->card_id) {
                $cardStatus = $status === 0 ? 0 : 2;
                Card::query()->where('id', $pickup->card_id)->whereRaw('status!=1')->update(['status' => $cardStatus]);
            }
        }
        $pickup->update_time = Date::current();
        $pickup->save();

        ManageLog::log($this->getManage(), '[JSON提卡编辑] 修改提卡码：' . $pickup->code);
        return $this->json(200, '保存成功');
    }

    public function lock(): array
    {
        $list = (array)($_POST['list'] ?? []);
        $pickups = \App\Model\JsonPickup::query()->whereIn('id', $list)->where('status', 0)->get();
        $cardIds = [];
        foreach ($pickups as $pickup) {
            if ($pickup->card_id) {
                $cardIds[] = $pickup->card_id;
            }
        }

        \App\Model\JsonPickup::query()->whereIn('id', $list)->where('status', 0)->update(['status' => 2, 'update_time' => Date::current()]);
        if ($cardIds) {
            Card::query()->whereIn('id', $cardIds)->whereRaw('status!=1')->update(['status' => 2]);
        }

        ManageLog::log($this->getManage(), '[JSON提卡锁定] 批量锁定：' . count($list));
        return $this->json(200, '锁定成功');
    }

    public function unlock(): array
    {
        $list = (array)($_POST['list'] ?? []);
        $pickups = \App\Model\JsonPickup::query()->whereIn('id', $list)->where('status', 2)->get();
        $cardIds = [];
        foreach ($pickups as $pickup) {
            if ($pickup->card_id) {
                $cardIds[] = $pickup->card_id;
            }
        }

        \App\Model\JsonPickup::query()->whereIn('id', $list)->where('status', 2)->update(['status' => 0, 'update_time' => Date::current()]);
        if ($cardIds) {
            Card::query()->whereIn('id', $cardIds)->whereRaw('status!=1')->update(['status' => 0]);
        }

        ManageLog::log($this->getManage(), '[JSON提卡解锁] 批量解锁：' . count($list));
        return $this->json(200, '解锁成功');
    }

    /**
     * @throws JSONException
     */
    public function del(): array
    {
        $list = (array)($_POST['list'] ?? []);
        if (count($list) === 0) {
            throw new JSONException('请选择要删除的记录');
        }

        $pickups = \App\Model\JsonPickup::query()->whereIn('id', $list)->get();
        $count = 0;
        foreach ($pickups as $pickup) {
            if ($pickup->card_id) {
                Card::query()->where('id', $pickup->card_id)->whereRaw('status!=1')->delete();
            }
            if ($pickup->delete()) {
                $count++;
            }
        }

        if ($count === 0) {
            throw new JSONException('没有删除任何记录');
        }

        ManageLog::log($this->getManage(), '[JSON提卡删除] 批量删除：' . $count);
        return $this->json(200, '删除成功');
    }
}
