<?php

declare(strict_types=1);

namespace Imagony\Tests\Thumbnails;

use Imagony\Converters\Format;
use Imagony\Diagnostics\{DriverInfo, Environment};
use Imagony\Exception\{NoDriverException, UnsupportedFormatException};
use Imagony\ImagineFactory;
use Imagony\Placeholder;
use Imagony\Resizing\ResizeFlag;
use Imagony\Tests\{ArrayLogger, Environments, ImageTestCase};
use Imagony\Thumbnails\Thumbnail;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Log\LoggerInterface;

class ThumbnailTest extends ImageTestCase {
	private string $base;

	protected function setUp(): void {
		parent::setUp();

		$this->base = $this->dir."/public/";
		mkdir($this->base."uploads", 0755, true);
		$this->saveImage($this->base."uploads/a.png", 400, 200);

		$this->configure();
	}

	protected function tearDown(): void {
		Environment::setCurrent(null);
		parent::tearDown();
	}

	private function configure(array $override = [], ?LoggerInterface $logger = null, bool $debug = false): void {
		new Thumbnail(array_replace([
			"base" => $this->base,
			"folder" => $this->base."thumbnails/",
			"fallback" => $this->base."no-image.png",
			"flags" => ["shrink_only"],
			"formats" => [],
			"templates" => [
				"small" => ["path" => "uploads/", "width" => null, "height" => 50, "flags" => [], "quality" => 80],
				"square" => ["path" => "uploads/", "width" => 50, "height" => 50, "flags" => ["exact"], "quality" => null],
				"box" => ["path" => "uploads/", "width" => 50, "height" => 50, "flags" => [], "quality" => null],
			],
		], $override), $logger, $debug);
	}

	/**
	 * Absolute path of a path returned by Thumbnail::create().
	 */
	private function absolute(string $created): string {
		return realpath($this->base).$created;
	}

	public function testCreateWithTemplateResizesIntoTemplateFolder(): void {
		$source = $this->base."uploads/a.png";

		$created = Thumbnail::create("small", "a.png");

		$this->assertSame("/thumbnails/small/a_x50f1q80mt".filemtime($source).".png", $created);
		$this->assertSame([100, 50], $this->sizeOf($this->absolute($created)));
	}

	public function testCreateIsCached(): void {
		$first = $this->absolute(Thumbnail::create("small", "a.png"));
		touch($first, time() - 1000);
		$mtime = filemtime($first);

		$this->assertSame($first, $this->absolute(Thumbnail::create("small", "a.png")));
		$this->assertSame($mtime, filemtime($first), "The thumbnail must not be generated again.");
	}

	public function testChangedSourceCreatesNewThumbnail(): void {
		$first = Thumbnail::create("small", "a.png");
		touch($this->base."uploads/a.png", time() + 100);
		clearstatcache();

		$second = Thumbnail::create("small", "a.png");

		$this->assertNotSame($first, $second);
		$this->assertFileExists($this->absolute($second));
	}

	public function testTemplateFlagsOverrideDefaultFlags(): void {
		$this->assertSame([50, 50], $this->sizeOf($this->absolute(Thumbnail::create("square", "a.png"))), "exact crops to the box");
		$this->assertSame([50, 25], $this->sizeOf($this->absolute(Thumbnail::create("box", "a.png"))), "the default flags fit the box");
	}

	public static function flagVariants(): iterable {
		yield "strings" => [["exact"]];
		yield "enums" => [[ResizeFlag::EXACT]];
		yield "ints" => [[8]];
		yield "legacy Nette constants" => [['Nette\Utils\Image::EXACT']];
	}

	#[DataProvider("flagVariants")]
	public function testCreateWithoutTemplateAcceptsFlagTypes(array $flags): void {
		$created = Thumbnail::create("uploads/", "a.png", 50, 50, $flags);

		$this->assertSame([50, 50], $this->sizeOf($this->absolute($created)));
	}

	public function testCreateWithoutTemplateAndSizeReturnsOriginal(): void {
		$this->assertSame("/uploads/a.png", Thumbnail::create("uploads/", "a.png"));
	}

	public function testMissingSourceReturnsFallback(): void {
		$this->assertSame("/no-image.png", Thumbnail::create("small", "missing.png"));
	}

	public function testCorruptSourceReturnsFallback(): void {
		file_put_contents($this->base."uploads/bad.png", "this is not an image");

		$this->assertSame("/no-image.png", Thumbnail::create("small", "bad.png"));
	}

	public function testSvgSourceIsReturnedUntouched(): void {
		file_put_contents($this->base."uploads/logo.svg", '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"/>');

		$this->assertSame("/uploads/logo.svg", Thumbnail::create("small", "logo.svg"));
	}

	public function testCreateGeneratesRequestedFormats(): void {
		if(!ImagineFactory::supportsFormat(Format::WEBP)) {
			$this->markTestSkipped("WEBP is not supported by the available Imagine driver.");
		}

		$created = Thumbnail::create("small", "a.png", formats: ["webp"]);

		$this->assertFileExists($this->absolute($created).".webp");
	}

	public function testFormatsFromConfigurationAreUsedByDefault(): void {
		if(!ImagineFactory::supportsFormat(Format::WEBP)) {
			$this->markTestSkipped("WEBP is not supported by the available Imagine driver.");
		}
		$this->configure(["formats" => ["webp"]]);

		$created = Thumbnail::create("small", "a.png");

		$this->assertFileExists($this->absolute($created).".webp");
	}

	public function testUnsupportedFormatIsLoggedOnceAndTheThumbnailIsStillCreated(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));
		$logger = new ArrayLogger();
		$this->configure(logger: $logger);

		$created = Thumbnail::create("small", "a.png", formats: ["avif"]);
		Thumbnail::create("small", "a.png", formats: ["avif"]);
		Thumbnail::create("square", "a.png", formats: ["avif"]);

		$this->assertFileExists($this->absolute($created));
		$this->assertFileDoesNotExist($this->absolute($created).".avif");
		$warnings = $logger->messages();
		$this->assertCount(1, $warnings, "The problem repeats for every image, it is logged once.");
		$this->assertStringContainsString("AVIF thumbnails are skipped", $warnings[0]);
		$this->assertStringContainsString("--with-avif", $warnings[0]);
	}

	public function testSupportedFormatsAreStillCreatedNextToAnUnsupportedOne(): void {
		if(!ImagineFactory::supportsFormat(Format::WEBP)) {
			$this->markTestSkipped("WEBP is not supported by the available Imagine driver.");
		}
		Environment::setCurrent(Environments::of(new DriverInfo("gd", true, "2.1.0", ["webp" => true, "avif" => false])));
		$this->configure(logger: new ArrayLogger());

		$created = Thumbnail::create("small", "a.png", formats: ["avif", "webp"]);

		$this->assertFileExists($this->absolute($created).".webp");
		$this->assertFileDoesNotExist($this->absolute($created).".avif");
	}

	public function testUnsupportedFormatThrowsInDebugMode(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));
		$this->configure(debug: true);

		try {
			Thumbnail::create("small", "a.png", formats: ["avif"]);
			$this->fail("The exception was expected.");
		} catch(UnsupportedFormatException $e) {
			$this->assertStringContainsString("GD 2.1.0: built without AVIF", $e->getMessage());
		}
	}

	public function testStrictOptionOverridesDebugMode(): void {
		Environment::setCurrent(Environments::of(Environments::gd(true, false)));

		$this->configure(["strict" => false], new ArrayLogger(), true);
		$created = Thumbnail::create("small", "a.png", formats: ["avif"]);
		$this->assertFileExists($this->absolute($created), "Debug mode does not throw when strict is off.");

		$this->configure(["strict" => true], null, false);
		$this->expectException(UnsupportedFormatException::class);
		Thumbnail::create("small", "a.png", formats: ["avif"]);
	}

	public function testMissingDriverAlwaysThrows(): void {
		Environment::setCurrent(Environments::none());
		$this->configure();

		$this->expectException(NoDriverException::class);
		Thumbnail::create("small", "a.png", formats: ["webp"]);
	}

	public function testBadImageIsLoggedBeforeTheFallbackIsUsed(): void {
		file_put_contents($this->base."uploads/bad.png", "this is not an image");
		$logger = new ArrayLogger();
		$this->configure(logger: $logger);

		$this->assertSame("/no-image.png", Thumbnail::create("small", "bad.png"));

		$warnings = $logger->messages();
		$this->assertStringContainsString("bad.png", $warnings[0]);
		$this->assertStringContainsString("fallback image is used", $warnings[0]);
	}

	public function testMissingFallbackImageIsLoggedOnce(): void {
		$logger = new ArrayLogger();
		$this->configure(logger: $logger);

		Thumbnail::create("small", "missing.png");
		Thumbnail::create("small", "missing2.png");

		$warnings = array_values(array_filter($logger->messages(), fn(string $message) => str_contains($message, "fallback image")));
		$this->assertCount(1, $warnings);
		$this->assertStringContainsString("imagony.thumbnails.fallback", $warnings[0]);
	}

	public function testMissingSourceWithExistingFallbackIsNotAProblem(): void {
		$this->saveImage($this->base."no-image.png", 10, 10);
		$logger = new ArrayLogger();
		$this->configure(logger: $logger);

		$this->assertSame("/no-image.png", Thumbnail::create("small", "missing.png"));
		$this->assertSame([], $logger->records, "A missing source is expected and the fallback exists.");
	}

	public function testBuiltInPlaceholderIsReturnedAsUrlWhenThereIsNoFallbackOption(): void {
		$logger = new ArrayLogger();
		$this->configure(["fallback" => null], $logger);

		$this->assertSame(Placeholder::URL, Thumbnail::create("small", "missing.png"));
		$this->assertSame(Placeholder::URL, Thumbnail::create("uploads/", "missing.png", 50, 50));
		$this->assertSame([], $logger->records, "The built-in placeholder always exists.");
	}

	public function testBuiltInPlaceholderIsUsedForBadImagesToo(): void {
		file_put_contents($this->base."uploads/bad.png", "this is not an image");
		$logger = new ArrayLogger();
		$this->configure(["fallback" => null], $logger);

		$this->assertSame(Placeholder::URL, Thumbnail::create("small", "bad.png"));
		$this->assertStringContainsString("bad.png", $logger->messages()[0], "The reason is still logged.");
	}

	/**
	 * The placeholder lives in vendor: nothing may be written next to it and the variants are served by the bundle.
	 */
	public function testBuiltInPlaceholderIsNeverConverted(): void {
		if(!ImagineFactory::supportsFormat(Format::WEBP)) {
			$this->markTestSkipped("WEBP is not supported by the available Imagine driver.");
		}
		$before = scandir(dirname(Placeholder::file()));
		$this->configure(["fallback" => null, "formats" => ["webp"]]);

		$this->assertSame(Placeholder::URL, Thumbnail::create("small", "missing.png"));
		$this->assertSame($before, scandir(dirname(Placeholder::file())), "No files are created in the folder of the bundle.");
	}

	public function testOwnFallbackStillWorksAsAPathInBase(): void {
		$this->saveImage($this->base."images/own.png", 10, 10);
		$this->configure(["fallback" => $this->base."images/own.png"]);

		$this->assertSame("/images/own.png", Thumbnail::create("small", "missing.png"));
	}

	public function testUnknownFormatIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		Thumbnail::create("small", "a.png", formats: ["gif"]);
	}

	public function testUnknownFlagIsRejected(): void {
		$this->expectException(\InvalidArgumentException::class);
		Thumbnail::create("uploads/", "a.png", 50, 50, ["crop"]);
	}

	public function testUnwritableFolderThrows(): void {
		file_put_contents($this->dir."/blocker", "I am a file");
		$this->configure(["folder" => $this->dir."/blocker/thumbs/"]);

		// mkdir() reports a warning before the exception is thrown
		set_error_handler(static fn() => true);
		try {
			$this->expectException(\RuntimeException::class);
			Thumbnail::create("small", "a.png");
		} finally {
			restore_error_handler();
		}
	}

	public function testGetPath(): void {
		$this->assertSame("uploads/", Thumbnail::getPath("small"));
		$this->assertSame("some/other/", Thumbnail::getPath("some/other/"));
	}

	public function testGetParameters(): void {
		$parameters = Thumbnail::getParameters();

		$this->assertSame($this->base, $parameters["base"]);
		$this->assertArrayHasKey("small", $parameters["templates"]);
	}

	public function testGenerateCreatesThumbnailsForEveryTemplateFile(): void {
		$this->saveImage($this->base."uploads/b.png", 300, 300);
		$this->configure(["templates" => [
			"small" => ["path" => "uploads/", "width" => null, "height" => 50, "flags" => [], "quality" => 80],
		]]);

		Thumbnail::generate();

		$thumbnails = glob($this->base."thumbnails/small/*.png");
		$this->assertCount(2, $thumbnails);
		sort($thumbnails);
		$this->assertStringContainsString("/a_x50", $thumbnails[0]);
		$this->assertStringContainsString("/b_x50", $thumbnails[1]);
		$this->assertSame([50, 50], $this->sizeOf($thumbnails[1]));
	}

	public function testCleanRemovesFilesRecursivelyAndKeepsDotFilesAndFolders(): void {
		$folder = $this->base."thumbnails/";
		mkdir($folder."small/deep", 0755, true);
		mkdir($folder.".cache", 0755, true);
		file_put_contents($folder."x.png", "x");
		file_put_contents($folder."x.png.webp", "x");
		file_put_contents($folder.".gitkeep", "");
		file_put_contents($folder.".htaccess", "");
		file_put_contents($folder.".cache/z.png", "z");
		file_put_contents($folder."small/y.png", "y");
		file_put_contents($folder."small/.gitkeep", "");
		file_put_contents($folder."small/deep/z.png", "z");

		$removed = Thumbnail::clean();

		$expected = [$folder."small/deep/z.png", $folder."small/y.png", $folder."x.png", $folder."x.png.webp"];
		sort($removed);
		$this->assertSame($expected, $removed);
		foreach($expected as $file) {
			$this->assertFileDoesNotExist($file);
		}
		$this->assertFileExists($folder.".gitkeep");
		$this->assertFileExists($folder.".htaccess");
		$this->assertFileExists($folder.".cache/z.png", "Dot folders are left alone.");
		$this->assertFileExists($folder."small/.gitkeep");
		$this->assertDirectoryExists($folder."small/deep");
	}

	public function testCleanRemovesGeneratedThumbnails(): void {
		$created = $this->absolute(Thumbnail::create("small", "a.png"));
		$this->assertFileExists($created);

		$this->assertSame([$created], Thumbnail::clean());
		$this->assertFileDoesNotExist($created);
	}

	public function testCleanDryRunOnlyFindsThumbnails(): void {
		$created = $this->absolute(Thumbnail::create("small", "a.png"));

		$this->assertSame([$created], Thumbnail::clean(true));
		$this->assertFileExists($created);
	}

	public function testCleanWithMissingFolderRemovesNothing(): void {
		$this->configure(["folder" => $this->base."never-created/"]);

		$this->assertSame([], Thumbnail::clean());
		$this->assertSame([], Thumbnail::clean(true));
	}

	public function testCleanRefusesToRemoveSourceImages(): void {
		foreach([$this->base, $this->base."uploads/", $this->dir."/", $this->base."uploads/../"] as $folder) {
			$this->configure(["folder" => $folder]);

			try {
				Thumbnail::clean();
				$this->fail("The folder `{$folder}` must not be cleaned.");
			} catch(\LogicException $e) {
				$this->assertStringContainsString("imagony.thumbnails.folder", $e->getMessage());
			}
		}

		$this->assertFileExists($this->base."uploads/a.png");
	}

	public function testCleanAllowsAFolderNextToTheBase(): void {
		mkdir($this->dir."/cache/thumbs", 0755, true);
		file_put_contents($this->dir."/cache/thumbs/x.png", "x");
		$this->configure(["folder" => $this->dir."/cache/thumbs/"]);

		$this->assertSame([$this->dir."/cache/thumbs/x.png"], Thumbnail::clean());
	}
}
