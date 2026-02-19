<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transfer;
use Illuminate\Support\Facades\Gate;
use Illuminate\Auth\Access\HandlesAuthorization;

class TransferPolicy
{
    use HandlesAuthorization;

    /**
     * Darf der User einen Transfer anlegen?
     */
    public function create(
        User $actor,
        Wallet $from,
        Wallet $to,
        string $type
    ): bool {
        
        // WalletPolicy erzwingen
        if (
            !Gate::allows('debit', $from) ||
            !Gate::allows('credit', $to)
        ) {
            return false;
        }
        
        return true;
    }

    /**
     * Darf der User diesen Transfer ausführen?
     */
    public function execute(User $actor, Transfer $transfer): bool
    {
        // Status-Check
        if ($transfer->status !== 'pending') {
            return false;
        }

        // Wallets neu prüfen (Race-Condition-Schutz)
        return Gate::allows('debit', $transfer->fromWallet)
            && Gate::allows('credit', $transfer->toWallet);
    }

    /**
     * Darf der User diesen Transfer sehen?
     */
    public function view(User $actor, Transfer $transfer): bool
    {        
        return true;            
    }

    /**
     * Darf der User den Transfer abbrechen?
     */
    public function cancel(User $actor, Transfer $transfer): bool
    {
        return true;            
    }
    
}
