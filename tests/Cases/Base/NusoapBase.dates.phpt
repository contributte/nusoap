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

// Test timestamp is converted to UTC regardless of the default timezone
Toolkit::test(static function (): void {
	$timezone = date_default_timezone_get();
	date_default_timezone_set('Europe/Prague');

	try {
		Assert::same('1970-01-01T00:00:00Z', timestamp_to_iso8601(0));
		Assert::same('2023-06-15T12:30:45Z', timestamp_to_iso8601(gmmktime(12, 30, 45, 6, 15, 2023)));
		Assert::same('2023-06-15T14:30:45+02:00', timestamp_to_iso8601(gmmktime(12, 30, 45, 6, 15, 2023), false));
	} finally {
		date_default_timezone_set($timezone);
	}
});

// Test UTC round trip
Toolkit::test(static function (): void {
	$timestamp = gmmktime(23, 59, 59, 12, 31, 2023);

	Assert::same($timestamp, iso8601_to_timestamp(timestamp_to_iso8601($timestamp)));
	Assert::same($timestamp, iso8601_to_timestamp(timestamp_to_iso8601($timestamp, false)));
});
