<?php

namespace App\Services\Checkout;

use RuntimeException;

/** A checkout problem the customer can fix (shown as a form error). */
class CheckoutException extends RuntimeException {}
