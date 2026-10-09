<?php

declare(strict_types=1);

namespace Imagony\Tests\OpenGraph;

use Imagony\OpenGraph\Image;
use Imagony\Tests\ImageTestCase;

class ImageTest extends ImageTestCase {
	private function colorAt(string $path, int $x, int $y): int {
		$gd = imagecreatefrompng($path);
		return imagecolorat($gd, $x, $y) & 0xFFFFFF;
	}

	public function testLandscapeIsCroppedToOpenGraphRatio(): void {
		$source = $this->saveImage($this->dir."/wide.png", 800, 400, "#ff0000");
		$destination = $this->dir."/og.png";

		$result = (new Image())->convert($source, $destination, $this->dir."/fallback.png");

		$this->assertSame($destination, $result);
		$this->assertSame([800, 418], $this->sizeOf($destination));
		$this->assertSame(0xFF0000, $this->colorAt($destination, 400, 209));
	}

	public function testPortraitIsCenteredOnWhiteCanvas(): void {
		$source = $this->saveImage($this->dir."/tall.png", 400, 800, "#ff0000");
		$destination = $this->dir."/og.png";

		$result = (new Image())->convert($source, $destination, $this->dir."/fallback.png");

		$this->assertSame($destination, $result);
		$this->assertSame([1528, 800], $this->sizeOf($destination));
		$this->assertSame(0xFFFFFF, $this->colorAt($destination, 5, 5), "The side is white.");
		$this->assertSame(0xFF0000, $this->colorAt($destination, 764, 400), "The image is in the middle.");
		$this->assertSame(0xFFFFFF, $this->colorAt($destination, 1520, 400), "The other side is white.");
	}

	public function testExistingDestinationIsNotOverwritten(): void {
		$source = $this->saveImage($this->dir."/wide.png", 800, 400);
		$destination = $this->dir."/og.png";
		file_put_contents($destination, "existing");

		$this->assertSame($destination, (new Image())->convert($source, $destination, $this->dir."/fallback.png"));
		$this->assertSame("existing", file_get_contents($destination));
	}

	public function testMissingSourceUsesFallbackImage(): void {
		$fallback = $this->saveImage($this->dir."/fallback.png", 400, 200);
		$destination = $this->dir."/og.png";

		$result = (new Image())->convert($this->dir."/missing.png", $destination, $fallback);

		$this->assertSame($destination, $result);
		$this->assertSame([400, 209], $this->sizeOf($destination));
	}

	public function testInvalidSourceReturnsFallbackPath(): void {
		file_put_contents($this->dir."/bad.png", "this is not an image");
		$destination = $this->dir."/og.png";

		$result = (new Image())->convert($this->dir."/bad.png", $destination, $this->dir."/fallback.png");

		$this->assertSame($this->dir."/fallback.png", $result);
		$this->assertFileDoesNotExist($destination);
	}

	public function testMissingSourceAndFallbackReturnsFallbackPath(): void {
		$destination = $this->dir."/og.png";

		$result = (new Image())->convert($this->dir."/missing.png", $destination, $this->dir."/fallback.png");

		$this->assertSame($this->dir."/fallback.png", $result);
		$this->assertFileDoesNotExist($destination);
	}
}
