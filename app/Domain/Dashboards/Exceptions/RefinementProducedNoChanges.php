<?php

namespace App\Domain\Dashboards\Exceptions;

use RuntimeException;

/**
 * The model returned a valid spec identical to the current one: nothing to propose.
 */
final class RefinementProducedNoChanges extends RuntimeException {}
