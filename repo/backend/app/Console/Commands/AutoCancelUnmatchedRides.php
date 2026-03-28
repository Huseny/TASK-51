<?php

namespace App\Console\Commands;

use App\Models\RideOrder;
use App\Services\RideOrderStateMachine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AutoCancelUnmatchedRides extends Command
{
    protected $signature = 'ride:auto-cancel-unmatched';

    protected $description = 'Auto-cancel unmatched rides after 10 minutes';

    public function __construct(private readonly RideOrderStateMachine $stateMachine)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $orders = RideOrder::query()
            ->where('status', 'matching')
            ->where('created_at', '<', now()->subMinutes(10))
            ->get();

        $count = 0;

        foreach ($orders as $order) {
            try {
                $this->stateMachine->transition($order, 'cancel', null, ['reason' => 'auto_cancel_timeout']);
                $count++;
            } catch (\Throwable $exception) {
                Log::channel('app')->error('Failed auto-cancel transition', [
                    'ride_order_id' => $order->id,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        Log::channel('security')->warning(sprintf('Auto-canceled %d unmatched ride orders', $count), [
            'count' => $count,
        ]);

        return self::SUCCESS;
    }
}
