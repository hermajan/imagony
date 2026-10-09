<?php

declare(strict_types=1);

namespace Imagony\Exception;

use Imagony\Diagnostics\Environment;

/**
 * Neither GD nor Imagick is available in PHP.
 */
class NoDriverException extends \RuntimeException implements ImagonyException {
	public static function create(Environment $environment): self {
		return new self($environment->explain());
	}
}
