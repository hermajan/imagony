<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Imagony\Picture;

class PictureTest extends ImageTestCase {
	public function testExists(): void {
		file_put_contents($this->dir."/file.txt", "x");

		$this->assertTrue(Picture::exists($this->dir."/file.txt"));
		$this->assertFalse(Picture::exists($this->dir), "A directory is not a file.");
		$this->assertFalse(Picture::exists($this->dir."/missing.png"));
	}

	public function testIsValid(): void {
		$png = $this->saveImage($this->dir."/a.png", 10, 10);
		file_put_contents($this->dir."/bad.png", "this is not an image");

		$this->assertTrue(Picture::isValid($png));
		$this->assertFalse(Picture::isValid($this->dir."/bad.png"));
		$this->assertFalse(Picture::isValid($this->dir."/missing.png"));
		$this->assertFalse(Picture::isValid($this->dir));
	}
}
