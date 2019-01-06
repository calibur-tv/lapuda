<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2018/7/2
 * Time: 下午5:55
 */

namespace App\Api\V1\Controllers;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\Tag\Base\UserBadgeService;
use Illuminate\Http\Request;

class UserBadgeController extends Controller
{
    public function __construct()
    {
        $this->badgeService = new UserBadgeService();
    }

    public function show(Request $request)
    {
        $badgeId = $request->get('badge_id');
        $userId = $request->get('user_id');

        $badge = $this->badgeService->getBadgeItem($badgeId, $userId);

        return $this->resOK($badge);
    }

    public function allBadge()
    {
        $badges = $this->badgeService->getAllBadge();

        return $this->resOK([
            'list' => $badges,
            'total' => count($badges),
            'noMore' => true
        ]);
    }

    public function badgeUsers(Request $request)
    {
        $badgeId = $request->get('id');
        $page = $request->get('page') ?: 0;
        $take = $request->get('take') ?: 20;

        $userIds = $this->badgeService->getBadgeUserIds($badgeId);
        $userRepository = new UserRepository();
        $idsObj = $userRepository->filterIdsByPage($userIds, $page, $take);
        $users = $userRepository->list($idsObj['ids']);

        return $this->resOK([
            'list' => $users,
            'total' => $idsObj['total'],
            'noMore' => $idsObj['noMore']
        ]);
    }

    public function createBadge(Request $request)
    {
        $badgeId = $this->badgeService->createBage($request->all());
        $badge = $this->badgeService->getBadgeItem($badgeId);

        return $this->resCreated($badge);
    }

    public function updateBadge(Request $request)
    {
        $badgeId = $this->badgeService->updateBadge($request->all());
        $badge = $this->badgeService->getBadgeItem($badgeId);

        return $this->resOK($badge);
    }

    public function deleteBadge(Request $request)
    {
        $badgeId = $request->get('id');
        $this->badgeService->deleteBadge($badgeId);

        return $this->resNoContent();
    }

    public function setUserBadge(Request $request)
    {
        $badgeId = $request->get('badge_id');
        $userId = $request->get('user_id');

        $this->badgeService->setUserBadge($userId, $badgeId);

        return $this->resNoContent();
    }

    public function removeUserBadge(Request $request)
    {
        $badgeId = $request->get('badge_id');
        $userId = $request->get('user_id');
        $count = $request->get('count') ?: 1;

        $this->badgeService->removeUserBadge($userId, $badgeId, $count);

        return $this->resNoContent();
    }

    public function userBadgeList(Request $request)
    {
        $userId = $request->get('user_id');
        $badges = $this->badgeService->getUserBadges($userId);

        return $this->resOK($badges);
    }
}