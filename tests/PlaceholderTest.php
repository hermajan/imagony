<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Imagony\Placeholder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PlaceholderTest extends TestCase {
	public static function variants(): iterable {
		yield "png" => ["", "image/png", IMAGETYPE_PNG];
		yield "webp" => [".webp", "image/webp", IMAGETYPE_WEBP];
		yield "avif" => [".avif", "image/avif", IMAGETYPE_AVIF];
	}

	#[DataProvider("variants")]
	public function testVariantIsShippedAndResolved(string $suffix, string $type, int $imageType): void {
		$resolved = Placeholder::resolve(Placeholder::URL.$suffix);

		$this->assertNotNull($resolved);
		$this->assertSame($type, $resolved["type"]);
		$this->assertSame(Placeholder::file().$suffix, $resolved["file"]);
		$this->assertFileExists($resolved["file"]);

		$size = getimagesize($resolved["file"]);
		$this->assertIsArray($size, "A real image is shipped.");
		$this->assertSame($imageType, $size[2]);
		$this->assertSame([600, 400], [$size[0], $size[1]]);
	}

	public function testFileIsInsideTheBundleNotInPublic(): void {
		$this->assertSame(dirname(__DIR__)."/resources/no-image.png", Placeholder::file());
		$this->assertDirectoryDoesNotExist(dirname(__DIR__)."/public", "`assets:install` would copy a `public` folder, the placeholder is served from the bundle.");
	}

	public function testUrlIsAnAbsolutePath(): void {
		$this->assertSame("/_imagony/no-image.png", Placeholder::URL);
	}

	public static function otherPaths(): iterable {
		yield "other file" => ["/_imagony/other.png"];
		yield "other folder" => ["/no-image.png"];
		yield "traversal" => ["/_imagony/../resources/no-image.png"];
		yield "unknown variant" => ["/_imagony/no-image.png.gif"];
		yield "prefix" => ["/prefix/_imagony/no-image.png"];
		yield "empty" => [""];
	}

	#[DataProvider("otherPaths")]
	public function testOtherPathsAreNotThePlaceholder(string $path): void {
		$this->assertNull(Placeholder::resolve($path));
	}
}
