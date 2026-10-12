<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts any reasonable spelling of an Indonesian mobile number and rejects
 * placeholders such as "000000", which used to be stored verbatim and made both
 * the receivables list and the customer history useless.
 */
class IndonesianPhoneNumber implements ValidationRule
{
    public function __construct(
        private readonly bool $required = true,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            if ($this->required) {
                $fail('Nomor WhatsApp wajib diisi.');
            }

            return;
        }

        if (! is_string($value)) {
            $fail('Nomor WhatsApp tidak valid.');

            return;
        }

        if (! PhoneNumber::isValid($value)) {
            $fail('Nomor WhatsApp tidak valid. Contoh: 081234567890');
        }
    }
}
