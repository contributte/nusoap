<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test timestamp_to_iso8601
$timestamp = mktime(12, 30, 45, 6, 15, 2023);

// Test with UTC
$iso = timestamp_to_iso8601($timestamp, true);
Assert::type('string', $iso);
Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$~', $iso);

// Test without UTC (local time)
$isoLocal = timestamp_to_iso8601($timestamp, false);
Assert::type('string', $isoLocal);
Assert::match('~^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$~', $isoLocal);


// Test iso8601_to_timestamp
$isoString = '2023-06-15T12:30:45Z';
$ts = iso8601_to_timestamp($isoString);
Assert::type('int', $ts);

// Verify round-trip conversion
$backToIso = timestamp_to_iso8601($ts, true);
Assert::same($isoString, $backToIso);


// Test iso8601_to_timestamp with timezone offset
$isoWithOffset = '2023-06-15T12:30:45+02:00';
$tsWithOffset = iso8601_to_timestamp($isoWithOffset);
Assert::type('int', $tsWithOffset);


// Test iso8601_to_timestamp with negative timezone offset
$isoNegOffset = '2023-06-15T12:30:45-05:00';
$tsNegOffset = iso8601_to_timestamp($isoNegOffset);
Assert::type('int', $tsNegOffset);


// Test iso8601_to_timestamp with invalid format
$invalid = iso8601_to_timestamp('not-a-valid-date');
Assert::false($invalid);

$invalid2 = iso8601_to_timestamp('2023-06-15');
Assert::false($invalid2);


// Test iso8601_to_timestamp with milliseconds
$isoWithMs = '2023-06-15T12:30:45.123Z';
$tsWithMs = iso8601_to_timestamp($isoWithMs);
Assert::type('int', $tsWithMs);


// Test various date formats
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


// Test edge cases
$epochStart = timestamp_to_iso8601(0, true);
Assert::same('1970-01-01T00:00:00Z', $epochStart);

$epochTs = iso8601_to_timestamp('1970-01-01T00:00:00Z');
Assert::same(0, $epochTs);


echo "All helper function tests passed!\n";
