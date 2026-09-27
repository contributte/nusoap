<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test parseCookie parses a Set-Cookie value with all attributes
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same([
		'name' => 'SID',
		'value' => 'abc123',
		'domain' => 'example.com',
		'path' => '/app',
		'expires' => 'Wed, 01 Jan 2031 00:00:00 GMT',
		'secure' => true,
	], $http->parseCookie('SID=abc123; path=/app; domain=example.com; expires=Wed, 01 Jan 2031 00:00:00 GMT; secure'));
});

// Test parseCookie defaults path to / and keeps "=" inside the value
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same([
		'name' => 'token',
		'value' => 'a=b=c',
		'domain' => '',
		'path' => '/',
		'expires' => '',
		'secure' => false,
	], $http->parseCookie('token=a=b=c'));
});

// Test parseCookie returns an empty array for a cookie without name=value
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same([], $http->parseCookie('garbage'));
	Assert::same([], $http->parseCookie('=value'));
});

// Test getCookiesForRequest sends only cookies matching domain, path, expiry and security
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://api.example.com/app/service.php');

	$cookies = [
		['name' => 'plain', 'value' => '1'],
		['name' => 'domain', 'value' => '2', 'domain' => 'example.com'],
		['name' => 'otherDomain', 'value' => '3', 'domain' => 'example.org'],
		['name' => 'path', 'value' => '4', 'path' => '/app'],
		['name' => 'otherPath', 'value' => '5', 'path' => '/admin'],
		['name' => 'expired', 'value' => '6', 'expires' => 'Thu, 01 Jan 1970 00:00:01 GMT'],
		['name' => 'future', 'value' => '7', 'expires' => gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT'],
		['name' => 'secure', 'value' => '8', 'secure' => true],
		'not-an-array',
	];

	Assert::same('plain=1; domain=2; path=4; future=7; ', $http->getCookiesForRequest($cookies));
	Assert::same('plain=1; domain=2; path=4; future=7; secure=8; ', $http->getCookiesForRequest($cookies, true));
});

// Test getCookiesForRequest with no cookies returns an empty string
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same('', $http->getCookiesForRequest(null));
	Assert::same('', $http->getCookiesForRequest([]));
});
