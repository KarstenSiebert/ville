<?php

namespace App\Policies;

use App\Models\User;
use App\Models\MarketLimitOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarketLimitOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $actor, MarketLimitOrder $order): bool
    {   
        return ($actor->id === $order->user_id) || $actor->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $actor, MarketLimitOrder $order): bool
    {        
        return true;
    }
    
    public function delete(User $actor, MarketLimitOrder $order): bool
    {
        return ($actor->id === $order->user_id) || $actor->hasRole('admin');
    }

    public function cancel(User $actor, MarketLimitOrder $order): bool
    {
        return ($actor->id === $order->user_id) || $actor->hasRole('admin');
    }
    
    public function close(User $actor, Market $market): bool
    {
        return ($actor->id === $order->user_id) || $actor->hasRole('admin');
    }

    public function admin(User $actor)
    {
        return $actor->hasRole('admin') === true;        
    }

}
