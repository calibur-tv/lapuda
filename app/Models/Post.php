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

    protected $table = 'posts';

    protected $fillable = [
        'title',            // 帖子标题，只有 1 楼才有标题,
        'content',          // 帖子内容，富文本
        'user_id',          // 帖子作者的 id
        'bangumi_id',       // 帖子所属番剧的 id
        'parent_id',        // 如果帖子不是 1 楼，则 parent_id 是一楼的 id，否则就是 0
        'floor_count',      // 楼层，默认为 1 楼
        'comment_count',    // 如果是 1 楼，就是回帖数量，否则就是回复数量
        'like_count'        // 喜欢或点赞的数量
    ];

    protected $casts = [
        'bangumi_id' => 'integer',
        'floor_count' => 'integer',
        'user_id' => 'integer',
        'like_count' => 'integer',
        'comment_count' => 'integer',
        'parent_id' => 'integer'
    ];

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
}