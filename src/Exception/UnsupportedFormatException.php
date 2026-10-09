<?php

declare(strict_types=1);

namespace Imagony\Exception;

use Imagony\Converters\Format;
use Imagony\Diagnostics\Environment;

/**
 * A format is requested (e.g. in `imagony.thumbnails.formats`), but no available driver can write it.
 */
class UnsupportedFormatException extends \RuntimeException implements ImagonyException {
	public function __construct(public readonly Format $format, string $details, ?\Throwable $previous = null) {
		parent::__construct(
			$details."\nRemove ".strtoupper($format->value)." from `imagony.thumbnails.formats` or fix the PHP installation. ".
			"`bin/console imagony:check` shows what is available (needs symfony/console).",
			0,
			$previous
		);
	}

	public static function create(Format $format, Environment $environment): self {
		return new self($format, $environment->explain($format));
	}
}
