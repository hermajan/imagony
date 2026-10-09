<?php

namespace Imagony\Thumbnails;

use Imagine\Exception\Exception as ImagineException;
use Imagony\{Converters\Format, ImagineFactory, Picture, Placeholder, Resizing\ResizeFlag, Resizing\Resizer};
use Imagony\Converters\{AvifConverter, WebpConverter};
use Imagony\Exception\{NoDriverException, UnsupportedFormatException};
use Psr\Log\{LoggerInterface, NullLogger};
use Symfony\Component\Finder\Finder;

class Thumbnail {
	private static array $parameters = [];

	private static ?LoggerInterface $logger = null;

	private static bool $debug = false;

	/** @var array<string, true> Problems of the installation which were logged already, they repeat for every image. */
	private static array $logged = [];

	/**
	 * @param array $parameters Configuration `imagony.thumbnails`.
	 * @param LoggerInterface|null $logger Receives warnings about problems which do not stop the page.
	 * @param bool $debug Is the application in debug mode? Unsupported formats throw an exception then (see the `strict` option).
	 */
	public function __construct(array $parameters, ?LoggerInterface $logger = null, bool $debug = false) {
		self::$parameters = $parameters;
		self::$logger = $logger;
		self::$debug = $debug;
		self::$logged = [];
	}

	private static function logger(): LoggerInterface {
		return self::$logger ??= new NullLogger();
	}

	/**
	 * Throw when a configured format can not be written? Follows the debug mode unless the `strict` option is set.
	 */
	private static function isStrict(): bool {
		return self::$parameters["strict"] ?? self::$debug;
	}

	/**
	 * Logs a warning about a problem of the installation just once, it would repeat for every image otherwise.
	 */
	private static function warnOnce(string $key, string $message, array $context = []): void {
		if(!isset(self::$logged[$key])) {
			self::$logged[$key] = true;
			self::logger()->warning($message, $context);
		}
	}

	private static function fallback(): string {
		$fallback = self::$parameters["fallback"] ?? null;
		if($fallback === null) {
			return Placeholder::file();
		}

		if(!file_exists($fallback)) {
			self::warnOnce("fallback", "Imagony: the fallback image `{$fallback}` does not exist, set `imagony.thumbnails.fallback` or create the file.", ["fallback" => $fallback]);
		}

		return $fallback;
	}
	
	public static function getParameters(): array {
		return self::$parameters;
	}
	
	public static function getPath(string $path): string {
		$template = self::$parameters["templates"];
		if(isset($template[$path])) {
			return $template[$path]["path"];
		} else {
			return $path;
		}
	}
	
	/**
	 * Cleans thumbnails, including the ones in subfolders (e.g. folders of templates). The folders and dot files (e.g. `.gitkeep`) are kept.
	 * @param bool $dryRun Only finds the thumbnails, nothing is removed.
	 * @return array Array of removed thumbnails filenames (of the found ones when it is a dry run)
	 * @throws \LogicException If the folder of thumbnails is the folder with source images or contains it, everything would be removed.
	 */
	public static function clean(bool $dryRun = false): array {
		$folder = self::$parameters["folder"];
		if(!is_dir($folder)) {
			return [];
		}

		$sources = [self::$parameters["base"]];
		foreach(self::$parameters["templates"] ?? [] as $template) {
			if(isset($template["path"])) {
				$sources[] = self::$parameters["base"].$template["path"];
			}
		}
		$real = realpath($folder);
		foreach($sources as $source) {
			$source = realpath($source);
			if($source !== false and $real !== false and str_starts_with(rtrim($source, "/\\").DIRECTORY_SEPARATOR, rtrim($real, "/\\").DIRECTORY_SEPARATOR)) {
				throw new \LogicException("Refusing to clean `{$folder}`, it is (or contains) the folder with source images `{$source}`. Fix `imagony.thumbnails.folder`.");
			}
		}

		$result = [];
		$files = (new Finder())->files()->in($folder)->ignoreDotFiles(true);
		foreach($files as $file) {
			if($dryRun or unlink($file->getPathname())) {
				$result[] = $file->getPathname();
			}
		}
		return $result;
	}
	
	/**
	 * Generates images from thumbnail templates.
	 * @return void
	 */
	public static function generate() {
		$templates = self::$parameters["templates"];
		foreach($templates as $key => $template) {
			if(array_key_exists("path", $template)) {
				$files = (new Finder())->files()->in(self::$parameters["base"].$template["path"])->depth(0);
				foreach($files as $file) {
					self::create($key, $file->getFilename(), $template["width"] ?? null, $template["height"] ?? null, $template["flags"] ?? [], $template["quality"] ?? null, self::$parameters["formats"] ?? []);
				}
			}
		}
	}
	
	/**
	 * Returns the path and filename of the thumbnail of the image
	 */
	public static function create(string $path, ?string $filename = null, ?int $width = null, ?int $height = null, array $flags = [], ?int $quality = null, array $formats = []): string {
		if(!file_exists(self::$parameters["folder"]) and !mkdir(self::$parameters["folder"], 0755, true)) {
			throw new \RuntimeException("Path `".self::$parameters["folder"]."` does not exist!");
		}
		
		$template = $path;
		if(array_key_exists($template, self::$parameters["templates"])) {
			$path = self::$parameters["base"].self::$parameters["templates"][$template]["path"];
			$width = self::$parameters["templates"][$template]["width"] ?? null;
			$height = self::$parameters["templates"][$template]["height"] ?? null;
			$flags = self::$parameters["templates"][$template]["flags"] ?? [];
			$quality = self::$parameters["templates"][$template]["quality"] ?? null;
		} else {
			$path = self::$parameters["base"].$template;
		}
		
		if(empty($flags)) {
			$flags = self::$parameters["flags"];
		}
		$resizeFlags = ResizeFlag::fromConfigList($flags);
		
		$source = $path.$filename;
		$resized = self::resize($source, $template, $width, $height, $resizeFlags, $quality);

		// the built-in placeholder is served from vendor with its variants, nothing is converted there and its URL is not a path in `base`
		if($resized === Placeholder::file()) {
			return Placeholder::URL;
		}

		if(empty($formats)) {
			$formats = self::$parameters["formats"];
		}
		self::convert($resized, $formats);
		
		return substr($resized, strlen(realpath(self::$parameters["base"])));
	}
	
	/**
	 * Resizes image into a thumbnail
	 */
	protected static function resize(string $filename, string $template = "", ?int $width = null, ?int $height = null, array $flags = [], ?int $quality = null): string {
		if(file_exists($filename) and !isset($width) and !isset($height)) {
			return $filename;
		}
		
		if(Picture::exists($filename) === false) {
			return self::fallback();
		}
		
		$folder = self::$parameters["folder"];
		$temp = "_".str_replace(DIRECTORY_SEPARATOR, "-", $template)."_";
		
		// $template does not have DIRECTORY_SEPARATOR. It means $template is a parameter from the configuration.
		if(strpos($template, DIRECTORY_SEPARATOR) === false) {
			$directory = self::$parameters["folder"].$template.DIRECTORY_SEPARATOR;
			if(is_dir($directory) or mkdir($directory, 0755, true)) {
				$folder = $directory;
				$temp = "_";
			}
		} else {
			$folder .= basename(dirname($filename)).DIRECTORY_SEPARATOR;
			if(!is_dir($folder)) {
				mkdir($folder, 0755, true);
			}
		}
		
		$mask = $width."x".$height."f".ResizeFlag::toMask($flags)."q".$quality;
		$mtime = "mt".filemtime($filename);
		$destination = $folder.pathinfo($filename, PATHINFO_FILENAME).$temp.$mask.$mtime.".".pathinfo($filename, PATHINFO_EXTENSION);
		
		if(file_exists($filename) and !file_exists($destination)) {
			try {
				$image = ImagineFactory::create()->open($filename);
				if($width or $height) {
					Resizer::resize($image, $width, $height, $flags);
				}
				$image->save($destination, isset($quality) ? ["quality" => $quality] : []);
			} catch(ImagineException $e) {
				// SVG is not supported for conversion by Imagine
				if(pathinfo($filename, PATHINFO_EXTENSION) === "svg") {
					return $filename;
				}

				self::logger()->warning("Imagony: the thumbnail of `{$filename}` can not be created, the fallback image is used: {$e->getMessage()}", ["file" => $filename, "destination" => $destination, "exception" => $e]);

				return self::fallback();
			}
		}
		
		return $destination;
	}
	
	/**
	 * Converts thumbnail to other formats
	 * @param string $filename Absolute path of the file
	 * @param array $formats Formats to which convert
	 */
	protected static function convert(string $filename, array $formats = []) {
		foreach($formats as $format) {
			$format = Format::fromConfig($format);

			try {
				// a missing codec is a problem of the PHP installation, it has to be reported instead of silently skipped
				ImagineFactory::assertSupports($format);
			} catch(UnsupportedFormatException|NoDriverException $e) {
				if(self::isStrict()) {
					throw $e;
				}

				self::warnOnce("format.{$format->value}", "Imagony: ".strtoupper($format->value)." thumbnails are skipped. ".$e->getMessage(), ["format" => $format->value, "exception" => $e]);
				continue;
			}

			$converter = match($format) {
				Format::AVIF => new AvifConverter(),
				Format::WEBP => new WebpConverter(),
			};
			$converter->convert($filename, false);
		}
	}
}
