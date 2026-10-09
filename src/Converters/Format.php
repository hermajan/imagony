<?php

declare(strict_types=1);

namespace Imagony\Converters;

enum Format: string {
	case WEBP = "webp";
	case AVIF = "avif";
	
	/**
	 * Creates a format from a config value: `webp`, `Webp` or an IMAGETYPE_* int.
	 */
	public static function fromConfig(string|int|self $value): self {
		if($value instanceof self) {
			return $value;
		}
		
		if(is_int($value)) {
			return match($value) {
				IMAGETYPE_WEBP => self::WEBP,
				IMAGETYPE_AVIF => self::AVIF,
				default => throw new \InvalidArgumentException("Unsupported image format `$value`."),
			};
		}
		
		$name = strtolower(substr(strrchr("::".$value, ":"), 1));
		return self::tryFrom($name) ?? throw new \InvalidArgumentException("Unsupported image format `$value`.");
	}
}
