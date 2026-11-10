<?php

namespace Omnipulse\Exception;

/** A provider asked for with options it cannot work with, or not configured at all: an option missing, an unknown factory, an amount in no currency. */
final class InvalidConfigException extends \InvalidArgumentException implements OmnipulseException
{
}
