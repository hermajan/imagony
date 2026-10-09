<?php

declare(strict_types=1);

namespace Imagony;

/**
 * The built-in fallback image. It is served straight from the folder of the bundle (e.g. `vendor/hermajan/imagony/resources`),
 * so nothing has to be installed or copied into `public/`. See {@see EventListener\PlaceholderListener}.
 */
final class Placeholder {
	/** URL of the image which `thumbnail()` returns when the source is missing. */
	public const URL = "/_imagony/no-image.png";

	/** Variants next to the PNG, so `<source srcset="{{ thumbnail(...) }}.webp">` works for the placeholder too. */
	private const TYPES = [
		"" => "image/png",
		".webp" => "image/webp",
		".avif" => "image/avif",
	];

	private function __construct() {
	}

	/**
	 * Absolute path of the PNG image in the bundle.
	 */
	public static function file(): string {
		return dirname(__DIR__)."/resources/no-image.png";
	}

	/**
	 * Finds the file and the content type for the URL of the placeholder or of its WebP/AVIF variant.
	 * @return array{file: string, type: string}|null Null when the path is not the placeholder.
	 */
	public static function resolve(string $path): ?array {
		foreach(self::TYPES as $suffix => $type) {
			if($path === self::URL.$suffix) {
				$file = self::file().$suffix;
				return is_file($file) ? ["file" => $file, "type" => $type] : null;
			}
		}

		return null;
	}
}
