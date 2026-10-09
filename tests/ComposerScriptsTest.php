<?php

declare(strict_types=1);

namespace Imagony\Tests;

use PHPUnit\Framework\TestCase;

class ComposerScriptsTest extends TestCase {
	private function composer(): array {
		return json_decode(file_get_contents(__DIR__."/../composer.json"), true, flags: JSON_THROW_ON_ERROR);
	}

	/**
	 * Composer runs scripts of the root package only, so scripts of the bundle would never run in a project
	 * (and a script pointing at a moved class went unnoticed once). The bundle works with its defaults instead.
	 */
	public function testBundleDoesNotRelyOnComposerScripts(): void {
		$this->assertArrayNotHasKey("scripts", $this->composer());
	}

	public function testAutoloadMatchesSourceFolder(): void {
		$this->assertSame(["Imagony\\" => "src/"], $this->composer()["autoload"]["psr-4"]);
	}

	public function testRequiresPackagesUsedBySources(): void {
		$require = array_keys($this->composer()["require"]);

		foreach(["imagine/imagine", "symfony/finder", "symfony/yaml", "symfony/config", "symfony/dependency-injection", "symfony/http-kernel", "twig/twig", "psr/log"] as $package) {
			$this->assertContains($package, $require);
		}
	}

	/**
	 * Composer can not require "GD or Imagick", the suggestions are the only hint shown during the installation.
	 */
	public function testSuggestsImageExtensionsAndConsole(): void {
		$suggest = $this->composer()["suggest"];

		foreach(["ext-gd", "ext-imagick", "symfony/console"] as $package) {
			$this->assertArrayHasKey($package, $suggest);
			$this->assertNotSame("", $suggest[$package]);
		}
		$this->assertStringContainsString("imagony:check", $suggest["symfony/console"]);
		$this->assertStringContainsString("--with-avif", $suggest["ext-gd"]);
	}

	public function testConsoleIsNotRequiredButTheCommandNeedsIt(): void {
		$composer = $this->composer();

		$this->assertArrayNotHasKey("symfony/console", $composer["require"]);
		$this->assertArrayNotHasKey("ext-gd", $composer["require"], "Imagick is an alternative to GD, so neither is required.");
	}
}
