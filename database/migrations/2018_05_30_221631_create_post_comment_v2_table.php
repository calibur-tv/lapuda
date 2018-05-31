<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePostCommentV2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('post_comments_v2', function (Blueprint $table) {
            $table->engine = 'MyISAM';
            $table->unsignedInteger('id');
            $table->unsignedInteger('modal_id')->default(0);
            $table->unsignedInteger('parent_id')->default(0);
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('to_user_id')->default(0);
            $table->text('content');
            $table->unsignedInteger('state')->default(0);
            $table->unsignedInteger('comment_count')->default(0);
            $table->softDeletes();
            $table->timestamps();
            $table->primary(['modal_id', 'id']);
        });

        \Illuminate\Support\Facades\DB::statement('ALTER TABLE post_comments_v2 MODIFY id INTEGER NOT NULL AUTO_INCREMENT');
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('post_comments_v2');
    }
}
