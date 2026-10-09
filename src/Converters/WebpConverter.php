<?php

namespace Imagony\Converters;

/**
 * Converts images to WebP format.
 * @see https://en.wikipedia.org/wiki/WebP
 */
class WebpConverter extends Converter {
	public function __construct() {
		$this->extension = "webp";
		$this->quality = -1;
		$this->format = Format::WEBP;
	}
}
