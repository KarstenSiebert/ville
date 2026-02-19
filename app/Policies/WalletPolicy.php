<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Access\HandlesAuthorization;

class WalletPolicy
{
    use HandlesAuthorization;

    /**
     * Darf der User diese Wallet sehen?
     */
    public function view(User $actor, Wallet $wallet): bool
    {
        // Eigene Wallet
        if ($wallet->user_id === $actor->id) {
            return true;
        }
        
        return true;        
    }

    /**
     * Darf der User aus dieser Wallet abbuchen?
     */
    public function debit(User $actor, Wallet $wallet): bool
    {
        // Nur Wallet-Besitzer oder Parent
        if (!$this->view($actor, $wallet)) {
            return false;
        }
        
        return true;
    }

    /**
     * Darf der User diese Wallet gutschreiben?
     */

    public function credit(User $actor, Wallet $wallet): bool
    {
        // Sichtbarkeit zuerst
        if (!$this->view($actor, $wallet)) {
            return false;
        }
        
        return true;
    }

    /**
     * Darf der User diesen Wallet-Typ erstellen?
     */
    public function create(User $actor, string $type): bool
    {
        return in_array($type, ['deposit', 'available', 'reserved']);
    }

}
