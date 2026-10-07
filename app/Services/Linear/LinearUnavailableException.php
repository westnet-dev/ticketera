<?php

namespace App\Services\Linear;

use RuntimeException;

/**
 * Linear could not answer: it is unreachable, the key is missing or rejected, or the query failed.
 */
class LinearUnavailableException extends RuntimeException {}
