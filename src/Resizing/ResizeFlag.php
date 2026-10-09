<?php

declare(strict_types=1);

namespace Imagony\Resizing;

/**
 * Resize modes. Backing values match the former Nette\Utils\Image constants,
 * so the bitmask in generated thumbnail filenames stays the same.
 */
enum ResizeFlag: int {
	/** Fits the image into the given box, keeping the aspect ratio. */
	case FIT = 0;
	
	/** Never enlarges the image. */
	case SHRINK_ONLY = 1;
	
	/** Ignores the aspect ratio. */
	case STRETCH = 2;
	
	/** Fills the whole box, keeping the aspect ratio. The overflow is kept. */
	case FILL = 4;
	
	/** Fills the whole box, keeping the aspect ratio, and crops the overflow. */
	case EXACT = 8;
	
	/**
	 * Creates a flag from a config value: `shrink_only`, `ShrinkOnly` or an int.
	 */
	public static function fromConfig(string|int|self $value): self {
		if($value instanceof self) {
			return $value;
		}
		
		if(is_int($value)) {
			return self::tryFrom($value) ?? throw new \InvalidArgumentException("Unknown resize flag `$value`.");
		}
		
		$name = strtolower(preg_replace('/[^a-z]/i', '', substr(strrchr("::".$value, ":"), 1)));
		return match($name) {
			"fit" => self::FIT,
			"shrinkonly" => self::SHRINK_ONLY,
			"stretch" => self::STRETCH,
			"fill" => self::FILL,
			"exact" => self::EXACT,
			default => throw new \InvalidArgumentException("Unknown resize flag `$value`."),
		};
	}
	
	/**
	 * @param array<string|int|self> $flags
	 * @return list<self>
	 */
	public static function fromConfigList(array $flags): array {
		return array_map(self::fromConfig(...), array_values($flags));
	}
	
	/**
	 * @param iterable<self> $flags
	 */
	public static function toMask(iterable $flags): int {
		$mask = 0;
		foreach($flags as $flag) {
			$mask |= $flag->value;
		}
		return $mask;
	}
}
