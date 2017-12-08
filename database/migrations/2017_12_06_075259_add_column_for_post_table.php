<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddColumnForPostTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->string('title')->default('');
            $table->text('content');
            $table->text('images');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bangumi_id');
            $table->unsignedInteger('parent_id')->default(0);
            $table->unsignedInteger('floor_count')->default(1);
            $table->unsignedInteger('comment_count')->default(0);
            $table->unsignedInteger('like_count')->default(0);
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('posts', function (Blueprint $table) {
            $table->dropColumn('title');
            $table->dropColumn('content');
            $table->dropColumn('images');
            $table->dropColumn('user_id');
            $table->dropColumn('bangumi_id');
            $table->dropColumn('parent_id');
            $table->dropColumn('floor_count');
            $table->dropColumn('comment_count');
            $table->dropColumn('like_count');
            $table->dropColumn('deleted_at');
        });
    }
}
