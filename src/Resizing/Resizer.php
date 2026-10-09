<?php

declare(strict_types=1);

namespace Imagony\Resizing;

use Imagine\Image\{Box, ImageInterface, Point};

final class Resizer {
	private function __construct() {
	}
	
	/**
	 * Resizes the image in place. Missing width or height is calculated from the aspect ratio.
	 * @param list<ResizeFlag> $flags
	 */
	public static function resize(ImageInterface $image, ?int $width, ?int $height, array $flags = []): ImageInterface {
		$size = $image->getSize();
		$sourceWidth = $size->getWidth();
		$sourceHeight = $size->getHeight();
		
		$shrinkOnly = in_array(ResizeFlag::SHRINK_ONLY, $flags, true);
		$stretch = in_array(ResizeFlag::STRETCH, $flags, true);
		$exact = in_array(ResizeFlag::EXACT, $flags, true);
		$fill = $exact || in_array(ResizeFlag::FILL, $flags, true);
		
		if(!$width && !$height) {
			return $image;
		}
		
		if($stretch && $width && $height) {
			$scaleX = $width / $sourceWidth;
			$scaleY = $height / $sourceHeight;
		} else {
			$scaleX = $width ? $width / $sourceWidth : null;
			$scaleY = $height ? $height / $sourceHeight : null;
			
			if($scaleX === null || $scaleY === null) {
				$scaleX = $scaleY = $scaleX ?? $scaleY;
			} else {
				$scaleX = $scaleY = $fill ? max($scaleX, $scaleY) : min($scaleX, $scaleY);
			}
		}
		
		if($shrinkOnly) {
			$scaleX = min($scaleX, 1);
			$scaleY = min($scaleY, 1);
		}
		
		$newWidth = max(1, (int)round($sourceWidth * $scaleX));
		$newHeight = max(1, (int)round($sourceHeight * $scaleY));
		
		if($newWidth !== $sourceWidth || $newHeight !== $sourceHeight) {
			$image->resize(new Box($newWidth, $newHeight));
		}
		
		if($exact && $width && $height) {
			$cropWidth = min($width, $newWidth);
			$cropHeight = min($height, $newHeight);
			if($cropWidth !== $newWidth || $cropHeight !== $newHeight) {
				$image->crop(
					new Point((int)floor(($newWidth - $cropWidth) / 2), (int)floor(($newHeight - $cropHeight) / 2)),
					new Box($cropWidth, $cropHeight)
				);
			}
		}
		
		return $image;
	}
}
