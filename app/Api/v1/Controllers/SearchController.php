<?php

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\BangumiRepository;
use App\Api\V1\Repositories\UserRepository;
use App\Models\Notifications;
use Illuminate\Http\Request;
use App\Services\OpenSearch\Search;
use Illuminate\Support\Facades\DB;
use Mews\Purifier\Facades\Purifier;

/**
 * @Resource("搜索相关接口")
 */
class SearchController extends Controller
{
    public function search(Request $request)
    {
        $key = Purifier::clean($request->get('q'));

        if (!$key)
        {
            return $this->resOK();
        }

        $type = intval($request->get('type')) ?: 0;
        $page = intval($request->get('page')) ?: 0;

        $search = new Search();
        $result = $search->retrieve($key, $type, $page);

        return $this->resOK($result);
    }

    public function bangumis()
    {
        $bangumiRepository = new BangumiRepository();

        return $this->resOK($bangumiRepository->searchAll());
    }

    public function getUserInfo(Request $request)
    {
        $zone = $request->get('zone');
        $userId = $request->get('id');
        if (!$zone && !$userId)
        {
            return $this->resErrBad();
        }

        $userRepository = new UserRepository();
        if (!$userId)
        {
            $userId = $userRepository->getUserIdByZone($zone, true);
        }

        if (!$userId)
        {
            return $this->resErrNotFound();
        }

        return $this->resOK($userRepository->item($userId, true));
    }

    public function migrate()
    {
        $notifications = DB::table('notifications')
            ->get()
            ->toArray();

        foreach ($notifications as $item)
        {
            $type = intval($item->type);
            $ids = explode(',', $item->about_id);
            $modelId = 0;
            $commentId = 0;
            $replyId = 0;

            $resultType = 0;
            if ($type === 1)
            {
                $resultType = 4;
                $modelId = $ids[1];
                $commentId = $ids[0];
            }
            else if ($type === 2)
            {
                $resultType = 5;
                $modelId = $ids[2];
                $commentId = $ids[1];
                $replyId = $ids[0];
            }
            else if ($type === 3)
            {
                $resultType = 1;
                $modelId = $ids[0];
            }
            else if ($type === 4)
            {
                $resultType = 18;
                $modelId = $ids[1];
                $commentId = $ids[0];
            }

            Notifications::create([
                'checked' => $item->checked,
                'from_user_id' => $item->from_user_id,
                'to_user_id' => $item->to_user_id,
                'type' => $resultType,
                'model_id' => $modelId,
                'comment_id' => $commentId,
                'reply_id' => $replyId,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at
            ]);
        }

        return $this->resOK('success');
    }
}
