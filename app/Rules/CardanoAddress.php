<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class CardanoAddress implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!str_starts_with($value, 'addr1')) {
            $fail(__('the_address_must_start_with_addr1'));
            return;
        }

        $dataPart = substr($value, str_starts_with($value, 'addr_test1') ? 10 : 5);

        if (!preg_match('/^[qpzry9x8gf2tvdw0s3jn54khce6mua7l]+$/i', $dataPart)) {
            $fail(__('the_address_contains_invalid_characters'));            
        }
    }
}
