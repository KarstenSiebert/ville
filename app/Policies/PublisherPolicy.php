<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Publisher;
use Illuminate\Auth\Access\HandlesAuthorization;

class PublisherPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $actor, Publisher $publisher): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $actor, Publisher $publisher): bool
    {        
        return true;
    }
    
    public function delete(User $actor, Publisher $publisher): bool
    {
        return $actor->hasRole('admin') === true;
    }    

    public function admin(User $actor)
    {
        return $actor->hasRole('admin') === true;        
    }

}
