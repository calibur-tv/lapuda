<?php

namespace App\Jobs\User;

use App\Api\V1\Repositories\UserRepository;
use App\Api\V1\Services\LightCoinService;
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
        $inviter = User
            ::where('id', $this->inviteCode)
            ->select('id', 'phone', 'nickname', 'faker')
            ->first();

        /*
        if (
            $inviter &&
            $inviter->phone &&
            // preg_match('/^(13[0-9]|15[012356789]|166|17[3678]|18[0-9]|14[57])[0-9]{8}$/', $inviter->phone) &&
            !intval($inviter->faker)
        )
        {
            $lightCoinService = new LightCoinService();
            $result = $lightCoinService->inviteUser($inviter->id, $this->inviteUserId);
            if (!$result)
            {
                return;
            }

            $userRepository = new UserRepository();
            $newUser = $userRepository->item($this->inviteUserId);

            $sms = new Message();
            $sms->inviteUser($inviter->phone, $inviter->nickname, $newUser['nickname']);
        }
        */
    }
}
