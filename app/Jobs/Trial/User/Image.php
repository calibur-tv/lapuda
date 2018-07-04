<?php

namespace App\Jobs\Trial\User;

use App\Models\User;
use App\Services\Trial\ImageFilter;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\DB;

class Image implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $userId;
    protected $type;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id, $type)
    {
        $this->userId = $id;
        $this->type = $type;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $user = User::find($this->userId);

        if (is_null($user))
        {
            return;
        }

        $user = $user->toArray();
        $url = $user[$this->type];

        $imageFilter = new ImageFilter();
        if ($imageFilter->bad($url))
        {
            DB::table('users')
                ->where('id', $this->userId)
                ->update([
                    'state' => 1
                ]);
        }
    }
}
