<?php

namespace Omnipulse\Exception;

/**
 * The provider could not be reached: a network error, a timeout - and, for
 * a review platform, a server error or a quota exceeded (it said nothing
 * about the request). Show nothing rather than something stale beyond what
 * the platform allows. Not a ProviderException: nothing was answered.
 */
final class UnreachableException extends \RuntimeException implements OmnipulseException
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $provider = null,
        /** The status, for a server error or a quota exceeded */
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** "[google] No answer: ..." */
    public static function from(string $provider, string $message, ?string $providerCode = null, ?\Throwable $previous = null): self
    {
        return new self(\sprintf('[%s] %s', $provider, $message), 0, $previous, $provider, $providerCode);
    }
}
