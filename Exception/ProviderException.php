<?php

namespace Omnipulse\Exception;

/**
 * The provider answered, and not with what was asked: an error status, an
 * error in a GraphQL answer, an answer that is not JSON, a place or an
 * account it does not know. $provider and $providerCode name who answered
 * and the code it gave, when the provider's package sets them (from()).
 */
class ProviderException extends \RuntimeException implements OmnipulseException
{
    final public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly ?string $provider = null,
        /** The provider's own error code, when it gave one */
        public readonly ?string $providerCode = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    /** "Example answered 400 for the query: Invalid metric" - the status as the code when it is a number. */
    public static function answered(string $provider, string $what, int|string $status, ?string $message = null): static
    {
        return new static(\sprintf('%s answered %s for %s%s', $provider, (string) $status, $what, null !== $message ? ': '.$message : '.'), \is_int($status) ? $status : 0, null, $provider, (string) $status);
    }

    /** "[google] HTTP 400, INVALID_ARGUMENT: ..." - what the provider said, its own code kept. */
    public static function from(string $provider, string $message, ?string $providerCode = null, ?\Throwable $previous = null): static
    {
        return new static(\sprintf('[%s] %s', $provider, $message), 0, $previous, $provider, $providerCode);
    }
}
