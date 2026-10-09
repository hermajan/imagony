<?php

declare(strict_types=1);

namespace Imagony\Twig;

use Imagony\Thumbnails\Thumbnail;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ImagonyExtension extends AbstractExtension {
	public function __construct(private readonly Thumbnail $thumbnail) {}

	public function getFunctions(): array {
		return [new TwigFunction('thumbnail', [Thumbnail::class, 'create'])];
	}
}
