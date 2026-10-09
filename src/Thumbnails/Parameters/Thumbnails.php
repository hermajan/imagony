<?php

namespace Imagony\Thumbnails\Parameters;

use Imagony\Resizing\ResizeFlag;

class Thumbnails {
	/** Base folder with images*/
	public string $base;
	
	/** Path to folder with thumbnails */
	public string $folder;
	
	/** Filename of fallback thumbnail image */
	public string $fallback;
	
	/** Flags for resizing */
	public array $flags = [ResizeFlag::SHRINK_ONLY];
	
	/** Formats to which thumbnail also converts */
	public array $formats = [];
}
