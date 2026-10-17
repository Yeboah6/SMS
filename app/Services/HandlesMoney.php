<?php

namespace App\Services;

/** Money is compared in whole cents so floating point never decides whether a balance is zero. */
trait HandlesMoney
{
    protected function cents(string|int|float|null $amount): int
    {
        return (int) round(((float) ($amount ?? 0)) * 100);
    }

    protected function money(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }
}