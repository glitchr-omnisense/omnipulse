<?php

namespace Omnipulse\Model;

use Omnipulse\Exception\InvalidConfigException;

/**
 * An amount in hundredths of its currency's unit, as the providers count it
 * (GitHub's monthlyPriceInCents, Open Collective's valueInCents,
 * Liberapay's "636.28"), and its ISO 4217 currency.
 */
final readonly class Money
{
    public string $currency;

    public function __construct(public int $cents, string $currency)
    {
        $currency = strtoupper(trim($currency));
        if (!preg_match('~^[A-Z]{3}$~', $currency)) {
            throw new InvalidConfigException(\sprintf('"%s" is not an ISO 4217 currency code.', $currency));
        }
        $this->currency = $currency;
    }

    /** From a decimal amount: "636.28", 636.28, 5. */
    public static function fromDecimal(string|int|float $amount, string $currency): self
    {
        if (!is_numeric($amount)) {
            throw new InvalidConfigException(\sprintf('"%s" is not an amount.', $amount));
        }

        return new self((int) round((float) $amount * 100), $currency);
    }

    /** "636.28" */
    public function decimal(): string
    {
        return ($this->cents < 0 ? '-' : '').intdiv(abs($this->cents), 100).'.'.str_pad((string) (abs($this->cents) % 100), 2, '0', \STR_PAD_LEFT);
    }

    public function multiply(float $factor): self
    {
        return new self((int) round($this->cents * $factor), $this->currency);
    }

    /** @return array{cents: int, currency: string, decimal: string} */
    public function toArray(): array
    {
        return ['cents' => $this->cents, 'currency' => $this->currency, 'decimal' => $this->decimal()];
    }
}
