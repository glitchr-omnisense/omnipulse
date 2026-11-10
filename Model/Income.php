<?php

namespace Omnipulse\Model;

/** What an account receives, every period: Liberapay counts it by the week, GitHub by the month. */
final readonly class Income
{
    public function __construct(public Money $amount, public Period $period)
    {
    }

    /** The same income by the month (a week is 52/12 of a month); null for a one-time amount. */
    public function monthly(): ?Money
    {
        $factor = $this->period->perMonth();

        return null === $factor ? null : $this->amount->multiply($factor);
    }

    /** @return array{amount: array{cents: int, currency: string, decimal: string}, period: string} */
    public function toArray(): array
    {
        return ['amount' => $this->amount->toArray(), 'period' => $this->period->value];
    }
}
