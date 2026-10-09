<?php

declare(strict_types=1);

namespace Imagony\Tests\Diagnostics;

use Imagony\Converters\Format;
use Imagony\Diagnostics\{DriverInfo, Environment};
use Imagony\Tests\Environments;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase {
	public static function scenarios(): iterable {
		$gdNoAvif = Environments::of(Environments::gd(true, false));
		$imagickAvif = Environments::of(Environments::imagick(true, true), Environments::gd(true, false));
		$imagickNoAvif = Environments::of(Environments::imagick(true, false), Environments::gd(true, false));

		yield "GD without AVIF: WebP works" => [$gdNoAvif, Format::WEBP, "gd"];
		yield "GD without AVIF: AVIF does not" => [$gdNoAvif, Format::AVIF, null];
		yield "Imagick is preferred" => [$imagickAvif, Format::AVIF, "imagick"];
		yield "Imagick is preferred for WebP too" => [$imagickAvif, Format::WEBP, "imagick"];
		yield "Imagick without AVIF, GD without AVIF" => [$imagickNoAvif, Format::AVIF, null];
		yield "Imagick without AVIF, GD with AVIF" => [Environments::of(Environments::imagick(true, false), Environments::gd(true, true)), Format::AVIF, "gd"];
		yield "nothing installed" => [Environments::none(), Format::WEBP, null];
	}

	#[DataProvider("scenarios")]
	public function testFirstSupporting(Environment $environment, Format $format, ?string $expected): void {
		$this->assertSame($expected, $environment->firstSupporting($format));
	}

	public function testFirstAvailable(): void {
		$this->assertNull(Environments::none()->firstAvailable());
		$this->assertSame("gd", Environments::of(Environments::gd())->firstAvailable());
		$this->assertSame("imagick", Environments::of(Environments::gd(), Environments::imagick())->firstAvailable());
	}

	public function testDriversAreAlwaysDescribedInOrderOfPreference(): void {
		$drivers = Environments::of(Environments::gd())->drivers();

		$this->assertSame(["imagick", "gd"], array_map(fn(DriverInfo $driver) => $driver->name, $drivers));
		$this->assertFalse($drivers[0]->available);
		$this->assertTrue($drivers[1]->available);
	}

	public function testUnavailableDriverSupportsNothing(): void {
		$driver = new DriverInfo("imagick", false, null, ["webp" => true, "avif" => true]);

		$this->assertFalse($driver->supports(Format::WEBP));
	}

	public function testExplainsGdBuiltWithoutAvif(): void {
		$text = Environments::of(Environments::gd(true, false, "2.1.0"))->explain(Format::AVIF);

		$this->assertStringContainsString("AVIF can not be written", $text);
		$this->assertStringContainsString("GD 2.1.0: built without AVIF", $text);
		$this->assertStringContainsString("--with-avif", $text);
		$this->assertStringContainsString("libavif-dev", $text);
		$this->assertStringContainsString("Imagick: the extension is not installed", $text);
	}

	public function testExplainsGdBuiltWithoutWebp(): void {
		$text = Environments::of(Environments::gd(false, false))->explain(Format::WEBP);

		$this->assertStringContainsString("built without WEBP", $text);
		$this->assertStringContainsString("--with-webp", $text);
		$this->assertStringContainsString("libwebp-dev", $text);
	}

	public function testExplainsImagickWithoutAvifDelegate(): void {
		$text = Environments::of(Environments::imagick(true, false, "3.7.0 (ImageMagick 7.1.1)"), Environments::gd(true, false))->explain(Format::AVIF);

		$this->assertStringContainsString("Imagick 3.7.0 (ImageMagick 7.1.1): installed, but its ImageMagick can not write AVIF", $text);
		$this->assertStringContainsString("libheif", $text);
		$this->assertStringContainsString("GD 2.1.0: built without AVIF", $text);
	}

	public function testExplainsMissingDrivers(): void {
		$text = Environments::none()->explain();

		$this->assertStringContainsString("ext-gd or ext-imagick", $text);
		$this->assertStringContainsString("docker-php-ext-install gd", $text);
		$this->assertStringContainsString("pecl install imagick", $text);
	}

	public function testExplainWithoutFormatIsEmptyWhenDriverIsAvailable(): void {
		$this->assertSame("", Environments::of(Environments::gd())->explain());
	}

	public function testCurrentCanBeReplacedAndReset(): void {
		$fake = Environments::none();
		Environment::setCurrent($fake);
		try {
			$this->assertSame($fake, Environment::current());
		} finally {
			Environment::setCurrent(null);
		}

		$this->assertNotSame($fake, Environment::current());
	}

	public function testProbeDescribesTheRunningPhp(): void {
		$drivers = array_column(array_map(fn(DriverInfo $driver) => ["name" => $driver->name, "driver" => $driver], Environment::probe()), "driver", "name");

		$this->assertSame(["imagick", "gd"], array_keys($drivers));
		$this->assertSame(extension_loaded("gd"), $drivers["gd"]->available);
		$this->assertSame(extension_loaded("imagick"), $drivers["imagick"]->available);
		if(extension_loaded("gd")) {
			$this->assertSame(!empty(gd_info()["WebP Support"]), $drivers["gd"]->supports(Format::WEBP));
			$this->assertSame(!empty(gd_info()["AVIF Support"]), $drivers["gd"]->supports(Format::AVIF));
		}
	}
}
