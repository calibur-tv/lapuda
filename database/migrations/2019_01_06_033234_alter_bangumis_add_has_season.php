<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AlterBangumisAddHasSeason extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bangumis', function (Blueprint $table) {
            $table->tinyInteger('has_season')->after('season')->default(0)->comment('是否分季度 0-不分 1-分');
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
            $table->dropColumn('has_season');
        });
    }
}
