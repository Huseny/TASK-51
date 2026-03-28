<?php

namespace App\Console\Commands;

use App\Models\RideOrder;
use App\Services\RideOrderStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoRevertNoShowRides extends Command
{
    protected $signature = 'ride:auto-revert-no-show';

    protected $description = 'Auto-revert accepted no-show rides to matching';

    public function __construct(private readonly RideOrderStateMachine $stateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orders = RideOrder::query()
            ->where('status', 'accepted')
            ->whereNull('started_at')
            ->where('accepted_at', '<', now()->subMinutes(5))
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            try {
                $this->stateMachine->transition($order, 'reassign', null, ['reason' => 'no_show_auto_revert']);
                $count++;
            } catch (\Throwable $exception) {
                Log::channel('app')->error('Failed auto-revert transition', [
                    'ride_order_id' => $order->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::channel('security')->warning(sprintf('Auto-reverted %d no-show accepted rides to matching', $count), [
            'count' => $count,
        ]);

        return self::SUCCESS;
    }
}
