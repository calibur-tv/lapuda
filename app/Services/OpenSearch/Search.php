<?php

namespace App\Services\OpenSearch;
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/11
 * Time: 上午11:02
 */
use App\Api\V1\Repositories\AnswerRepository;
use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\CartoonRoleRepository;
use App\Api\V1\Repositories\ImageRepository;
use App\Api\V1\Repositories\PostRepository;
use App\Api\V1\Repositories\QuestionRepository;
use App\Api\V1\Repositories\ScoreRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Repositories\VideoRepository;
use App\Api\V1\Services\Trending\AnswerTrendingService;
use App\Api\V1\Services\Trending\ImageTrendingService;
use App\Api\V1\Services\Trending\PostTrendingService;
use App\Api\V1\Services\Trending\QuestionTrendingService;
use App\Api\V1\Services\Trending\CartoonRoleTrendingService;
use App\Api\V1\Services\Trending\ScoreTrendingService;
use App\Api\V1\Transformers\AnswerTransformer;
use App\Api\V1\Transformers\BangumiTransformer;
use App\Api\V1\Transformers\CartoonRoleTransformer;
use App\Api\V1\Transformers\ImageTransformer;
use App\Api\V1\Transformers\PostTransformer;
use App\Api\V1\Transformers\QuestionTransformer;
use App\Api\V1\Transformers\ScoreTransformer;
use App\Api\V1\Transformers\UserTransformer;
use App\Api\V1\Transformers\VideoTransformer;
use App\Services\OpenSearch\Client\OpenSearchClient;
use App\Services\OpenSearch\Client\SearchClient;
use App\Services\OpenSearch\Util\SearchParamsBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

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

    public function create($id, $content, $modal, $time = null)
    {
        if (config('app.env') !== 'production')
        {
            return 0;
        }

        $modalId = $this->convertModal($modal);
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
        if (config('app.env') !== 'production')
        {
            return 0;
        }

        $modalId = $this->convertModal($modal);
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
        if (config('app.env') !== 'production')
        {
            return 0;
        }

        $modalId = $this->convertModal($modal);
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

    public function retrieve($key, $type = 'all', $page = 0, $count = 15)
    {
        $repository = null;
        $modalId = $this->convertModal($type);
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
        $this->params->setAppName($this->appName);
        $this->params->setFormat($this->format);
        $this->params->setQuery(
            $modalId
                ? "default:'${key}' AND modal_id:'" . $modalId . "'&&sort=-(score)"
                : "default:'${key}'&&sort=-(score)"
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
            $trendingService = $this->getTrendingServiceByType($modalId);
            if (is_null($trendingService))
            {
                $transformer = $this->getTransformerByType($modalId);
                if (!is_null($transformer))
                {
                    foreach ($list as $item)
                    {
                        $source = $repository->item($item['type_id']);
                        if (is_null($source))
                        {
                            $job = (new \App\Jobs\Search\Delete([$item['type_id']], $modalId));
                            dispatch($job);
                        }
                        else
                        {
                            $source = $transformer->search($source);
                            if (is_null($source))
                            {
                                continue;
                            }
                            $source['type'] = $type;
                            $result[] = $source;
                        }
                    }
                }
            }
            else
            {
                $ids = array_map(function ($item)
                {
                    return $item['type_id'];
                }, $list);

                $trendingList = $trendingService->getListByIds($ids, 'trending');
                foreach ($trendingList as $trendingItem)
                {
                    $trendingItem['type'] = $type;
                    $result[] = $trendingItem;
                }

                $resultIds = array_map(function ($item)
                {
                    return $item['id'];
                }, $trendingList);

                $emptyIds = array_diff($ids, $resultIds);
                if (count($emptyIds))
                {
                    $job = (new \App\Jobs\Search\Delete($emptyIds, $modalId));
                    dispatch($job);
                }
            }
        }
        else
        {
            foreach ($list as $item)
            {
                $typeId = intval($item['modal_id']);
                $trendingService = $this->getTrendingServiceByType($typeId);
                if (is_null($trendingService))
                {
                    $repository = $this->getRepositoryByType($typeId);
                    if (is_null($repository))
                    {
                        continue;
                    }
                    $source = $repository->item($item['type_id']);
                    if (is_null($source))
                    {
                        $job = (new \App\Jobs\Search\Delete([$item['type_id']], $typeId));
                        dispatch($job);
                        continue;
                    }
                    $transformer = $this->getTransformerByType($typeId);
                    $source = $transformer->search($source);
                    if (is_null($source))
                    {
                        continue;
                    }
                    $source['type'] = $this->convertModal($typeId);
                    $result[] = $source;
                }
                else
                {
                    $trendingItems = $trendingService->getListByIds([$item['type_id']], 'trending');
                    if (empty($trendingItems))
                    {
                        $job = (new \App\Jobs\Search\Delete([$item['type_id']], $typeId));
                        dispatch($job);
                    }
                    else
                    {
                        $trendingItem = $trendingItems[0];
                        $trendingItem['type'] = $this->convertModal($typeId);
                        $result[] = $trendingItem;
                    }
                }
            }
        }

        return [
            'list' => $result,
            'total' => $ret['total'],
            'noMore' => $ret['num'] < $count
        ];
    }

    public function weight($id, $modal, $score)
    {
        if (config('app.env') !== 'production')
        {
            return;
        }

        $modalId = $this->convertModal($modal);
        if (!$modalId)
        {
            return;
        }

        DB::table($this->table)
            ->whereRaw('type_id = ? and modal_id = ?', [$id, $modalId])
            ->update([
                'score' => $score
            ]);
    }

    public function checkNeedMigrate($modal, $id)
    {
        $result = false;
        $cacheKey = $this->migrateWeightKey($modal, $id);
        if (!Redis::EXISTS($cacheKey))
        {
            $result = true;
        }

        if (time() - Redis::GET($cacheKey) > 86400)
        {
            $result = true;
        }

        if ($result)
        {
            Redis::SET($cacheKey, time());
            Redis::EXPIRE($cacheKey, 86400);
        }

        return $result;
    }

    public function convertModal($modal)
    {
        $arr = [
            'all' => 0,
            'user' => 1,
            'bangumi' => 2,
            'video' => 3,
            'post' => 4,
            'role' => 5,
            'image' => 6,
            'score' => 7,
            'question' => 8,
            'answer' => 9
        ];

        try
        {
            if (gettype($modal) === 'string')
            {
                return $arr[$modal] ?: 0;
            }

            return array_flip($arr)[$modal] ?: 'all';
        }
        catch (\Exception $e)
        {
            return gettype($modal) === 'string' ? 0 : 'all';
        }
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
        else if ($type === 6)
        {
            return new ImageRepository();
        }
        else if ($type === 7)
        {
            return new ScoreRepository();
        }
        else if ($type === 8)
        {
            return new QuestionRepository();
        }
        else if ($type === 9)
        {
            return new AnswerRepository();
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
        else if ($type === 6)
        {
            return new ImageTransformer();
        }
        else if ($type === 7)
        {
            return new ScoreTransformer();
        }
        else if ($type === 8)
        {
            return new QuestionTransformer();
        }
        else if ($type === 9)
        {
            return new AnswerTransformer();
        }

        return null;
    }

    protected function migrateWeightKey($modal, $id)
    {
        return $modal . '-' . $id . '-last-migrate-search-ts';
    }

    protected function getTrendingServiceByType($type)
    {
        if ($type === 1)
        {
            return null;
        }
        else if ($type === 2)
        {
            return null;
        }
        else if ($type === 3)
        {
            return null;
        }
        else if ($type === 4)
        {
            return new PostTrendingService();
        }
        else if ($type === 5)
        {
            return new CartoonRoleTrendingService();
        }
        else if ($type === 6)
        {
            return new ImageTrendingService();
        }
        else if ($type === 7)
        {
            return new ScoreTrendingService();
        }
        else if ($type === 8)
        {
            return new QuestionTrendingService();
        }
        else if ($type === 9)
        {
            return new AnswerTrendingService();
        }

        return null;
    }
}