<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Market;
use Illuminate\Auth\Access\HandlesAuthorization;

class MarketPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $actor, Market $market): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $actor, Market $market): bool
    {        
        return (($actor->id === $market->user_id) && ($actor->id === $market->publisher_id)) || $actor->hasRole('admin');
    }
    
    public function delete(User $actor, Market $market): bool
    {
        return $actor->hasRole('admin') === true;
    }

    public function cancel(User $actor, Market $market): bool
    {
        return $actor->hasRole('admin') === true;
    }

    public function resolve(User $actor, Market $market): bool
    {
        return ($actor->hasRole('admin') || $actor->hasRole('trusted') || $actor->hasRole('publisher')) === true;
    }

    public function settle(User $actor, Market $market): bool
    {
        return $actor->hasRole('admin') === true;
    }

    public function close(User $actor, Market $market): bool
    {
        return ($actor->hasRole('admin') || $actor->hasRole('trusted') || $actor->hasRole('publisher')) === true;
    }

    public function admin(User $actor)
    {
        return $actor->hasRole('admin') === true;        
    }

}
