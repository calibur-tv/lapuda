<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/23
 * Time: 上午10:08
 */

namespace App\Api\V1\Repositories;

use App\Models\Banner;
use App\Models\Image;
use App\Models\ImageLike;
use App\Models\ImageTag;
use App\Models\Tag;

class ImageRepository extends Repository
{
    public function item($id)
    {
        if (!$id)
        {
            return null;
        }

        $result = $this->RedisHash('user_image_' . $id, function () use ($id)
        {
            $image = Image::where('id', $id)->first();
            if (is_null($image))
            {
                return null;
            }

            $image = $image->toArray();
            $image['image_count'] = $image['image_count'] ? ($image['image_count'] - 1) : 0;
            $image['width'] = $image['width'] ? $image['width'] : 200;
            $image['height'] = $image['height'] ? $image['height'] : 200;

            return $image;
        }, 'm');

        if (is_null($result))
        {
            return null;
        }

        $meta = $this->Cache('user_image_' . $id . '_meta', function () use ($result)
        {
            $tagIds = ImageTag::where('image_id', $result['id'])->pluck('tag_id');

            $bangumiRepository = new BangumiRepository();
            $cartoonRoleRepository = new CartoonRoleRepository();
            $userRepository = new UserRepository();

            return [
                'user' => $userRepository->item($result['user_id']),
                'role' => $result['role_id'] ? $cartoonRoleRepository->item($result['role_id']) : null,
                'bangumi' => $result['bangumi_id'] ? $bangumiRepository->item($result['bangumi_id']) : null,
                'tags' => Tag::whereIn('id', $tagIds)->select('id', 'name')->get(),
                'size' => $result['image_count'] ? null : Tag::where('id', $result['size_id'])->select('id', 'name')->first()
            ];
        });

        return array_merge($result, $meta);
    }

    public function list($ids)
    {
        $result = [];
        foreach ($ids as $id)
        {
            $item = $this->item($id);
            if ($item) {
                $result[] = $item;
            }
        }
        return $result;
    }

    public function uptoken()
    {
        $auth = new \App\Services\Qiniu\Auth();
        $timeout = 3600;
        $uptoken = $auth->uploadToken(null, $timeout, [
            'returnBody' => '{
                "code": 0,
                "data": {
                    "height": $(imageInfo.height),
                    "width": $(imageInfo.width),
                    "type": "$(mimeType)",
                    "size": $(fsize),
                    "key": "$(key)"
                }
            }',
            'mimeLimit' => 'image/jpeg;image/png;image/jpg;image/gif'
        ]);

        return [
            'upToken' => $uptoken,
            'expiredAt' => time() + $timeout
        ];
    }

    public function banners($withTrashed = false)
    {
        $list = $this->RedisList($withTrashed ? 'loop_banners_all' : 'loop_banners', function () use ($withTrashed)
        {
            $list = $withTrashed
                ? Banner::withTrashed()
                    ->select('id', 'url', 'user_id', 'bangumi_id', 'gray', 'deleted_at')
                    ->orderBy('id', 'DESC')
                    ->get()
                    ->toArray()
                : Banner::select('id', 'url', 'user_id', 'bangumi_id', 'gray')->get()->toArray();

            $userRepository = new UserRepository();
            $bangumiRepository = new BangumiRepository();

            foreach ($list as $i => $image)
            {
                if ($image['user_id'])
                {
                    $user = $userRepository->item($image['user_id']);
                    $list[$i]['user_nickname'] = $user['nickname'];
                    $list[$i]['user_avatar'] = $user['avatar'];
                    $list[$i]['user_zone'] = $user['zone'];
                }
                else
                {
                    $list[$i]['user_nickname'] = '';
                    $list[$i]['user_avatar'] = '';
                    $list[$i]['user_zone'] = '';
                }

                if ($image['bangumi_id'])
                {
                    $bangumi = $bangumiRepository->item($image['bangumi_id']);
                    $list[$i]['bangumi_name'] = $bangumi['name'];
                }
                else
                {
                    $list[$i]['bangumi_name'] = '';
                }

                $list[$i] = json_encode($list[$i]);
            }

            return $list;
        });

        $result = [];
        foreach ($list as $item)
        {
            $result[] = json_decode($item, true);
        }

        return $result;
    }

    public function uploadImageTypes()
    {
        return $this->Cache('upload-image-types', function ()
        {
            $size = Tag::where('model', '2')->select('name', 'id')->get();
            $tags = Tag::where('model', '1')->select('name', 'id')->get();

            return [
                'size' => $size,
                'tags' => $tags
            ];
        }, 'm');
    }

    public function albumImages($albumId, $imageIds)
    {
        return $this->Cache('image_album_' . $albumId . '_' . $imageIds, function () use ($imageIds)
        {
            $ids = explode(',', $imageIds);

            return Image::whereIn('id', $ids)
                ->whereIn('state', [1, 2])
                ->get()
                ->toArray();
        });
    }

    public function getRoleImageIds($roleId, $seen, $take, $size, $tags, $creator, $sort)
    {
        return Image::whereIn('state', [1, 4])
            ->whereRaw('role_id = ? and image_count <> ?', [$roleId, 1])
            ->whereNotIn('images.id', $seen)
            ->take($take)
            ->when($sort === 'new', function ($query)
            {
                return $query->latest();
            }, function ($query)
            {
                return $query->orderBy('like_count', 'DESC');
            })
            ->when($creator !== -1, function ($query) use ($creator)
            {
                return $query->where('creator', $creator);
            })
            ->when($size, function ($query) use ($size)
            {
                return $query->where('size_id', $size);
            })
            ->when($tags, function ($query) use ($tags)
            {
                return $query->leftJoin('image_tags AS tags', 'images.id', '=', 'tags.image_id')
                    ->where('tags.tag_id', $tags);
            })
            ->pluck('images.id');
    }
}