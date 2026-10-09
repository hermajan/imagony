<?php

namespace Imagony\Converters;

use Imagony\Exception\{NoDriverException, UnsupportedFormatException};
use Imagony\ImagineFactory;
use Symfony\Component\Finder\Finder;

abstract class Converter {
	protected string $extension = "";

	protected int $quality = -1;

	protected ?Format $format = null;

	/**
	 * Converts image to some format.
	 * @param string $path
	 * @param bool $replace
	 * @return bool True if the image is converted, false otherwise (e.g. the image can not be read).
	 * @throws UnsupportedFormatException If PHP can not write the format at all, that is a problem of the installation, not of the image.
	 */
	public function convert(string $path, bool $replace = false): bool {
		if(file_exists($path)) {
			if($this->extension !== pathinfo($path, PATHINFO_EXTENSION)) {
				try {
					if($replace === true) {
						$this->save($path);
					} else {
						if(!file_exists($path.".".$this->extension)) {
							$this->save($path);
						} else {
							return false;
						}
					}
				} catch(UnsupportedFormatException|NoDriverException $e) {
					throw $e;
				} catch(\Exception $e) {
					return false;
				}
			} else {
				return false;
			}

			return true;
		}

		return false;
	}

	/**
	 * Converts folder with images to some format.
	 * @param string $path Path to file.
	 * @param bool $replace True if a converted file should be replaced, false otherwise.
	 * @param array $masks File name masks (globs) where search for files. See <https://symfony.com/doc/current/components/finder.html#file-name>.
	 */
	public function convertFolder(string $path, bool $replace = false, array $masks = ["*"]): void {
		$finder = (new Finder())->files()->in($path)->name($masks);
		foreach($finder as $file) {
			$this->convert($file->getPathname(), $replace);
		}
	}

	/**
	 * Saves the modified image to a specified file.
	 * @param string $filename The name of the file where the image will be saved.
	 * @throws \Imagine\Exception\Exception
	 * @throws \RuntimeException If no Imagine driver can write the format.
	 */
	public function save(string $filename) {
		$options = $this->quality >= 0 ? ["quality" => $this->quality] : [];
		ImagineFactory::create($this->format)->open($filename)->save($filename.".".$this->extension, $options);
	}
}
