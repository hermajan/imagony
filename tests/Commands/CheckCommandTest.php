<?php

declare(strict_types=1);

namespace Imagony\Tests\Commands;

use Imagony\Commands\CheckCommand;
use Imagony\Diagnostics\Environment;
use Imagony\Tests\{Environments, ImageTestCase};
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CheckCommandTest extends ImageTestCase {
	private string $columns;

	protected function setUp(): void {
		parent::setUp();

		// the output is wrapped to the width of the terminal, long hints would be split
		$this->columns = (string)getenv("COLUMNS");
		putenv("COLUMNS=500");

		mkdir($this->dir."/public/uploads", 0755, true);
		$this->saveImage($this->dir."/public/no-image.png", 10, 10);
	}

	protected function tearDown(): void {
		putenv($this->columns === "" ? "COLUMNS" : "COLUMNS=".$this->columns);
		parent::tearDown();
	}

	private function parameters(array $override = []): array {
		return array_replace([
			"base" => $this->dir."/public/",
			"folder" => $this->dir."/public/thumbnails/",
			"fallback" => $this->dir."/public/no-image.png",
			"flags" => ["shrink_only"],
			"formats" => [],
			"templates" => ["blog" => ["path" => "uploads/"]],
		], $override);
	}

	private function check(Environment $environment, array $parameters): CommandTester {
		$tester = new CommandTester(new CheckCommand($environment, $parameters));
		$tester->execute([], ["decorated" => false]);

		return $tester;
	}

	public function testEverythingIsFine(): void {
		$tester = $this->check(Environments::of(Environments::gd(true, true)), $this->parameters(["formats" => ["webp", "avif"]]));

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("Thumbnails can be generated as configured.", $tester->getDisplay());
		$this->assertStringContainsString("2.1.0", $tester->getDisplay());
	}

	public function testShowsWhatEachDriverCanDo(): void {
		$display = $this->check(Environments::of(Environments::gd(true, false)), $this->parameters())->getDisplay();

		$this->assertMatchesRegularExpression('/imagick\s+no\s/', $display);
		$this->assertMatchesRegularExpression('/gd\s+yes\s+2\.1\.0\s+yes\s+no\s/', $display);
	}

	public function testUnsupportedConfiguredFormatFailsWithTheFix(): void {
		$tester = $this->check(Environments::of(Environments::gd(true, false)), $this->parameters(["formats" => ["webp", "avif"]]));

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString("`imagony.thumbnails.formats` contains AVIF. AVIF can not be written by any available PHP image driver", $tester->getDisplay());
		$this->assertStringContainsString("--with-avif", $tester->getDisplay());
		$this->assertStringContainsString("libavif-dev", $tester->getDisplay());
		$this->assertStringNotContainsString("contains WEBP", $tester->getDisplay());
	}

	public function testUnsupportedFormatIsNotAProblemWhenNotConfigured(): void {
		$tester = $this->check(Environments::of(Environments::gd(true, false)), $this->parameters(["formats" => ["webp"]]));

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
	}

	public function testMissingDriversFail(): void {
		$tester = $this->check(Environments::none(), $this->parameters());

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString("ext-gd or ext-imagick", $tester->getDisplay());
	}

	public function testUnknownFormatFails(): void {
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["formats" => ["gif"]]));

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString("imagony.thumbnails.formats", $tester->getDisplay());
		$this->assertStringContainsString("gif", $tester->getDisplay());
	}

	public function testMissingFallbackIsOnlyAWarning(): void {
		unlink($this->dir."/public/no-image.png");
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters());

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("imagony.thumbnails.fallback", $tester->getDisplay());
		$this->assertStringContainsString("see the warnings", $tester->getDisplay());
	}

	public function testBuiltInPlaceholderNeedsNoFileInTheProject(): void {
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["fallback" => null]));

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("Thumbnails can be generated as configured.", $tester->getDisplay());
		$this->assertStringNotContainsString("fallback", $tester->getDisplay());
	}

	public function testMissingOwnFallbackSuggestsTheBuiltInPlaceholder(): void {
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["fallback" => $this->dir."/public/own.png"]));

		$this->assertStringContainsString("imagony.thumbnails.fallback", $tester->getDisplay());
		$this->assertStringContainsString("remove the option to use the built-in placeholder", preg_replace('/\s+/', ' ', $tester->getDisplay()));
		$this->assertStringNotContainsString("assets:install", $tester->getDisplay());
	}

	public function testFallbackOutsideBaseIsAWarning(): void {
		$this->saveImage($this->dir."/outside/no-image.png", 10, 10);
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["fallback" => $this->dir."/outside/no-image.png"]));

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("is not inside `imagony.thumbnails.base`", preg_replace('/\s+/', ' ', $tester->getDisplay()), "The block wraps long lines.");
	}

	public function testFallbackInsideBaseIsFine(): void {
		$this->saveImage($this->dir."/public/bundles/imagony/no-image.png", 10, 10);
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["fallback" => $this->dir."/public/bundles/imagony/no-image.png"]));

		$this->assertStringContainsString("Thumbnails can be generated as configured.", $tester->getDisplay());
	}

	public function testMissingTemplateFolderIsOnlyAWarning(): void {
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["templates" => ["gallery" => ["path" => "gallery/"]]]));

		$this->assertSame(Command::SUCCESS, $tester->getStatusCode());
		$this->assertStringContainsString("Template `gallery`", $tester->getDisplay());
	}

	public function testMissingBaseFails(): void {
		$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["base" => $this->dir."/nowhere/"]));

		$this->assertSame(Command::FAILURE, $tester->getStatusCode());
		$this->assertStringContainsString("imagony.thumbnails.base", $tester->getDisplay());
	}

	public function testUnwritableFolderFails(): void {
		if(function_exists("posix_geteuid") and posix_geteuid() === 0) {
			$this->markTestSkipped("Root can write everywhere.");
		}
		mkdir($this->dir."/public/locked", 0555);

		try {
			$tester = $this->check(Environments::of(Environments::gd()), $this->parameters(["folder" => $this->dir."/public/locked/thumbs/"]));

			$this->assertSame(Command::FAILURE, $tester->getStatusCode());
			$this->assertStringContainsString("can not be created", $tester->getDisplay());
		} finally {
			chmod($this->dir."/public/locked", 0755);
		}
	}
}
