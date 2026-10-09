<?php

declare(strict_types=1);

namespace Imagony\Diagnostics;

use Imagony\Converters\Format;

/**
 * Knows what the running PHP is able to do with images and can explain what is missing and how to fix it.
 * It is the only place which looks at the PHP extensions, everything else (the factory, exceptions, the check command) asks it.
 */
final class Environment {
	/** Drivers in order of preference. */
	public const DRIVERS = ["imagick", "gd"];

	private static ?self $current = null;

	/** @var array<string, DriverInfo> */
	private array $drivers = [];

	/**
	 * @param list<DriverInfo>|null $drivers Describes the drivers, the running PHP is probed when null.
	 */
	public function __construct(?array $drivers = null) {
		foreach($drivers ?? self::probe() as $driver) {
			$this->drivers[$driver->name] = $driver;
		}
	}

	/**
	 * Environment of the running PHP.
	 */
	public static function current(): self {
		return self::$current ??= new self();
	}

	/**
	 * Replaces the shared environment, mainly for tests. Null resets it to the running PHP.
	 */
	public static function setCurrent(?self $environment): void {
		self::$current = $environment;
	}

	/**
	 * @return list<DriverInfo>
	 */
	public static function probe(): array {
		$imagick = extension_loaded("imagick") && class_exists(\Imagick::class);
		$imageMagick = $imagick ? self::imageMagickVersion() : null;
		$gd = extension_loaded("gd");
		$info = $gd ? gd_info() : [];

		return [
			new DriverInfo(
				"imagick",
				$imagick,
				$imagick ? phpversion("imagick").($imageMagick ? " (ImageMagick {$imageMagick})" : "") : null,
				$imagick ? [
					Format::WEBP->value => in_array("WEBP", \Imagick::queryFormats("WEBP"), true),
					Format::AVIF->value => in_array("AVIF", \Imagick::queryFormats("AVIF"), true),
				] : []
			),
			new DriverInfo(
				"gd",
				$gd,
				$gd ? ($info["GD Version"] ?? null) : null,
				$gd ? [
					Format::WEBP->value => function_exists("imagewebp") && !empty($info["WebP Support"]),
					Format::AVIF->value => function_exists("imageavif") && !empty($info["AVIF Support"]),
				] : []
			),
		];
	}

	private static function imageMagickVersion(): ?string {
		try {
			return preg_match('/ImageMagick\s+(\S+)/', \Imagick::getVersion()["versionString"] ?? "", $matches) ? $matches[1] : null;
		} catch(\Throwable) {
			return null;
		}
	}

	/**
	 * @return list<DriverInfo> In order of preference.
	 */
	public function drivers(): array {
		$result = [];
		foreach(self::DRIVERS as $name) {
			$result[] = $this->drivers[$name] ?? new DriverInfo($name, false);
		}
		return $result;
	}

	/**
	 * Name of the preferred available driver.
	 */
	public function firstAvailable(): ?string {
		foreach($this->drivers() as $driver) {
			if($driver->available) {
				return $driver->name;
			}
		}
		return null;
	}

	/**
	 * Name of the preferred driver which can write the format.
	 */
	public function firstSupporting(Format $format): ?string {
		foreach($this->drivers() as $driver) {
			if($driver->supports($format)) {
				return $driver->name;
			}
		}
		return null;
	}

	/**
	 * Explains what is wrong and how to fix it. Without a format, it explains missing drivers.
	 */
	public function explain(?Format $format = null): string {
		if($this->firstAvailable() === null) {
			return "No PHP image extension is available. Install ext-gd or ext-imagick.\n".
				" - GD: `apt-get install php-gd`, in Docker `docker-php-ext-install gd`.\n".
				" - Imagick: `pecl install imagick` or `apt-get install php-imagick`.";
		}

		if($format === null) {
			return "";
		}

		$name = strtoupper($format->value);
		$lines = ["{$name} can not be written by any available PHP image driver:"];
		foreach($this->drivers() as $driver) {
			$lines[] = " - ".$this->reason($driver, $format);
		}

		return implode("\n", $lines);
	}

	private function reason(DriverInfo $driver, Format $format): string {
		$name = strtoupper($format->value);
		$version = $driver->version ? " {$driver->version}" : "";

		if($driver->name === "imagick") {
			if(!$driver->available) {
				return "Imagick: the extension is not installed. Install ext-imagick (`pecl install imagick`) with an ImageMagick which can write {$name} (".match($format) {
					Format::WEBP => "libwebp delegate",
					Format::AVIF => "libheif/libavif delegate",
				}.").";
			}

			return "Imagick{$version}: installed, but its ImageMagick can not write {$name}. Rebuild or upgrade ImageMagick with the ".match($format) {
				Format::WEBP => "libwebp delegate (Debian: `libwebp-dev`)",
				Format::AVIF => "libheif/libavif delegate (Debian: `libheif-dev`, `libavif-dev`)",
			}.".";
		}

		if(!$driver->available) {
			return "GD: the extension is not loaded. Install ext-gd (`docker-php-ext-install gd`, or `apt-get install php-gd`).";
		}

		return "GD{$version}: built without {$name}. Rebuild PHP's GD with ".match($format) {
			Format::WEBP => "`--with-webp` (Debian: `libwebp-dev`; Docker: `docker-php-ext-configure gd --with-webp`)",
			Format::AVIF => "`--with-avif` (Debian: `libavif-dev`; Docker: `docker-php-ext-configure gd --with-avif`)",
		}.".";
	}
}
