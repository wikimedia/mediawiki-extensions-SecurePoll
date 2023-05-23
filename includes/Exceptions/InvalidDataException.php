<?php

namespace MediaWiki\Extension\SecurePoll\Exceptions;

use RuntimeException;

/**
 * Exception class for a validation error when reading from stored election
 * properties.
 */
class InvalidDataException extends RuntimeException {
}
