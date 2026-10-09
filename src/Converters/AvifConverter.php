<?php

namespace Imagony\Converters;

/**
 * Converts images to AVIF format.
 * @see https://en.wikipedia.org/wiki/AVIF
 */
class AvifConverter extends Converter {
	public function __construct() {
		$this->extension = "avif";
		$this->quality = -1;
		$this->format = Format::AVIF;
	}
}
