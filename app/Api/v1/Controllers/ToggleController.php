<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/12
 * Time: 下午9:44
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Services\Toggle\Image\ImageLikeService;
use App\Api\V1\Services\Toggle\Post\PostLikeService;
use App\Api\V1\Services\Toggle\Score\ScoreLikeService;
use Illuminate\Http\Request;

class ToggleController extends Controller
{
    public function check(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $likeService = $this->getLikeServiceByType($type);
        if (is_null($likeService))
        {
            return $this->resErrBad();
        }

        return $this->resOK($likeService->checkGetId($userId, $id));
    }

    public function like(Request $request)
    {
        $id = $request->get('id');
        $type = $request->get('type');
        $userId = $this->getAuthUserId();

        $likeService = $this->getLikeServiceByType($type);
        if (is_null($likeService))
        {
            return $this->resErrBad();
        }

        $result = $likeService->toggle($userId, $id);

        return $this->resCreated((boolean)$result);
    }

    public function follow(Request $request)
    {

    }

    public function mark(Request $request)
    {

    }

    public function reward(Request $request)
    {

    }

    protected function getLikeServiceByType($type)
    {
        switch ($type)
        {
            case 'post':
                return new PostLikeService();
            break;
            case 'image':
                return new ImageLikeService();
            break;
            case 'score':
                return new ScoreLikeService();
            break;
            default:
                return null;
        }
    }
}