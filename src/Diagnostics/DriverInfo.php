<?php

declare(strict_types=1);

namespace Imagony\Diagnostics;

use Imagony\Converters\Format;

/**
 * What one Imagine driver (PHP extension) can do in the running PHP.
 */
final readonly class DriverInfo {
	/**
	 * @param string $name `imagick` or `gd`.
	 * @param bool $available Is the extension loaded?
	 * @param string|null $version Version of the extension (or of the library behind it).
	 * @param array<string, bool> $formats Can the driver write the format? Keyed by {@see Format::$value}.
	 */
	public function __construct(
		public string $name,
		public bool $available,
		public ?string $version = null,
		public array $formats = [],
	) {
	}

	public function supports(Format $format): bool {
		return $this->available and ($this->formats[$format->value] ?? false);
	}
}
