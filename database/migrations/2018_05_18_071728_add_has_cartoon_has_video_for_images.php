<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddHasCartoonHasVideoForImages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bangumis', function (Blueprint $table) {
            $table->boolean('has_cartoon')->default(false);
            $table->boolean('has_video')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bangumis', function (Blueprint $table) {
            $table->dropColumn('has_cartoon');
            $table->dropColumn('has_video');
        });
    }
}
