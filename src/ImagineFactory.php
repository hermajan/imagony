<?php

declare(strict_types=1);

namespace Imagony;

use Imagine\Gd\Imagine as GdImagine;
use Imagine\Image\ImagineInterface;
use Imagine\Imagick\Imagine as ImagickImagine;
use Imagony\Converters\Format;
use Imagony\Diagnostics\Environment;
use Imagony\Exception\{NoDriverException, UnsupportedFormatException};

/**
 * Provides the Imagine driver: Imagick when available (better AVIF/WebP support), GD otherwise.
 * When a format is requested, the first driver which can write it is used.
 */
final class ImagineFactory {
	/** @var array<string, ImagineInterface> */
	private static array $drivers = [];

	private function __construct() {
	}

	/**
	 * @param Format|null $format Format which the driver has to be able to write.
	 * @throws NoDriverException If neither GD nor Imagick is available.
	 * @throws UnsupportedFormatException If no driver can write the format.
	 */
	public static function create(?Format $format = null): ImagineInterface {
		$environment = Environment::current();
		$driver = $format === null ? $environment->firstAvailable() : $environment->firstSupporting($format);

		if($driver === null) {
			self::fail($environment, $format);
		}

		return self::$drivers[$driver] ??= $driver === "imagick" ? new ImagickImagine() : new GdImagine();
	}

	/**
	 * Checks if any Imagine driver can write the format.
	 */
	public static function supportsFormat(Format $format): bool {
		return Environment::current()->firstSupporting($format) !== null;
	}

	/**
	 * Throws an explaining exception when no driver can write the format.
	 * @throws NoDriverException
	 * @throws UnsupportedFormatException
	 */
	public static function assertSupports(Format $format): void {
		$environment = Environment::current();
		if($environment->firstSupporting($format) === null) {
			self::fail($environment, $format);
		}
	}

	private static function fail(Environment $environment, ?Format $format): never {
		if($format === null or $environment->firstAvailable() === null) {
			throw NoDriverException::create($environment);
		}

		throw UnsupportedFormatException::create($format, $environment);
	}
}
