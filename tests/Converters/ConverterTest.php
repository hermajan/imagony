<?php

declare(strict_types=1);

namespace Imagony\Tests\Converters;

use Imagony\Converters\{AvifConverter, Format, WebpConverter};
use Imagony\Diagnostics\Environment;
use Imagony\Exception\{NoDriverException, UnsupportedFormatException};
use Imagony\ImagineFactory;
use Imagony\Tests\{Environments, ImageTestCase};

class ConverterTest extends ImageTestCase {
	private function requireFormat(Format $format): void {
		if(!ImagineFactory::supportsFormat($format)) {
			$this->markTestSkipped(strtoupper($format->value)." is not supported by the available Imagine driver.");
		}
	}

	/**
	 * Saves a noisy image, so the output size depends on the quality.
	 */
	private function saveNoise(string $path): string {
		mt_srand(42);
		$gd = imagecreatetruecolor(120, 120);
		for($x = 0; $x < 120; $x++) {
			for($y = 0; $y < 120; $y++) {
				imagesetpixel($gd, $x, $y, mt_rand(0, 0xFFFFFF));
			}
		}
		imagepng($gd, $path);
		return $path;
	}

	public function testWebpConvertCreatesSiblingFile(): void {
		$this->requireFormat(Format::WEBP);
		$png = $this->saveImage($this->dir."/a.png", 40, 20);

		$this->assertTrue((new WebpConverter())->convert($png));
		$this->assertFileExists($png.".webp");
		$this->assertSame([40, 20], $this->sizeOf($png.".webp"));
		$this->assertSame(IMAGETYPE_WEBP, getimagesize($png.".webp")[2]);
		$this->assertFileExists($png, "The original stays.");
	}

	public function testConvertDoesNotOverwriteUnlessReplacing(): void {
		$this->requireFormat(Format::WEBP);
		$png = $this->saveImage($this->dir."/a.png", 40, 20);
		file_put_contents($png.".webp", "old");

		$this->assertFalse((new WebpConverter())->convert($png));
		$this->assertSame("old", file_get_contents($png.".webp"));

		$this->assertTrue((new WebpConverter())->convert($png, true));
		$this->assertNotSame("old", file_get_contents($png.".webp"));
	}

	public function testConvertReturnsFalseForMissingFile(): void {
		$this->assertFalse((new WebpConverter())->convert($this->dir."/missing.png"));
	}

	public function testConvertReturnsFalseWhenFileAlreadyHasTheFormat(): void {
		$this->requireFormat(Format::WEBP);
		$png = $this->saveImage($this->dir."/a.png", 40, 20);
		(new WebpConverter())->convert($png);

		$this->assertFalse((new WebpConverter())->convert($png.".webp", true));
		$this->assertFileDoesNotExist($png.".webp.webp");
	}

	public function testConvertReturnsFalseForNonImage(): void {
		file_put_contents($this->dir."/bad.png", "this is not an image");

		$this->assertFalse((new WebpConverter())->convert($this->dir."/bad.png"));
		$this->assertFileDoesNotExist($this->dir."/bad.png.webp");
	}

	protected function tearDown(): void {
		Environment::setCurrent(null);
		parent::tearDown();
	}

	public function testAvifConvertMatchesDriverSupport(): void {
		$png = $this->saveImage($this->dir."/a.png", 40, 20);

		if(ImagineFactory::supportsFormat(Format::AVIF)) {
			$this->assertTrue((new AvifConverter())->convert($png));
			$this->assertFileExists($png.".avif");
		} else {
			$this->expectException(UnsupportedFormatException::class);
			(new AvifConverter())->convert($png);
		}
	}

	/**
	 * A missing codec is a problem of the PHP installation, so it must not look like a bad image (false).
	 */
	public function testUnsupportedFormatIsNotSwallowed(): void {
		$png = $this->saveImage($this->dir."/a.png", 40, 20);
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));

		try {
			(new AvifConverter())->convert($png);
			$this->fail("The exception was expected.");
		} catch(UnsupportedFormatException $e) {
			$this->assertStringContainsString("--with-avif", $e->getMessage());
		}
		$this->assertFileDoesNotExist($png.".avif");
	}

	public function testNoDriverIsNotSwallowed(): void {
		$png = $this->saveImage($this->dir."/a.png", 40, 20);
		Environment::setCurrent(Environments::none());

		$this->expectException(NoDriverException::class);
		(new WebpConverter())->convert($png);
	}

	public function testBadImageIsStillJustFalseWhenFormatIsSupported(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, true)));
		file_put_contents($this->dir."/bad.png", "this is not an image");

		$this->assertFalse((new AvifConverter())->convert($this->dir."/bad.png"));
	}

	public function testConvertFolderRespectsMasksAndRecurses(): void {
		$this->requireFormat(Format::WEBP);
		$this->saveImage($this->dir."/images/a.png", 30, 30);
		$this->saveImage($this->dir."/images/sub/b.png", 30, 30);
		$this->saveImage($this->dir."/images/c.jpg", 30, 30);

		(new WebpConverter())->convertFolder($this->dir."/images", false, ["*.png"]);

		$this->assertFileExists($this->dir."/images/a.png.webp");
		$this->assertFileExists($this->dir."/images/sub/b.png.webp");
		$this->assertFileDoesNotExist($this->dir."/images/c.jpg.webp");
	}

	public function testConvertFolderWithDefaultMaskConvertsEverything(): void {
		$this->requireFormat(Format::WEBP);
		$this->saveImage($this->dir."/images/a.png", 30, 30);
		$this->saveImage($this->dir."/images/c.jpg", 30, 30);

		(new WebpConverter())->convertFolder($this->dir."/images");

		$this->assertFileExists($this->dir."/images/a.png.webp");
		$this->assertFileExists($this->dir."/images/c.jpg.webp");
		$this->assertFileDoesNotExist($this->dir."/images/a.png.webp.webp", "Already converted files are skipped.");
	}

	public function testQualityIsPassedToTheDriver(): void {
		$this->requireFormat(Format::WEBP);
		$low = $this->saveNoise($this->dir."/low.png");
		$high = $this->saveNoise($this->dir."/high.png");

		$lowConverter = new class extends WebpConverter {
			public function __construct() {
				parent::__construct();
				$this->quality = 5;
			}
		};
		$highConverter = new class extends WebpConverter {
			public function __construct() {
				parent::__construct();
				$this->quality = 95;
			}
		};

		$this->assertTrue($lowConverter->convert($low));
		$this->assertTrue($highConverter->convert($high));
		$this->assertLessThan(filesize($high.".webp"), filesize($low.".webp"));
	}
}
