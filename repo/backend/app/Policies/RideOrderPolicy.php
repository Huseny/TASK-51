<?php

namespace App\Policies;

use App\Models\RideOrder;
use App\Models\User;

class RideOrderPolicy
{
    public function view(User $user, RideOrder $order): bool
    {
        return $user->id === $order->rider_id || $user->role === 'admin';
    }

    public function cancel(User $user, RideOrder $order): bool
    {
        return $user->id === $order->rider_id
            && in_array($order->status, ['matching', 'accepted'], true);
    }
}
