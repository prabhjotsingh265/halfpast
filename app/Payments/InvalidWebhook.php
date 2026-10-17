<?php

namespace App\Payments;

use RuntimeException;

/** A webhook whose signature doesn't check out. Never acted on. */
class InvalidWebhook extends RuntimeException {}
