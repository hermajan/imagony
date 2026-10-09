<?php

declare(strict_types=1);

namespace Imagony\Tests\Resizing;

use Imagony\Resizing\ResizeFlag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ResizeFlagTest extends TestCase {
	public static function configValues(): iterable {
		yield "snake case" => ["shrink_only", ResizeFlag::SHRINK_ONLY];
		yield "constant name" => ["SHRINK_ONLY", ResizeFlag::SHRINK_ONLY];
		yield "camel case" => ["ShrinkOnly", ResizeFlag::SHRINK_ONLY];
		yield "legacy Nette constant" => ['Nette\Utils\Image::SHRINK_ONLY', ResizeFlag::SHRINK_ONLY];
		yield "fit" => ["fit", ResizeFlag::FIT];
		yield "stretch" => ["stretch", ResizeFlag::STRETCH];
		yield "fill" => ["fill", ResizeFlag::FILL];
		yield "exact" => ["exact", ResizeFlag::EXACT];
		yield "int 0" => [0, ResizeFlag::FIT];
		yield "int 1" => [1, ResizeFlag::SHRINK_ONLY];
		yield "int 2" => [2, ResizeFlag::STRETCH];
		yield "int 4" => [4, ResizeFlag::FILL];
		yield "int 8" => [8, ResizeFlag::EXACT];
		yield "enum" => [ResizeFlag::EXACT, ResizeFlag::EXACT];
	}

	#[DataProvider("configValues")]
	public function testFromConfig(string|int|ResizeFlag $value, ResizeFlag $expected): void {
		$this->assertSame($expected, ResizeFlag::fromConfig($value));
	}

	public static function invalidValues(): iterable {
		yield "unknown string" => ["crop"];
		yield "empty string" => [""];
		yield "unknown int" => [3];
		yield "negative int" => [-1];
	}

	#[DataProvider("invalidValues")]
	public function testFromConfigRejectsUnknownValue(string|int $value): void {
		$this->expectException(\InvalidArgumentException::class);
		ResizeFlag::fromConfig($value);
	}

	public function testFromConfigListReindexesAndMixesTypes(): void {
		$this->assertSame(
			[ResizeFlag::SHRINK_ONLY, ResizeFlag::EXACT, ResizeFlag::FILL],
			ResizeFlag::fromConfigList([3 => "shrink_only", 7 => ResizeFlag::EXACT, 9 => 4])
		);
	}

	public function testToMask(): void {
		$this->assertSame(0, ResizeFlag::toMask([]));
		$this->assertSame(0, ResizeFlag::toMask([ResizeFlag::FIT]));
		$this->assertSame(1, ResizeFlag::toMask([ResizeFlag::SHRINK_ONLY]));
		$this->assertSame(9, ResizeFlag::toMask([ResizeFlag::SHRINK_ONLY, ResizeFlag::EXACT]));
		$this->assertSame(9, ResizeFlag::toMask([ResizeFlag::EXACT, ResizeFlag::SHRINK_ONLY, ResizeFlag::EXACT]));
	}

	/**
	 * The mask is part of generated thumbnail filenames, so the values must stay compatible with the former Nette constants.
	 */
	public function testValuesAreNetteCompatible(): void {
		$this->assertSame(
			["FIT" => 0, "SHRINK_ONLY" => 1, "STRETCH" => 2, "FILL" => 4, "EXACT" => 8],
			array_column(array_map(fn(ResizeFlag $flag) => ["name" => $flag->name, "value" => $flag->value], ResizeFlag::cases()), "value", "name")
		);
	}
}
