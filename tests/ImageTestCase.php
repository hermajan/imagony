<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Imagine\Gd\Imagine;
use Imagine\Image\{Box, ImageInterface};
use Imagine\Image\Palette\RGB;
use PHPUnit\Framework\TestCase;

/**
 * Base for tests working with images: gives every test its own temporary directory and generates images with GD.
 */
abstract class ImageTestCase extends TestCase {
	protected string $dir;

	protected function setUp(): void {
		if(!extension_loaded("gd")) {
			$this->markTestSkipped("GD extension is required.");
		}

		$dir = sys_get_temp_dir().DIRECTORY_SEPARATOR."imagony_".bin2hex(random_bytes(4));
		mkdir($dir, 0755, true);
		$this->dir = realpath($dir);
	}

	protected function tearDown(): void {
		if(isset($this->dir) and is_dir($this->dir)) {
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
				\RecursiveIteratorIterator::CHILD_FIRST
			);
			foreach($items as $item) {
				$item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
			}
			rmdir($this->dir);
		}
	}

	protected function createImage(int $width, int $height, string $color = "#ffffff"): ImageInterface {
		return (new Imagine())->create(new Box($width, $height), (new RGB())->color($color));
	}

	/**
	 * Saves a plain image (format by extension) and returns its path. Missing folders are created.
	 */
	protected function saveImage(string $path, int $width, int $height, string $color = "#ffffff"): string {
		if(!is_dir(dirname($path))) {
			mkdir(dirname($path), 0755, true);
		}
		$this->createImage($width, $height, $color)->save($path);
		return $path;
	}

	/**
	 * @return array{0: int, 1: int} Width and height of the image file.
	 */
	protected function sizeOf(string $path): array {
		$size = getimagesize($path);
		$this->assertIsArray($size, "`$path` is not an image.");
		return [$size[0], $size[1]];
	}
}
