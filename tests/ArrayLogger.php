<?php

declare(strict_types=1);

namespace Imagony\Tests;

use Psr\Log\AbstractLogger;

class ArrayLogger extends AbstractLogger {
	/** @var list<array{level: string, message: string, context: array}> */
	public array $records = [];

	public function log($level, string|\Stringable $message, array $context = []): void {
		$this->records[] = ["level" => (string)$level, "message" => (string)$message, "context" => $context];
	}

	/**
	 * @return list<string> Messages of the level.
	 */
	public function messages(string $level = "warning"): array {
		return array_values(array_map(fn(array $record) => $record["message"], array_filter($this->records, fn(array $record) => $record["level"] === $level)));
	}
}
