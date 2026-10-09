<?php

declare(strict_types=1);

namespace Imagony\Tests\Twig;

use Imagony\Thumbnails\Thumbnail;
use Imagony\Twig\ImagonyExtension;
use PHPUnit\Framework\TestCase;

class ImagonyExtensionTest extends TestCase {
	public function testExposesThumbnailFunction(): void {
		$extension = new ImagonyExtension(new Thumbnail(["templates" => []]));

		$functions = $extension->getFunctions();
		$this->assertCount(1, $functions);
		$this->assertSame("thumbnail", $functions[0]->getName());
		$this->assertSame([Thumbnail::class, "create"], $functions[0]->getCallable());
	}
}
