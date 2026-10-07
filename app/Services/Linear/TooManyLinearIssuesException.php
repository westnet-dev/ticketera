<?php

namespace App\Services\Linear;

/**
 * An admin created more Linear issues than the per-minute limit allows.
 */
final class TooManyLinearIssuesException extends LinearUnavailableException {}
