<?php

namespace App\Services\OpenSearch;
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/11
 * Time: 上午11:02
 */
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Api\V1\Transformers\VideoTransformer;
use App\Services\OpenSearch\Client\OpenSearchClient;
use App\Services\OpenSearch\Client\SearchClient;
use App\Services\OpenSearch\Util\SearchParamsBuilder;
use Illuminate\Support\Facades\DB;

class Search
{
    protected $accessKeyId;
    protected $secret;
    protected $endPoint;
    protected $appName;
    protected $suggestName;
    protected $format = 'json';
    protected $table = 'search_v3';

    protected $options = [
        'debug' => false
    ];

    protected $client;
    protected $search;
    protected $params;

    public function __construct()
    {
        $this->accessKeyId = config('search.access');
        $this->secret = config('search.secret');
        $this->endPoint = config('search.endpoint');
        $this->appName = config('search.name');
        $this->client = new OpenSearchClient($this->accessKeyId, $this->secret, $this->endPoint, $this->options);
        $this->search = new SearchClient($this->client);
        $this->params = new SearchParamsBuilder();
    }

    public function index($key)
    {
        $this->params->setStart(0);
        $this->params->setHits(1);
        $this->params->setAppName($this->appName);
        $this->params->setFormat('fulljson');
        $this->params->setQuery("default:'${key}' AND type:'2'");
        $ret = json_decode($this->search->execute($this->params->build())->result, true);
        return $ret['result']['items'];
    }

    public function create($id, $content, $modal, $time = null)
    {
        $modalId = $this->computeModalIdByStr($modal);
        if (!$modalId)
        {
            return 0;
        }

        $ts = $time ?: time();
        return DB::table($this->table)
            ->insertGetId([
                'modal_id' => $modalId,
                'type_id' => $id,
                'content' => $content,
                'created_at' => $ts,
                'updated_at' => $ts
            ]);
    }

    public function delete($id, $modal)
    {
        $modalId = $this->computeModalIdByStr($modal);
        if (!$modalId)
        {
            return 0;
        }

        return DB::table($this->table)
            ->whereRaw('type_id = ? and modal_id = ?', [$id, $modalId])
            ->delete();
    }

    public function update($id, $content, $modal)
    {
        $modalId = $this->computeModalIdByStr($modal);
        if (!$modalId)
        {
            return 0;
        }

        return DB::table($this->table)
            ->whereRaw('type_id = ? and modal_id = ?', [$id, $modalId])
            ->update([
                'content' => $content,
                'updated_at' => time()
            ]);
    }

    public function retrieve($key, $modalId = 0, $page = 0, $count = 15)
    {
        $repository = null;
        if ($modalId)
        {
            $repository = $this->getRepositoryByType($modalId);
            if (is_null($repository))
            {
                return [
                    'total' => 0,
                    'list' => [],
                    'noMore' => true
                ];
            }
        }
        $this->params->setStart($page * $count);
        $this->params->setHits($count);
        $this->params->setAppName('search_v3');
        $this->params->setFormat($this->format);
        $this->params->setQuery(
            $modalId
                ? "default:'${key}' AND modal_id:'${$modalId}'"
                : "default:'${key}'"
        );

        $res = json_decode($this->search->execute($this->params->build())->result, true);

        if ($res['status'] !== 'OK')
        {
            return [
                'total' => 0,
                'list' => [],
                'noMore' => true
            ];
        }

        $ret = $res['result'];
        $list = $ret['items'];

        // type_id 其实是 source_id。。。

        $result = [];
        if ($modalId)
        {
            $transformer = $this->getTransformerByType($modalId);
            foreach ($list as $item)
            {
                $source = $repository->item($item['type_id']);
                if (!is_null($source))
                {
                    $source = $transformer->search($source);
                    $source['type'] = $modalId;
                    $result[] = $source;
                }
            }
        }
        else
        {
            foreach ($list as $item)
            {
                $typeId = intval($item['modal_id']);
                $repository = $this->getRepositoryByType($typeId);
                $source = $repository->item($item['type_id']);
                if (!is_null($source))
                {
                    $transformer = $this->getTransformerByType($typeId);
                    $source = $transformer->search($source);
                    $source['type'] = $typeId;
                    $result[] = $source;
                }
            }
        }

        return [
            'list' => $result,
            'total' => $ret['total'],
            'noMore' => $ret['num'] < $count
        ];
    }

    public function weight($id, $modal, $score = 1)
    {
        $modalId = $this->computeModalIdByStr($modal);
        if (!$modalId)
        {
            return 0;
        }

        return DB::table($this->table)
            ->whereRaw('type_id = ? and modal_id = ?', [$id, $modalId])
            ->increment('score', $score);
    }

    protected function getRepositoryByType($type)
    {
        if ($type === 1)
        {
            return new UserRepository();
        }
        else if ($type === 2)
        {
            return new BangumiRepository();
        }
        else if ($type === 3)
        {
            return new VideoRepository();
        }
        else if ($type === 4)
        {
            return new PostRepository();
        }
        else if ($type === 5)
        {
            return new CartoonRoleRepository();
        }

        return null;
    }

    protected function getTransformerByType($type)
    {
        if ($type === 1)
        {
            return new UserTransformer();
        }
        else if ($type === 2)
        {
            return new BangumiTransformer();
        }
        else if ($type === 3)
        {
            return new VideoTransformer();
        }
        else if ($type === 4)
        {
            return new PostTransformer();
        }
        else if ($type === 5)
        {
            return new CartoonRoleTransformer();
        }

        return null;
    }

    protected function computeModalIdByStr($modal)
    {
        if ($modal === 'user')
        {
            return 1;
        }
        else if ($modal === 'bangumi')
        {
            return 2;
        }
        else if ($modal === 'video')
        {
            return 3;
        }
        else if ($modal === 'post')
        {
            return 4;
        }
        else if ($modal === 'role')
        {
            return 5;
        }

        return 0;
    }
}