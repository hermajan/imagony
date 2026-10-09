<?php

declare(strict_types=1);

namespace Imagony\Tests\Converters;

use Imagony\Converters\Format;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FormatTest extends TestCase {
	public static function configValues(): iterable {
		yield "webp" => ["webp", Format::WEBP];
		yield "WEBP" => ["WEBP", Format::WEBP];
		yield "Webp" => ["Webp", Format::WEBP];
		yield "avif" => ["avif", Format::AVIF];
		yield "IMAGETYPE_WEBP" => [IMAGETYPE_WEBP, Format::WEBP];
		yield "IMAGETYPE_AVIF" => [IMAGETYPE_AVIF, Format::AVIF];
		yield "enum" => [Format::AVIF, Format::AVIF];
	}

	#[DataProvider("configValues")]
	public function testFromConfig(string|int|Format $value, Format $expected): void {
		$this->assertSame($expected, Format::fromConfig($value));
	}

	public static function unsupportedValues(): iterable {
		yield "jpg" => ["jpg"];
		yield "empty" => [""];
		yield "IMAGETYPE_PNG" => [IMAGETYPE_PNG];
	}

	#[DataProvider("unsupportedValues")]
	public function testFromConfigRejectsUnsupportedFormat(string|int $value): void {
		$this->expectException(\InvalidArgumentException::class);
		Format::fromConfig($value);
	}

	public function testValuesAreFileExtensions(): void {
		$this->assertSame("webp", Format::WEBP->value);
		$this->assertSame("avif", Format::AVIF->value);
	}
}
