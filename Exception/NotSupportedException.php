<?php

namespace Omnipulse\Exception;

use Omnipulse\ProviderInterface;

/**
 * A provider asked for what it cannot do: a dimension it does not report,
 * search queries from an analytics tool, a reply on Tripadvisor's Content
 * API, the sponsors of a platform that keeps them private. Its
 * Capabilities say so beforehand.
 */
final class NotSupportedException extends \LogicException implements OmnipulseException
{
    public static function capability(object $provider, string $name, string $capability): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s.', $name, $provider::class, $capability));
    }

    /** A typed getter of the Registry asked for a contract the provider does not give as configured. */
    public static function question(ProviderInterface $provider, string $name, string $question): self
    {
        return new self(\sprintf('The "%s" provider (%s) does not %s; it gives: %s.', $name, $provider->getTitle(), $question, implode(', ', $provider->capabilities()->contracts()) ?: 'nothing'));
    }

    /** A provider asked for an operation it cannot do as configured: replying without the owner's access. */
    public static function operation(string $provider, string $operation, string $why = ''): self
    {
        return new self(\sprintf('The "%s" provider does not %s%s.', $provider, $operation, '' !== $why ? ': '.$why : ''));
    }
}
