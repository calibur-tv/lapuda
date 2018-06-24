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
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Services\OpenSearch\Client\OpenSearchClient;
use App\Services\OpenSearch\Client\SearchClient;
use App\Services\OpenSearch\Util\SearchParamsBuilder;
use Illuminate\Support\Facades\Log;

class Search
{
    protected $accessKeyId;
    protected $secret;
    protected $endPoint;
    protected $appName;
    protected $suggestName;
    protected $format = 'json';

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

    public function index($key, $type = 0, $page = 0, $count = 15)
    {
        $repository = null;
        if ($type)
        {
            $repository = $this->getRepositoryByType($type);
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
        $this->params->setAppName($this->appName);
        $this->params->setFormat($this->format);
        $this->params->setQuery(
            $type
                ? "default:'${key}' AND type:'${$type}'"
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

        $result = [];
        if ($type)
        {
            $transformer = $this->getTransformerByType($type);
            foreach ($list as $item)
            {
                $source = $repository->item($item['id']);
                if (!is_null($source))
                {
                    $source = $transformer->search($source);
                    $source['type'] = $type;
                    $result[] = $source;
                }
            }
        }
        else
        {
            foreach ($list as $item)
            {
                $typeId = intval($item['type_id']);
                $repository = $this->getRepositoryByType($typeId);
                $source = $repository->item($item['id']);
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

    public function getRepositoryByType($type)
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
            return new PostRepository();
        }
        else if ($type === 4)
        {
            return new CartoonRoleRepository();
        }

        return null;
    }

    public function getTransformerByType($type)
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
            return new PostTransformer();
        }
        else if ($type === 4)
        {
            return new CartoonRoleTransformer();
        }

        return null;
    }
}