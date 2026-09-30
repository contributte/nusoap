<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test a dateTime without timezone is read as UTC
Toolkit::test(static function (): void {
	Assert::same(gmmktime(12, 30, 45, 6, 15, 2023), iso8601_to_timestamp('2023-06-15T12:30:45'));
	Assert::same(gmmktime(12, 30, 45, 6, 15, 2023), iso8601_to_timestamp('2023-06-15T12:30:45.250'));
});

// Test timezone offsets with and without colon
Toolkit::test(static function (): void {
	$utc = gmmktime(10, 0, 0, 6, 15, 2023);

	Assert::same($utc, iso8601_to_timestamp('2023-06-15T12:00:00+02:00'));
	Assert::same($utc, iso8601_to_timestamp('2023-06-15T12:00:00+0200'));
	Assert::same($utc, iso8601_to_timestamp('2023-06-15T09:30:00-00:30'));
});
