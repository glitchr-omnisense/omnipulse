<?php

namespace Omnipulse\Model;

/** How often an amount is given: once, or every week, month, year. */
enum Period: string
{
    case Once = 'once';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    /** How many of this period make a month (52 weeks a year); null for a one-time amount. */
    public function perMonth(): ?float
    {
        return match ($this) {
            self::Once => null,
            self::Week => 52 / 12,
            self::Month => 1.0,
            self::Year => 1 / 12,
        };
    }
}
