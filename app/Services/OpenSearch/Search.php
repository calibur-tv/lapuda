<?php

namespace App\Services\OpenSearch;
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/2/11
 * Time: 上午11:02
 */
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
    protected $format = 'fulljson';

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
        $this->params->setFormat($this->format);
        $this->params->setQuery("default:'${key}' AND type:'2'");

        $ret = json_decode($this->search->execute($this->params->build())->result, true);

        return $ret['result']['items'];
    }

    public function create()
    {
        
    }

    public function update()
    {
        
    }

    public function retrieve()
    {

    }

    public function delete()
    {

    }
}