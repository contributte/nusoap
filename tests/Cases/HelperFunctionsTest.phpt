<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test timestamp_to_iso8601 with UTC
Toolkit::test(static function (): void {
	$timestamp = mktime(12, 30, 45, 6, 15, 2023);

	$iso = timestamp_to_iso8601($timestamp, true);
	Assert::type('string', $iso);
	Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$~', $iso);
});

// Test timestamp_to_iso8601 with local time
Toolkit::test(static function (): void {
	$timestamp = mktime(12, 30, 45, 6, 15, 2023);

	$isoLocal = timestamp_to_iso8601($timestamp, false);
	Assert::type('string', $isoLocal);
	Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$~', $isoLocal);
});

// Test iso8601_to_timestamp with UTC
Toolkit::test(static function (): void {
	$isoString = '2023-06-15T12:30:45Z';
	$ts = iso8601_to_timestamp($isoString);

	Assert::type('int', $ts);

	$backToIso = timestamp_to_iso8601($ts, true);
	Assert::same($isoString, $backToIso);
});

// Test iso8601_to_timestamp with positive timezone offset
Toolkit::test(static function (): void {
	$isoWithOffset = '2023-06-15T12:30:45+02:00';
	$ts = iso8601_to_timestamp($isoWithOffset);

	Assert::type('int', $ts);
});

// Test iso8601_to_timestamp with negative timezone offset
Toolkit::test(static function (): void {
	$isoNegOffset = '2023-06-15T12:30:45-05:00';
	$ts = iso8601_to_timestamp($isoNegOffset);

	Assert::type('int', $ts);
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
});

// Test round-trip conversions
Toolkit::test(static function (): void {
	$dates = [
		'2020-01-01T00:00:00Z',
		'2023-12-31T23:59:59Z',
		'2000-06-15T12:00:00Z',
	];

	foreach ($dates as $date) {
		$ts = iso8601_to_timestamp($date);
		Assert::type('int', $ts);

		$backToIso = timestamp_to_iso8601($ts, true);
		Assert::same($date, $backToIso);
	}
});

// Test epoch timestamp
Toolkit::test(static function (): void {
	$epochStart = timestamp_to_iso8601(0, true);
	Assert::same('1970-01-01T00:00:00Z', $epochStart);

	$epochTs = iso8601_to_timestamp('1970-01-01T00:00:00Z');
	Assert::same(0, $epochTs);
});
