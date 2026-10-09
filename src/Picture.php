<?php
namespace Imagony;

class Picture {
	/**
	 * Checks if the given file exists and is a regular file.
	 * @param string $filename The path to the file to check.
	 * @return bool Returns true if the file exists and is a regular file, false otherwise.
	 */
	public static function exists(string $filename): bool {
		return file_exists($filename) and is_file($filename);
	}

	/**
	 * Determines if the given file is valid by checking its existence and verifying it can be processed as an image.
	 * @param string $filename The path to the file to validate.
	 * @return bool Returns true if the file exists and can be processed as an image, false otherwise.
	 */
	public static function isValid(string $filename): bool {
		$exists = Picture::exists($filename);
		if($exists === true) {
			try {
				ImagineFactory::create()->open($filename);
			} catch(\Exception $e) {
				return false;
			}
		} else {
			return false;
		}

		return true;
	}
}
