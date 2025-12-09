<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test timestamp_to_iso8601 with UTC returns proper format
Toolkit::test(static function (): void {
	$timestamp = time();

	$iso = timestamp_to_iso8601($timestamp, true);
	Assert::type('string', $iso);
	Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$~', $iso);
});

// Test timestamp_to_iso8601 with local time returns proper format
Toolkit::test(static function (): void {
	$timestamp = time();

	$isoLocal = timestamp_to_iso8601($timestamp, false);
	Assert::type('string', $isoLocal);
	Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$~', $isoLocal);
});

// Test iso8601_to_timestamp with UTC
Toolkit::test(static function (): void {
	$isoString = '2023-06-15T12:30:45Z';
	$ts = iso8601_to_timestamp($isoString);

	Assert::type('int', $ts);
	Assert::true($ts > 0);
});

// Test iso8601_to_timestamp with positive timezone offset
Toolkit::test(static function (): void {
	$isoWithOffset = '2023-06-15T12:30:45+02:00';
	$ts = iso8601_to_timestamp($isoWithOffset);

	Assert::type('int', $ts);
	Assert::true($ts > 0);
});

// Test iso8601_to_timestamp with negative timezone offset
Toolkit::test(static function (): void {
	$isoNegOffset = '2023-06-15T12:30:45-05:00';
	$ts = iso8601_to_timestamp($isoNegOffset);

	Assert::type('int', $ts);
	Assert::true($ts > 0);
});

// Test iso8601_to_timestamp with invalid format
Toolkit::test(static function (): void {
	Assert::false(iso8601_to_timestamp('not-a-valid-date'));
	Assert::false(iso8601_to_timestamp('2023-06-15'));
});

// Test iso8601_to_timestamp with milliseconds
Toolkit::test(static function (): void {
	$isoWithMs = '2023-06-15T12:30:45.123Z';
	$ts = iso8601_to_timestamp($isoWithMs);

	Assert::type('int', $ts);
	Assert::true($ts > 0);
});

// Test epoch timestamp parsing
Toolkit::test(static function (): void {
	$epochTs = iso8601_to_timestamp('1970-01-01T00:00:00Z');
	Assert::same(0, $epochTs);
});

// Test timestamp_to_iso8601 returns valid format for epoch
Toolkit::test(static function (): void {
	$epochStart = timestamp_to_iso8601(0, true);
	Assert::type('string', $epochStart);
	Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$~', $epochStart);
});

// Test timezone offset handling - positive offset subtracts hours
Toolkit::test(static function (): void {
	$utc = iso8601_to_timestamp('2023-06-15T12:00:00Z');
	$plus2 = iso8601_to_timestamp('2023-06-15T14:00:00+02:00');

	// Both should represent the same UTC moment
	Assert::same($utc, $plus2);
});

// Test timezone offset handling - negative offset adds hours
Toolkit::test(static function (): void {
	$utc = iso8601_to_timestamp('2023-06-15T12:00:00Z');
	$minus5 = iso8601_to_timestamp('2023-06-15T07:00:00-05:00');

	// Both should represent the same UTC moment
	Assert::same($utc, $minus5);
});
