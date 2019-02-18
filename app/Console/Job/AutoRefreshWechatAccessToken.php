<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use App\Services\Qiniu\Http\Client;

class AutoRefreshWechatAccessToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'AutoRefreshWechatAccessToken';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'auto refresh wechat access token';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $client = new Client();
        $appId = config('services.weixin.client_id');
        $appSecret = config('services.weixin.client_secret');
        $resp = $client->get(
            "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}",
            [
                'Accept' => 'application/json'
            ]
        );

        try
        {
            $body = $resp->body;
            $token = json_decode($body, true)['access_token'];
        }
        catch (\Exception $e)
        {
            $token = '';
        }

        if ($token)
        {
            Redis::SET('wechat_js_sdk_access_token', $token);
        }

        return true;
    }
}