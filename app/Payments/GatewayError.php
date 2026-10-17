<?php

namespace App\Payments;

use RuntimeException;

/** The gateway refused or couldn't be reached. Safe to show a general message and retry. */
class GatewayError extends RuntimeException {}
