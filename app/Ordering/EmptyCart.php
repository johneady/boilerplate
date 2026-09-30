<?php

namespace App\Ordering;

use RuntimeException;

/**
 * Thrown when an order is placed with nothing orderable in the cart.
 */
class EmptyCart extends RuntimeException {}
