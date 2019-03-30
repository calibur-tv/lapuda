<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/9/23
 * Time: 下午10:39
 */

namespace App\Api\V1\Controllers;


use App\Api\V1\Repositories\Repository;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

/**
 * @Resource("友情链接相关接口")
 */
class FriendLinkController extends Controller
{
    public function list()
    {
        $repository = new Repository();

        $result = $repository->Cache('friend_sites', function ()
        {
            return DB
                ::table('friend_sites')
                ->get()
                ->toArray();
        });

        return $this->resOK($result);
    }

    public function append(Request $request)
    {
        DB
            ::table('friend_sites')
            ->insert([
                'name' => $request->get('name'),
                'link' => $request->get('link')
            ]);

        Redis::DEL('friend_sites');

        return $this->resNoContent();
    }

    public function remove(Request $request)
    {
        DB
            ::table('friend_sites')
            ->where('link', $request->get('link'))
            ->delete();

        Redis::DEL('friend_sites');

        return $this->resNoContent();
    }
}