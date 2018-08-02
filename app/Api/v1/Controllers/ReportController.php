<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/8/2
 * Time: 上午7:08
 */

namespace App\Api\V1\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->types = [
            'user',
            'bangumi',
            'video',
            'role',
            'post',
            'image',
            'score',
            'post_comment',
            'image_comment',
            'score_comment',
        ];
    }

    public function send(Request $request)
    {
        $type = $request->get('type');
        $id = $request->get('id');
        $message = $request->get('message');
        $userId = $this->getAuthUserId();

        if (!in_array($type, $this->types))
        {
            return $this->resErrBad();
        }

        $listCacheKey = $this->getReportListKeyByType($type);
        Redis::ZINCRBY($listCacheKey, 1, $id);

        $itemCacheKey = $this->getReportItemDetailKey($type, $id);
        Redis::RPUSH($itemCacheKey, $userId . ':' . $message);

        return $this->resNoContent();
    }

    protected function getReportListKeyByType($type)
    {
        return 'user_report_' . $type . '_trending_ids';
    }

    protected function getReportItemDetailKey($type, $id)
    {
        return 'user_report_' . $type . '_' . $id . '_detail';
    }
}