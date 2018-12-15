<?php

use Illuminate\Database\Seeder;

class VoteItemUserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = new \App\Models\VoteItemUser([
            'vote_item_id' => 1,
            'vote_id' => 1,
            'user_id' => 1,
        ]);

        $user->save();
    }
}
