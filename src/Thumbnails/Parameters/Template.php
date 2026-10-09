<?php

namespace Imagony\Thumbnails\Parameters;

class Template {
	/** Path of folder with images */
	public string $path;
	
	/** Width of thumbnail */
	public ?int $width = null;
	
	/** Height of thumbnail */
	public ?int $height = null;
	
	/** Flags for resizing */
	public array $flags = [];
	
	/** Quality of thumbnail */
	public ?int $quality = null;
}
