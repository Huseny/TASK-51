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

    public function accept(User $user, RideOrder $order): bool
    {
        return $user->role === 'driver' && $order->status === 'matching';
    }

    public function driverAction(User $user, RideOrder $order): bool
    {
        return ($user->role === 'driver' && $order->driver_id === $user->id)
            || $user->role === 'admin';
    }

    public function fleetView(User $user, RideOrder $order): bool
    {
        return in_array($user->role, ['fleet_manager', 'admin'], true);
    }

    public function fleetManage(User $user, RideOrder $order): bool
    {
        if (! in_array($user->role, ['fleet_manager', 'admin'], true)) {
            return false;
        }

        return ! in_array($order->status, ['completed', 'canceled'], true);
    }
}
