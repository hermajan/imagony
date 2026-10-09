<?php

declare(strict_types=1);

namespace Imagony\Tests\Resizing;

use Imagony\Resizing\{ResizeFlag, Resizer};
use Imagony\Tests\ImageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class ResizerTest extends ImageTestCase {
	/**
	 * Source size, target width, target height, flags, expected size.
	 */
	public static function resizes(): iterable {
		$big = [2816, 1536];
		$small = [100, 50];

		yield "no dimensions keeps the image" => [$big, null, null, [], [2816, 1536]];
		yield "zero dimensions keep the image" => [$big, 0, 0, [], [2816, 1536]];
		yield "height only" => [$big, null, 250, [], [458, 250]];
		yield "width only" => [$big, 1408, null, [], [1408, 768]];
		yield "fit into a square" => [$big, 100, 100, [], [100, 55]];
		yield "fit into a square (explicit FIT)" => [$big, 100, 100, [ResizeFlag::FIT], [100, 55]];
		yield "fit enlarges when allowed" => [$small, 200, 200, [], [200, 100]];
		yield "fill keeps the overflow" => [$big, 100, 100, [ResizeFlag::FILL], [183, 100]];
		yield "exact crops the overflow" => [$big, 100, 100, [ResizeFlag::EXACT], [100, 100]];
		yield "exact with one dimension does not crop" => [$big, null, 250, [ResizeFlag::EXACT], [458, 250]];
		yield "stretch ignores the ratio" => [$big, 100, 100, [ResizeFlag::STRETCH], [100, 100]];
		yield "shrink only shrinks" => [$big, null, 250, [ResizeFlag::SHRINK_ONLY], [458, 250]];
		yield "shrink only does not enlarge by height" => [$big, null, 9999, [ResizeFlag::SHRINK_ONLY], [2816, 1536]];
		yield "shrink only does not enlarge a box" => [$small, 400, 400, [ResizeFlag::SHRINK_ONLY], [100, 50]];
		yield "shrink only with exact does not enlarge" => [$small, 400, 400, [ResizeFlag::SHRINK_ONLY, ResizeFlag::EXACT], [100, 50]];
		yield "shrink only with exact crops a smaller box" => [$small, 40, 40, [ResizeFlag::SHRINK_ONLY, ResizeFlag::EXACT], [40, 40]];
		yield "extreme ratio never collapses to zero" => [[1000, 10], 5, null, [], [5, 1]];
	}

	#[DataProvider("resizes")]
	public function testResize(array $source, ?int $width, ?int $height, array $flags, array $expected): void {
		$image = $this->createImage($source[0], $source[1]);

		Resizer::resize($image, $width, $height, $flags);

		$this->assertSame($expected, [$image->getSize()->getWidth(), $image->getSize()->getHeight()]);
	}

	public function testResizesInPlaceAndReturnsTheSameInstance(): void {
		$image = $this->createImage(400, 200);

		$this->assertSame($image, Resizer::resize($image, 100, 100));
		$this->assertSame(100, $image->getSize()->getWidth());
	}

	public function testExactCropsFromTheCenter(): void {
		// left half red, right half blue; a square crop of the wide image must keep the middle of both
		$path = $this->dir."/split.png";
		$gd = imagecreatetruecolor(200, 100);
		imagefilledrectangle($gd, 0, 0, 99, 99, 0xFF0000);
		imagefilledrectangle($gd, 100, 0, 199, 99, 0x0000FF);
		imagepng($gd, $path);

		$image = (new \Imagine\Gd\Imagine())->open($path);
		Resizer::resize($image, 100, 100, [ResizeFlag::EXACT]);

		$this->assertSame([100, 100], [$image->getSize()->getWidth(), $image->getSize()->getHeight()]);
		$this->assertSame("#ff0000", (string)$image->getColorAt(new \Imagine\Image\Point(5, 50)));
		$this->assertSame("#0000ff", (string)$image->getColorAt(new \Imagine\Image\Point(95, 50)));
	}
}
