<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateBangumiSeasonsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bangumi_seasons', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('bangumi_id')->comment('番剧 id');
            $table->string('name')->comment('季度名称');
            $table->integer('rank')->comment('季度顺序');
            $table->string('summary')->comment('简介');
            $table->string('avatar')->comment('头像');
            $table->text('videos')->comment('视频 id');
            $table->tinyInteger('other_site_video');
            $table->integer('released_at')->default(0)->comment('第几周更新，0代表不连载');
            $table->integer('released_time')->default(0)->comment('上次更新时间');
            $table->tinyInteger('end')->comment('完结 0-未完结 1-已完结');
            $table->timestamp('published_at')->comment('放送开始');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('bangumi_seasons');
    }
}
