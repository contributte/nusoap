<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test encoding is read from the charset parameter of a content type
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('UTF-8', $base->getEncodingFromContentType('text/xml; charset=utf-8'));
	Assert::same('UTF-8', $base->getEncodingFromContentType('text/xml;charset="UTF-8"'));
	Assert::same('UTF-8', $base->getEncodingFromContentType('text/xml; charset=\"utf-8\"'));
	Assert::same('UTF-8', $base->getEncodingFromContentType('text/xml; charset=utf-8; action="urn:x"'));
	Assert::same('UTF-8', $base->getEncodingFromContentType('text/xml; format=flowed; Charset = UTF-8'));
	Assert::same('US-ASCII', $base->getEncodingFromContentType('text/xml; charset=us-ascii'));
	Assert::same('ISO-8859-1', $base->getEncodingFromContentType('text/xml; charset=ISO-8859-1'));
});

// Test unsupported charsets fall back to US-ASCII and missing ones to ISO-8859-1
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('US-ASCII', $base->getEncodingFromContentType('text/xml; charset=windows-1250'));
	Assert::same('ISO-8859-1', $base->getEncodingFromContentType('text/xml'));
	Assert::same('ISO-8859-1', $base->getEncodingFromContentType('text/xml; format=flowed'));
	Assert::same('ISO-8859-1', $base->getEncodingFromContentType(''));
});
