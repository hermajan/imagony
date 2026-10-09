<?php

declare(strict_types=1);

// Uses the bundle's own vendor when it has dev dependencies installed, otherwise the vendor of the host project (the bundle is symlinked there).
$autoload = null;
foreach([__DIR__."/../vendor", __DIR__."/../../vendor"] as $vendor) {
	if(is_file($vendor."/autoload.php") and is_dir($vendor."/phpunit/phpunit")) {
		$autoload = $vendor."/autoload.php";
		break;
	}
}
if($autoload === null) {
	throw new RuntimeException("Composer autoloader was not found. Run `composer install`.");
}

/** @var Composer\Autoload\ClassLoader $loader */
$loader = require $autoload;
$loader->addPsr4("Imagony\\", __DIR__."/../src", true);
$loader->addPsr4("Imagony\\Tests\\", __DIR__);
