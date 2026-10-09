<?php

namespace Imagony\OpenGraph;

use Imagine\Exception\Exception as ImagineException;
use Imagine\Image\Box;
use Imagine\Image\Palette\RGB;
use Imagine\Image\Point;
use Imagony\{ImagineFactory, Resizing\ResizeFlag, Resizing\Resizer};

class Image {
	/**
	 * Converts an image to a specific format or size and saves it to the destination.
	 * If the source file does not exist, the fallback image is used.
	 *
	 * @param string $source The file path of the source image.
	 * @param string $destination The file path where the converted image will be saved.
	 * @param string $fallback The file path of the fallback image if the source is not available or an error occurs.
	 * @return string Returns the file path of the saved image, or the fallback path if an error occurs.
	 */
	public function convert(string $source, string $destination, string $fallback): string {
		if(!file_exists($destination)) {
			$imagine = ImagineFactory::create();

			try {
				$image = $imagine->open(file_exists($source) ? $source : $fallback);
			} catch(ImagineException $e) {
				return $fallback;
			}

			$width = $image->getSize()->getWidth();
			$height = $image->getSize()->getHeight();

			try {
				if($width > $height) {
					// crop to the 1.91:1 ratio
					$picture = Resizer::resize($image, $width, (int)($width / 1.91), [ResizeFlag::EXACT]);
				} else {
					// place on the white 1.91:1 canvas
					$picture = $imagine->create(new Box((int)($height * 1.91), $height), (new RGB())->color("#fff"));
					$picture->paste($image, new Point((int)(($picture->getSize()->getWidth() - $width) / 2), 0));
				}

				$picture->save($destination);
			} catch(ImagineException $e) {
				return $fallback;
			}
		}

		return $destination;
	}
}
