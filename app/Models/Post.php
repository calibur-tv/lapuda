<?php
/**
 * Created by PhpStorm.
 * User: yuistack
 * Date: 2017/12/6
 * Time: 下午8:44
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Post extends Model
{
    use SoftDeletes;

    const IMAGE = 1;
    const VOTE  = 1 << 1;

    protected $table = 'posts';

    protected $fillable = [
        'title', // 帖子标题，只有 1 楼才有标题,
        'user_id', // 帖子作者的 id
        'bangumi_id', // 帖子所属番剧的 id
        'content', // 帖子内容，富文本
        'desc', // content 的纯文本，最多 200 个字
        'state', // 帖子状态，0 正常
        'floor_count', // 楼层数
        'view_count',
        'is_nice',
        'top_at',
        'is_creator'
    ];

    protected $casts = [
        'state' => 'integer',
        'is_nice' => 'integer',
        'is_creator' => 'integer',
        'floor_count' => 'integer'
    ];

    public function getUpdatedAtColumn()
    {
        return null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bangumi()
    {
        return $this->belongsTo(Bangumi::class);
    }

    public function images()
    {
        return $this->hasMany(PostImages::class);
    }

    public function vote()
    {
        if ($this->items & self::VOTE) {
            return $this->hasOne(Vote::class);
        } else {
            return null;
        }
    }
}
