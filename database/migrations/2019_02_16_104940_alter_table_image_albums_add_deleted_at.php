<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterTableImageAlbumsAddDeletedAt extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('image_albums', function (Blueprint $table) {
            $table->softDeletes();
            $table->bigInteger('rank')->unsigned()->comment('图片顺序')->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('image_albums', function (Blueprint $table) {
            $table->integer('rank')->unsigned()->comment('图片顺序')->change();
            $table->dropSoftDeletes();
        });
    }
}
