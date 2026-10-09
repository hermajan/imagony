<?php

declare(strict_types=1);

namespace Imagony\Tests\Symfony;

use Imagony\Symfony\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends TestCase {
	private function process(array $config): array {
		return (new Processor())->processConfiguration(new Configuration(), [$config]);
	}

	public function testDefaults(): void {
		$thumbnails = $this->process([])["thumbnails"];

		$this->assertSame('%kernel.project_dir%/public/', $thumbnails["base"]);
		$this->assertSame('%kernel.project_dir%/public/thumbnails/', $thumbnails["folder"]);
		$this->assertNull($thumbnails["fallback"], "Null = the built-in placeholder served from vendor.");
		$this->assertSame(["shrink_only"], $thumbnails["flags"]);
		$this->assertSame([], $thumbnails["formats"]);
		$this->assertSame([], $thumbnails["templates"]);
		$this->assertNull($thumbnails["strict"], "Null follows the debug mode.");
	}

	public function testStrictAcceptsOnlyBoolean(): void {
		$this->assertTrue($this->process(["thumbnails" => ["strict" => true]])["thumbnails"]["strict"]);
		$this->assertFalse($this->process(["thumbnails" => ["strict" => false]])["thumbnails"]["strict"]);

		$this->expectException(InvalidConfigurationException::class);
		$this->process(["thumbnails" => ["strict" => "yes"]]);
	}

	public function testTemplateDefaults(): void {
		$template = $this->process(["thumbnails" => ["templates" => ["blog" => ["path" => "images/blog/"]]]])["thumbnails"]["templates"]["blog"];

		$this->assertSame("images/blog/", $template["path"]);
		$this->assertNull($template["width"]);
		$this->assertNull($template["height"]);
		$this->assertNull($template["quality"]);
		$this->assertSame([], $template["flags"]);
	}

	public function testFullTemplate(): void {
		$template = $this->process(["thumbnails" => [
			"flags" => ["fit"],
			"formats" => ["webp", "avif"],
			"templates" => ["blog" => ["path" => "images/blog/", "width" => 300, "height" => 200, "quality" => 80, "flags" => ["exact"]]],
		]])["thumbnails"];

		$this->assertSame(["fit"], $template["flags"]);
		$this->assertSame(["webp", "avif"], $template["formats"]);
		$this->assertEquals(["path" => "images/blog/", "width" => 300, "height" => 200, "flags" => ["exact"], "quality" => 80], $template["templates"]["blog"]);
	}

	public function testTemplateRequiresPath(): void {
		$this->expectException(InvalidConfigurationException::class);
		$this->process(["thumbnails" => ["templates" => ["blog" => ["height" => 250]]]]);
	}

	public function testTemplateSizeMustBeInteger(): void {
		$this->expectException(InvalidConfigurationException::class);
		$this->process(["thumbnails" => ["templates" => ["blog" => ["path" => "x/", "width" => "wide"]]]]);
	}

	public function testUnknownOptionIsRejected(): void {
		$this->expectException(InvalidConfigurationException::class);
		$this->process(["thumbnails" => ["unknown" => true]]);
	}
}
