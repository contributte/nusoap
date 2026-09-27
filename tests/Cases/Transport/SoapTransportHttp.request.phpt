<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test setURL parses the URL and sets default ports and the Host header
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php?wsdl');

	Assert::same('http', $http->scheme);
	Assert::same('example.com', $http->host);
	Assert::same(80, $http->port);
	Assert::same('/service.php?wsdl', $http->path);
	Assert::same('/service.php?wsdl', $http->uri);
	Assert::same('example.com', $http->outgoing_headers['Host']);

	$https = new soap_transport_http('https://example.com/service.php');
	Assert::same(443, $https->port);

	$custom = new soap_transport_http('http://example.com:8080/service.php');
	Assert::same(8080, $custom->port);
	Assert::same('example.com:8080', $custom->outgoing_headers['Host']);
});

// Test User-Agent header is set from library title and version
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::match('NuSOAP/%a% (%a%)', $http->outgoing_headers['User-Agent']);
});

// Test setHeader and unsetHeader
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	$http->setHeader('X-Custom', 'value');
	Assert::same('value', $http->outgoing_headers['X-Custom']);

	$http->unsetHeader('X-Custom');
	Assert::false(isset($http->outgoing_headers['X-Custom']));

	// Unsetting a missing header is a no-op
	$http->unsetHeader('X-Missing');
	Assert::false(isset($http->outgoing_headers['X-Missing']));
});

// Test setSOAPAction quotes the value and setContentType appends the charset
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	$http->setSOAPAction('urn:Service#method');
	Assert::same('"urn:Service#method"', $http->outgoing_headers['SOAPAction']);

	$http->setContentType('text/xml', 'UTF-8');
	Assert::same('text/xml; charset=UTF-8', $http->outgoing_headers['Content-Type']);

	$http->setContentType('application/octet-stream');
	Assert::same('application/octet-stream', $http->outgoing_headers['Content-Type']);
});

// Test basic credentials strip colons from the username (RFC 2617)
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');
	$http->setCredentials('us:er', 'pa:ss');

	Assert::same('Basic ' . base64_encode('user:pa:ss'), $http->outgoing_headers['Authorization']);
	Assert::same('us:er', $http->username);
	Assert::same('pa:ss', $http->password);
});

// Test digest credentials compute the RFC 2617 response with qop=auth
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', [
		'realm' => 'realm',
		'nonce' => 'nonce123',
		'qop' => 'auth',
	]);

	$ha1 = md5('user:realm:pass');
	$ha2 = md5('POST:/service.php');
	$response = md5($ha1 . ':nonce123:00000001:nonce123:auth:' . $ha2);

	Assert::same(
		'Digest username="user", realm="realm", nonce="nonce123", uri="/service.php", cnonce="nonce123", nc=00000001, qop="auth", response="' . $response . '"',
		$http->outgoing_headers['Authorization']
	);
	Assert::same('digest', $http->authtype);
	Assert::same(1, $http->digestRequest['nc']);
});

// Test digest credentials without qop use the simplified RFC 2069 response
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest', [
		'realm' => 'realm',
		'nonce' => 'nonce123',
		'qop' => '',
	]);

	$ha1 = md5('user:realm:pass');
	$ha2 = md5('POST:/service.php');

	Assert::contains('response="' . md5($ha1 . ':nonce123:' . $ha2) . '"', $http->outgoing_headers['Authorization']);
});

// Test digest credentials without a nonce do not set an Authorization header yet
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->setCredentials('user', 'pass', 'digest');

	Assert::false(isset($http->outgoing_headers['Authorization']));
	Assert::same('digest', $http->authtype);
});

// Test certificate and ntlm credentials do not set an Authorization header
Toolkit::test(static function (): void {
	$cert = ['sslcertfile' => '/path/cert.pem', 'sslkeyfile' => '/path/key.pem'];

	$http = new soap_transport_http('https://example.com/');
	$http->setCredentials('', '', 'certificate', [], $cert);
	Assert::false(isset($http->outgoing_headers['Authorization']));
	Assert::same($cert, $http->certRequest);

	$http = new soap_transport_http('http://example.com/');
	$http->setCredentials('user', 'pass', 'ntlm');
	Assert::false(isset($http->outgoing_headers['Authorization']));
	Assert::same('ntlm', $http->authtype);
});

// Test setProxy stores proxy settings and a basic Proxy-Authorization header
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');
	$http->setProxy('proxy.local', '3128', 'puser', 'ppass');

	Assert::same([
		'host' => 'proxy.local',
		'port' => '3128',
		'username' => 'puser',
		'password' => 'ppass',
		'authtype' => 'basic',
	], $http->proxy);
	Assert::same(' Basic ' . base64_encode('puser:ppass'), $http->outgoing_headers['Proxy-Authorization']);

	// Empty host removes the proxy authorization
	$http->setProxy('', '');
	Assert::false(isset($http->outgoing_headers['Proxy-Authorization']));
});

// Test setProxy with ntlm or without credentials does not set Proxy-Authorization
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');
	$http->setProxy('proxy.local', '3128');
	Assert::false(isset($http->outgoing_headers['Proxy-Authorization']));

	$http->setProxy('proxy.local', '3128', 'puser', 'ppass', 'ntlm');
	Assert::false(isset($http->outgoing_headers['Proxy-Authorization']));
});

// Test io_method chooses curl for https, ntlm and forced curl, socket otherwise
Toolkit::test(static function (): void {
	Assert::same('socket', (new soap_transport_http('http://example.com/'))->io_method());
	Assert::same('curl', (new soap_transport_http('https://example.com/'))->io_method());
	Assert::same('curl', (new soap_transport_http('http://example.com/', null, true))->io_method());

	$ntlm = new soap_transport_http('http://example.com/');
	$ntlm->setCredentials('user', 'pass', 'ntlm');
	Assert::same('curl', $ntlm->io_method());

	$ntlmProxy = new soap_transport_http('http://example.com/');
	$ntlmProxy->setProxy('proxy.local', '3128', 'u', 'p', 'ntlm');
	Assert::same('curl', $ntlmProxy->io_method());

	Assert::same('unknown', (new soap_transport_http('ftp://example.com/'))->io_method());
});

// Test setEncoding switches to HTTP/1.1 and closes the connection
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');
	$http->setEncoding();

	Assert::same('1.1', $http->protocol_version);
	Assert::same('gzip, deflate', $http->outgoing_headers['Accept-Encoding']);
	Assert::same('close', $http->outgoing_headers['Connection']);
	Assert::false($http->persistentConnection);
	Assert::same('gzip, deflate', $http->encoding);
});

// Test usePersistentConnection sets Keep-Alive unless encoding is in use
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');
	Assert::true($http->usePersistentConnection());
	Assert::same('1.1', $http->protocol_version);
	Assert::same('Keep-Alive', $http->outgoing_headers['Connection']);
	Assert::true($http->persistentConnection);

	$encoded = new soap_transport_http('http://example.com/');
	$encoded->setEncoding('gzip');
	Assert::false($encoded->usePersistentConnection());
	Assert::same('close', $encoded->outgoing_headers['Connection']);
});

// Test buildPayload writes request line, headers, cookies and body
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php');
	$http->outgoing_headers = ['Host' => 'example.com'];
	$http->buildPayload('<body/>', 'a=1; ');

	Assert::same(
		"POST /service.php HTTP/1.0\r\n"
		. "Host: example.com\r\n"
		. "Content-Length: 7\r\n"
		. "Cookie: a=1; \r\n"
		. "\r\n"
		. '<body/>',
		$http->outgoing_payload
	);
});

// Test buildPayload uses the absolute URL through a proxy and skips Content-Length for GET
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/service.php?wsdl');
	$http->outgoing_headers = ['Host' => 'example.com'];
	$http->request_method = 'GET';
	$http->setProxy('proxy.local', '3128');
	$http->buildPayload('');

	Assert::same(
		"GET http://example.com/service.php?wsdl HTTP/1.0\r\n"
		. "Host: example.com\r\n"
		. "\r\n",
		$http->outgoing_payload
	);
});

// Test isSkippableCurlHeader detects interim and proxy responses
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::true($http->isSkippableCurlHeader("HTTP/1.1 100 Continue\r\n\r\nHTTP/1.1 200 OK"));
	Assert::true($http->isSkippableCurlHeader("HTTP/1.1 302 Found\r\n"));
	Assert::true($http->isSkippableCurlHeader("HTTP/1.1 401 Unauthorized\r\n"));
	Assert::true($http->isSkippableCurlHeader("HTTP/1.0 200 Connection established\r\n"));
	Assert::false($http->isSkippableCurlHeader("HTTP/1.1 200 OK\r\n"));
	Assert::false($http->isSkippableCurlHeader("HTTP/1.1 500 Internal Server Error\r\n"));
});

// Test decodeChunked joins chunks until the terminating zero-size chunk
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same('Hello, World', $http->decodeChunked("5\r\nHello\r\n7\r\n, World\r\n0\r\n\r\n", "\r\n"));
	Assert::same('abcdefghijklmnopqrstuvwxyz', $http->decodeChunked("1a\nabcdefghijklmnopqrstuvwxyz\n0\n\n", "\n"));
});

// Test decodeChunked returns an empty string when there is no line break
Toolkit::test(static function (): void {
	$http = new soap_transport_http('http://example.com/');

	Assert::same('', $http->decodeChunked('no line break', "\r\n"));
});
