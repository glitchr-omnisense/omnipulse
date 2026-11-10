<?php

namespace Omnipulse\Exception;

use Omnisense\Contracts\Exception\ExceptionInterface;

/** Every exception omnipulse throws, whichever family the provider serves (and omnisense/contracts' interface, so one catch holds every family's). */
interface OmnipulseException extends ExceptionInterface
{
}
