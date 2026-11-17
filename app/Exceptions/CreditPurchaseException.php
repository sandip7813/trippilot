<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a credit pack purchase cannot be started or confirmed. The
 * message is safe to show to the user.
 */
class CreditPurchaseException extends RuntimeException {}
