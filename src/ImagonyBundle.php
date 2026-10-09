<?php

declare(strict_types=1);

namespace Imagony;

use Imagony\Symfony\ImagonyExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImagonyBundle extends Bundle {
	protected function getContainerExtensionClass(): string {
		return ImagonyExtension::class;
	}
}
