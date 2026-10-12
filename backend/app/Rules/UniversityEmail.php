<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Translation\PotentiallyTranslatedString;

class UniversityEmail implements ValidationRule
{
    /**
     * Ensure the address belongs to one of the configured university domains.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $domains = config('unicircle.email_domains', []);

        $isUniversityAddress = is_string($value)
            && Str::endsWith(Str::lower($value), array_map(fn (string $domain): string => '@'.Str::lower($domain), $domains));

        if (! $isUniversityAddress) {
            $fail('The :attribute must be a university email address ('.implode(', ', array_map(fn (string $domain): string => '@'.$domain, $domains)).').');
        }
    }
}
