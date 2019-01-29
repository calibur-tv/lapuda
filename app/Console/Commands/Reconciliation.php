<?php

namespace App\Console\Commands;

use App\Models\LightCoinRecord;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class Reconciliation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'coin:reconciliation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $users = $this->getUserIds();

        foreach ($users as $user) {
            $records = $this->getRecords($user->id);

            /** @var Collection $record */
            foreach ($records as $record) {
                $count = $record->count();
                if (1 >= $count) {
                    continue;
                }

                for ($i = 1; $i < $count; $i++) {
                    $tableName = $this->getRewardTableName($record[$i]->to_product_type);
                    if (is_null($tableName)) {
                        continue;
                    }

                    \DB::beginTransaction();
                    try {
                        $this->reconciliation($record[$i]->from_user_id, $record[$i]->to_user_id, $record[$i]->to_product_id, $record[$i]->coin_id, $tableName, $record[$i]->id);

                        $update = [];
                        $previous = \DB::table('light_coin_records')->where('coin_id', $record[$i]->coin_id)->where('to_user_id', $record[$i]->from_user_id)->first();
                        if (!is_null($previous)) {
                            if (0 == $previous->from_user_id || $previous->created_at < Carbon::createFromFormat('Y-m-d H:i:s', '2019-01-20 01:31:19')) {
                                $update['state'] = 1;
                            }
                        }

                        $update['holder_id'] = $record[$i]->from_user_id;
                        $update['holder_type'] = 1;
                        $res = \DB::table('light_coins')->where('id', $record[$i]->coin_id)->update($update);
                        if (0 == $res) {
                            throw new Exception();
                        }

                        \DB::commit();
                    } catch (Exception $e) {
                        \DB::rollBack();
                    }
                }
            }
        }
    }

    private function reconciliation($fromUserId, $toUserId, $toProductId, $coinId, $rewardTableName, $recordId)
    {
        if (!is_null($rewardTableName)) {
            if ('cartoon_role_fans' == $rewardTableName) {
                $role = \DB::table($rewardTableName)->where('role_id', $toProductId)->first();
                if (!is_null($role)) {
                    $res = \DB::table($rewardTableName)->where('id', $role->id)->update([
                        'star_count' => $role->star_count - 1,
                    ]);

                    if (0 == $res) {
                        throw new \Exception();
                    }
                }
            } else {
                $reward = \DB::table($rewardTableName)->where('modal_id', $toProductId)->where('user_id', $fromUserId)->first();
                if (!is_null($reward)) {
                    $res = \DB::table($rewardTableName)->where('id', $reward->id)->delete();
                    if (0 == $res) {
                        throw new \Exception();
                    }
                }
            }
        }

        $res = \DB::table('light_coin_records')->where('id', $recordId)->delete();
        if (0 == $res) {
            throw new \Exception();
        }

        $record = \DB::table('light_coin_records')->where('from_user_id', $toUserId)->where('coin_id', $coinId)->first();
        if (is_null($record)) {
            return;
        }

        $tableName = $this->getRewardTableName($record->to_product_type);
        $this->reconciliation($record->from_user_id, $record->to_user_id, $record->to_product_id, $record->coin_id, $tableName, $record->id);
    }

    private function getUserIds()
    {
        \DB::enableQueryLog();
        $users = \DB::table('users')->get(['id']);
        return $users;
    }

    private function getRecords($fromUserId)
    {
        $records = \DB::table('light_coin_records')->where('from_user_id', $fromUserId)->where('to_user_id', '<>', 0)->groupBy([
            'to_user_id', 'to_product_id', 'to_product_type',
        ])->havingRaw('count(*) > 1')->get(['to_user_id', 'to_product_id', 'to_product_type'])->toArray();
        $toUserId = [];
        $toProductId = [];
        $toProductType = [];
        array_walk($records, function($value) use (&$toProductId, &$toProductType, &$toUserId) {
            $toUserId[] = $value->to_user_id;
            $toProductId[] = $value->to_product_id;
            $toProductType[] = $value->to_product_type;
        });
        $records = \DB::table('light_coin_records')->where('from_user_id', $fromUserId)->whereIn('to_user_id', $toUserId)->whereIn('to_product_id', $toProductId)->whereIn('to_product_type', $toProductType)->get();
        $records = $records->groupBy(function ($item) {
            return sprintf("%s_%s_%s", $item->to_user_id, $item->to_product_id, $item->to_product_type);
        });

        return $records;
    }

    private function getRewardTableName($type)
    {
        switch ($type) {
            case 4:
                return 'post_reward';
            case 5:
                return 'image_reward';
            case 6:
                return 'score_reward';
            case 7:
                return 'answer_reward';
            case 8:
                return 'video_reward';
            case 9:
                return 'cartoon_role_fans';
            default:
                return null;
        }
    }
}
