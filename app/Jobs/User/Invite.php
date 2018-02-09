<?php

namespace App\Jobs\User;

use App\Api\V1\Repositories\UserRepository;
use App\Models\MixinSearch;
use App\Models\User;
use App\Services\Sms\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class Invite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $inviteCode;

    protected $inviteUserId;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($uid, $code)
    {
        $this->inviteUserId = $uid;
        $this->inviteCode = $code;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $inviteUser = User::where('id', $this->convertInviteCode($this->inviteCode, false))->select('id', 'phone', 'nickname')->first();
        if ($inviteUser)
        {
            $userRepository = new UserRepository();
            $userRepository->toggleCoin(false, $this->inviteUserId, $inviteUser->id, 2, 0);

            $newUser = $userRepository->item($this->inviteUserId);

            $sms = new Message();
            $sms->inviteUser($inviteUser->phone, $inviteUser->nickname, $newUser['nickname']);

            $searchId = MixinSearch::whereRaw('modal_id = ? and type_id = ?', [$inviteUser->id, 1])
                ->pluck('id')
                ->first();

            if (!is_null($searchId))
            {
                MixinSearch::where('id', $searchId)->increment('score');
                MixinSearch::where('id', $searchId)->update([
                    'updated_at' => time()
                ]);
            }
        }
    }

    private function convertInviteCode($id, $convert = true)
    {
        return $convert
            ? base_convert($id * 1000 + rand(0, 999), 10, 36)
            : intval(base_convert($id, 36, 10) / 1000);
    }
}
