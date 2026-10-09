<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Imagony\Diagnostics\{DriverInfo, Environment};

/**
 * Fake PHP installations, so the tests do not depend on the extensions which the machine has.
 */
final class Environments {
	private function __construct() {
	}

	public static function gd(bool $webp = true, bool $avif = false, string $version = "2.1.0"): DriverInfo {
		return new DriverInfo("gd", true, $version, ["webp" => $webp, "avif" => $avif]);
	}

	public static function imagick(bool $webp = true, bool $avif = true, string $version = "3.7.0 (ImageMagick 7.1.1)"): DriverInfo {
		return new DriverInfo("imagick", true, $version, ["webp" => $webp, "avif" => $avif]);
	}

	public static function of(DriverInfo ...$drivers): Environment {
		return new Environment(array_values($drivers));
	}

	public static function none(): Environment {
		return new Environment([]);
	}
}
