<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\FakeHttpStream;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/FakeHttpStream.php';

function digestResponse(string $realm, string $nonce, string $nc, string $cnonce, string $qop, string $uri = '/service.php'): string
{
	return md5(md5('user:' . $realm . ':pass') . ':' . $nonce . ':' . $nc . ':' . $cnonce . ':' . $qop . ':' . md5('POST:' . $uri));
}

// Test digest header keeps opaque outside of the uri value
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', [
		'realm' => 'realm',
		'nonce' => 'n',
		'qop' => 'auth',
		'opaque' => 'op',
	]);

	Assert::same(
		'Digest username="user", realm="realm", nonce="n", uri="/service.php", opaque="op", cnonce="n", nc=00000001, qop=auth, response="' . digestResponse('realm', 'n', '00000001', 'n', 'auth') . '"',
		$http->outgoing_headers['Authorization']
	);
});

// Test digest nonce count increases when the nonce is reused
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', ['realm' => 'realm', 'nonce' => 'n', 'qop' => 'auth']);
	Assert::same(1, $http->digestRequest['nc']);

	$http->setCredentials('user', 'pass', 'digest', $http->digestRequest);
	Assert::same(2, $http->digestRequest['nc']);
	Assert::contains('nc=00000002', $http->outgoing_headers['Authorization']);
});

// Test digest nonce count is hexadecimal in both the header and the response hash
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', ['realm' => 'realm', 'nonce' => 'n', 'qop' => 'auth', 'nc' => 9]);

	Assert::contains('nc=0000000a', $http->outgoing_headers['Authorization']);
	Assert::contains('response="' . digestResponse('realm', 'n', '0000000a', 'n', 'auth') . '"', $http->outgoing_headers['Authorization']);
});

// Test digest picks auth from a list of offered qop values
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', ['realm' => 'realm', 'nonce' => 'n', 'qop' => 'auth,auth-int']);

	Assert::contains(', qop=auth, response="' . digestResponse('realm', 'n', '00000001', 'n', 'auth') . '"', $http->outgoing_headers['Authorization']);
});

// Test digest without qop sends neither qop, nc nor cnonce (RFC 2069)
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', ['realm' => 'realm', 'nonce' => 'n']);

	$response = md5(md5('user:realm:pass') . ':n:' . md5('POST:/service.php'));
	Assert::same(
		'Digest username="user", realm="realm", nonce="n", uri="/service.php", response="' . $response . '"',
		$http->outgoing_headers['Authorization']
	);
});

// Test client answers a digest challenge and retries the request
Toolkit::test(static function (): void {
	$requests = new ArrayObject();
	$handler = static function (string $request) use ($requests): string {
		$requests[] = $request;
		if (count($requests) === 1) {
			return "HTTP/1.1 401 Unauthorized\r\n"
				. "WWW-Authenticate: Digest realm=\"test, realm\", qop=\"auth,auth-int\", nonce=\"abc\", opaque=\"xyz\", algorithm=MD5\r\n"
				. "Content-Length: 0\r\n\r\n";
		}

		$body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
			. '<SOAP-ENV:Body><ns1:pingResponse xmlns:ns1="urn:x"><return>pong</return></ns1:pingResponse></SOAP-ENV:Body>'
			. '</SOAP-ENV:Envelope>';

		return "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body;
	};

	$client = new nusoap_client('http://soap.invalid/service.php');
	$client->setCredentials('user', 'pass', 'digest');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service.php', $handler);

	$result = $client->call('ping', [], 'urn:x', 'urn:x#ping');

	Assert::false($client->getError());
	Assert::same('pong', $result);
	Assert::count(2, $requests);
	Assert::notContains('Authorization:', $requests[0]);
	Assert::contains(
		'Authorization: Digest username="user", realm="test, realm", nonce="abc", uri="/service.php", opaque="xyz", cnonce="abc", nc=00000001, qop=auth, response="'
		. digestResponse('test, realm', 'abc', '00000001', 'abc', 'auth') . '"',
		$requests[1]
	);
});
