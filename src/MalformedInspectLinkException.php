<?php

declare(strict_types=1);

namespace VlyDev\Steam;

/**
 * Thrown by {@see InspectLink::deserialize()} when the input cannot be a valid
 * inspect-link payload — odd-length hex, non-hex characters, payload shorter
 * than the minimum, or proto bytes that fail to parse cleanly.
 *
 * Extends \InvalidArgumentException for backwards compatibility with callers
 * that catch the parent class.
 */
class MalformedInspectLinkException extends \InvalidArgumentException
{
}
