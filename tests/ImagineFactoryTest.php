<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Imagine\Image\ImagineInterface;
use Imagony\Converters\Format;
use Imagony\Diagnostics\Environment;
use Imagony\Exception\{ImagonyException, NoDriverException, UnsupportedFormatException};
use Imagony\ImagineFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImagineFactoryTest extends TestCase {
	protected function setUp(): void {
		if(!extension_loaded("gd") and !extension_loaded("imagick")) {
			$this->markTestSkipped("GD or Imagick extension is required.");
		}
	}

	public function testCreateReturnsSharedInstance(): void {
		$imagine = ImagineFactory::create();

		$this->assertInstanceOf(ImagineInterface::class, $imagine);
		$this->assertSame($imagine, ImagineFactory::create());
	}

	public static function formats(): iterable {
		yield "webp" => [Format::WEBP];
		yield "avif" => [Format::AVIF];
	}

	#[DataProvider("formats")]
	public function testCreateForFormatMatchesSupport(Format $format): void {
		if(!ImagineFactory::supportsFormat($format)) {
			$this->expectException(\RuntimeException::class);
			$this->expectExceptionMessage(strtoupper($format->value));
		}

		$this->assertInstanceOf(ImagineInterface::class, ImagineFactory::create($format));
	}

	protected function tearDown(): void {
		Environment::setCurrent(null);
	}

	public function testUnsupportedFormatExplainsTheCauseAndTheFix(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));

		try {
			ImagineFactory::create(Format::AVIF);
			$this->fail("The exception was expected.");
		} catch(UnsupportedFormatException $e) {
			$this->assertSame(Format::AVIF, $e->format);
			$this->assertInstanceOf(ImagonyException::class, $e);
			$this->assertInstanceOf(\RuntimeException::class, $e);
			$this->assertStringContainsString("GD 2.1.0: built without AVIF", $e->getMessage());
			$this->assertStringContainsString("--with-avif", $e->getMessage());
			$this->assertStringContainsString("imagony.thumbnails.formats", $e->getMessage());
			$this->assertStringContainsString("imagony:check", $e->getMessage());
		}
	}

	public function testSupportedFormatStillWorksWhenAnotherIsMissing(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));

		$this->assertTrue(ImagineFactory::supportsFormat(Format::WEBP));
		$this->assertFalse(ImagineFactory::supportsFormat(Format::AVIF));
		ImagineFactory::assertSupports(Format::WEBP);
		$this->expectException(UnsupportedFormatException::class);
		ImagineFactory::assertSupports(Format::AVIF);
	}

	public function testNoDriverIsReportedWithInstallationHints(): void {
		Environment::setCurrent(Environments::none());

		foreach([null, Format::WEBP, Format::AVIF] as $format) {
			try {
				ImagineFactory::create($format);
				$this->fail("The exception was expected.");
			} catch(NoDriverException $e) {
				$this->assertInstanceOf(ImagonyException::class, $e);
				$this->assertStringContainsString("ext-gd or ext-imagick", $e->getMessage());
			}
		}

		$this->assertFalse(ImagineFactory::supportsFormat(Format::WEBP));
	}

	public function testSupportsFormatAgreesWithGdWhenItIsTheOnlyDriver(): void {
		if(!extension_loaded("gd") or extension_loaded("imagick")) {
			$this->markTestSkipped("Only GD is expected to be loaded.");
		}

		$this->assertSame(!empty(gd_info()["WebP Support"]), ImagineFactory::supportsFormat(Format::WEBP));
		$this->assertSame(!empty(gd_info()["AVIF Support"]), ImagineFactory::supportsFormat(Format::AVIF));
	}
}
