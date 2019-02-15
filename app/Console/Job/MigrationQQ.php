<?php

/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/1/2
 * Time: 下午8:49
 */

namespace App\Console\Job;

use App\Models\User;
use App\Services\Qiniu\Http\Client;
use Illuminate\Console\Command;

class MigrationQQ extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'MigrationQQ';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'migration qq';
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $users = User
            ::withTrashed()
            ->whereNull('qq_unique_id')
            ->whereNotNull('qq_open_id')
            ->select('id', 'qq_open_id')
            ->get()
            ->toArray();
        if (empty($users))
        {
            return true;
        }

        $client = new Client();
        $client_id = config('services.qq.client_id');

        foreach ($users as $user)
        {
            $open_id = $user['qq_open_id'];

            $resp = $client->get(
                "https://graph.qq.com/oauth2.0/get_unionid?openid={$open_id}&client_id={$client_id}",
                [
                    'Accept' => 'application/json'
                ]
            );

            $data = $resp->body;
            $json = $this->removeCallback($data);
            $unique_id = json_decode($json, true)['unionid'];

            User
                ::withTrashed()
                ->where('id', $user['id'])
                ->update([
                    'qq_unique_id' => $unique_id
                ]);
        }

        return true;
    }

    protected function removeCallback($response)
    {
        if (false !== strpos($response, 'callback')) {
            $lpos = strpos($response, '(');
            $rpos = strrpos($response, ')');
            $response = substr($response, $lpos + 1, $rpos - $lpos - 1);
        }

        return $response;
    }
}