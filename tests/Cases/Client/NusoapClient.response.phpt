<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\FakeHttpStream;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/FakeHttpStream.php';

const RESPONSE_ENVELOPE = '<?xml version="1.0" encoding="UTF-8"?>'
	. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
	. '<SOAP-ENV:Body><ns1:sayHelloResponse xmlns:ns1="urn:TestService"><return>Canned</return></ns1:sayHelloResponse></SOAP-ENV:Body>'
	. '</SOAP-ENV:Envelope>';

function callWithResponse(callable $handler, ?nusoap_client $client = null): nusoap_client
{
	$client ??= new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service', $handler);
	$client->return = $client->call('sayHello', ['name' => 'World'], 'urn:TestService', 'urn:TestService#sayHello');

	return $client;
}

function cannedResponse(string $headers, string $body): callable
{
	return static fn (): string => "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\n" . $headers . "\r\n" . $body;
}

// Test client reads a chunked response
Toolkit::test(static function (): void {
	$chunks = str_split(RESPONSE_ENVELOPE, 50);
	$body = '';
	foreach ($chunks as $chunk) {
		$body .= dechex(strlen($chunk)) . "\r\n" . $chunk . "\r\n";
	}

	$body .= "0\r\n\r\n";

	$client = callWithResponse(cannedResponse("Transfer-Encoding: chunked\r\n", $body));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client reads a chunked response with chunk extensions
Toolkit::test(static function (): void {
	$body = '';
	foreach (str_split(RESPONSE_ENVELOPE, 50) as $i => $chunk) {
		$body .= dechex(strlen($chunk)) . ';chunk=' . $i . "\r\n" . $chunk . "\r\n";
	}

	$body .= "0;last\r\n\r\n";

	$client = callWithResponse(cannedResponse("Transfer-Encoding: chunked\r\n", $body));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client inflates gzip and deflate encoded responses
Toolkit::test(static function (): void {
	foreach (['gzip' => gzencode(RESPONSE_ENVELOPE), 'deflate' => gzdeflate(RESPONSE_ENVELOPE)] as $encoding => $body) {
		$client = callWithResponse(cannedResponse(
			'Content-Encoding: ' . $encoding . "\r\nContent-Length: " . strlen($body) . "\r\n",
			$body
		));

		Assert::false($client->getError(), $encoding);
		Assert::same('Canned', $client->return, $encoding);
	}
});

// Test client inflates a zlib wrapped deflate response (RFC 7230)
Toolkit::test(static function (): void {
	$body = gzcompress(RESPONSE_ENVELOPE);
	$client = callWithResponse(cannedResponse("Content-Encoding: deflate\r\nContent-Length: " . strlen($body) . "\r\n", $body));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client un-gzips a response with an original file name in the gzip header
Toolkit::test(static function (): void {
	$body = "\x1f\x8b\x08\x08\0\0\0\0\0\x03response.xml\0" . gzdeflate(RESPONSE_ENVELOPE) . pack('V', crc32(RESPONSE_ENVELOPE)) . pack('V', strlen(RESPONSE_ENVELOPE));
	$client = callWithResponse(cannedResponse("Content-Encoding: gzip\r\nContent-Length: " . strlen($body) . "\r\n", $body));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client reads the content encoding case-insensitively
Toolkit::test(static function (): void {
	$body = gzencode(RESPONSE_ENVELOPE);
	$client = callWithResponse(cannedResponse("Content-Encoding: GZIP\r\nContent-Length: " . strlen($body) . "\r\n", $body));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client reports unsupported content encoding
Toolkit::test(static function (): void {
	$client = callWithResponse(cannedResponse(
		"Content-Encoding: br\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n",
		RESPONSE_ENVELOPE
	));

	Assert::same('HTTP Error: Unsupported Content-Encoding br', $client->getError());
});

// Test client skips an interim 100 Continue response
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 100 Continue\r\n\r\n"
		. "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n"
		. RESPONSE_ENVELOPE);

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client reports unsupported HTTP status codes
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 404 Not Found\r\nContent-Type: text/html\r\nContent-Length: 9\r\n\r\nNot Found");

	Assert::false($client->return);
	Assert::contains('HTTP Error: Unsupported HTTP response status 404 Not Found', $client->getError());
});

// Test client reports failed HTTP authentication
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 401 Unauthorized\r\nWWW-Authenticate: Basic realm=\"test\"\r\nContent-Length: 0\r\n\r\n");

	Assert::false($client->return);
	Assert::same('HTTP Error: HTTP authentication failed', $client->getError());
});

// Test client reports a response without body
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 200 OK\r\nContent-Type: text/xml\r\nContent-Length: 0\r\n\r\n");

	Assert::false($client->return);
	Assert::same('HTTP Error: no data present after HTTP headers', $client->getError());
});

// Test client reports a non-XML response body
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 200 OK\r\nContent-Type: text/html\r\nContent-Length: 5\r\n\r\nhello");

	Assert::false($client->return);
	Assert::match('Response not of type text/xml: text/html', $client->getError());
});

// Test client stores cookies from the response and sends them with the next request
Toolkit::test(static function (): void {
	$client = callWithResponse(cannedResponse(
		"Set-Cookie: SID=abc123; path=/\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n",
		RESPONSE_ENVELOPE
	));

	Assert::false($client->getError());
	Assert::same([
	[
		'name' => 'SID',
		'value' => 'abc123',
		'domain' => '',
		'path' => '/',
		'expires' => '',
		'secure' => false,
	]], $client->getCookies());

	$recorded = new stdClass();
	callWithResponse(static function (string $request) use ($recorded): string {
		$recorded->request = $request;

		return "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n" . RESPONSE_ENVELOPE;
	}, $client);

	Assert::contains("Cookie: SID=abc123; \r\n", $recorded->request);
});

// Test client exposes the raw request, response and parsed SOAP headers
Toolkit::test(static function (): void {
	$body = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
		. '<SOAP-ENV:Header><ns2:Session xmlns:ns2="urn:h"><id>42</id></ns2:Session></SOAP-ENV:Header>'
		. '<SOAP-ENV:Body><ns1:sayHelloResponse xmlns:ns1="urn:TestService"><return>Canned</return></ns1:sayHelloResponse></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';

	$client = callWithResponse(cannedResponse('Content-Length: ' . strlen($body) . "\r\n", $body));

	Assert::same('Canned', $client->return);
	Assert::match('POST /service HTTP/1.1%A%', $client->request);
	Assert::match('HTTP/1.1 200 OK%A%', $client->response);
	Assert::same($body, $client->responseData);
	Assert::same('<ns2:Session xmlns:ns2="urn:h"><id>42</id></ns2:Session>', $client->getHeaders());
	Assert::same(['Session' => ['id' => '42']], $client->getHeader());
});

// Test a received fault only sets the fault properties of the client
Toolkit::test(static function (): void {
	$body = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
		. '<SOAP-ENV:Body><SOAP-ENV:Fault>'
		. '<faultcode>SOAP-ENV:Server</faultcode><faultstring>Broken</faultstring>'
		. '<endpoint>http://attacker.invalid/</endpoint><custom>value</custom>'
		. '</SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>';

	$client = callWithResponse(static fn (): string => "HTTP/1.1 500 Internal Server Error\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);

	Assert::true($client->fault);
	Assert::same('SOAP-ENV:Server', $client->faultcode);
	Assert::same('Broken', $client->faultstring);
	Assert::same('http://soap.invalid/service', $client->endpoint);
	Assert::false(property_exists($client, 'custom'));
	Assert::same('value', $client->return['custom']);
});

// Test a received fault without faultstring
Toolkit::test(static function (): void {
	$body = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
		. '<SOAP-ENV:Body><SOAP-ENV:Fault><faultcode>SOAP-ENV:Server</faultcode></SOAP-ENV:Fault></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';

	$client = callWithResponse(static fn (): string => "HTTP/1.1 500 Internal Server Error\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body);

	Assert::true($client->fault);
	Assert::same('SOAP-ENV:Server: ', $client->getError());
});

// Test client reads the charset among other content type parameters
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=utf-8; action=\"urn:TestService#sayHello\"\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n" . RESPONSE_ENVELOPE);

	Assert::false($client->getError());
	Assert::same('UTF-8', $client->xml_encoding);
	Assert::same('Canned', $client->return);
});

// Test client accepts an endpoint with an uppercase scheme
Toolkit::test(static function (): void {
	$client = callWithResponse(cannedResponse('Content-Length: ' . strlen(RESPONSE_ENVELOPE) . "\r\n", RESPONSE_ENVELOPE), new nusoap_client('HTTP://soap.invalid/service'));

	Assert::false($client->getError());
	Assert::same('Canned', $client->return);
});

// Test client follows absolute and relative redirects
Toolkit::test(static function (): void {
	$locations = [
		'http://soap.invalid/moved' => 'POST /moved HTTP/1.1',
		'/other/service?x=1' => 'POST /other/service?x=1 HTTP/1.1',
		'next.php' => 'POST /app/next.php HTTP/1.1',
	];

	foreach ($locations as $location => $requestLine) {
		$requests = new ArrayObject();
		$client = new nusoap_client('http://soap.invalid/app/service?wsdl');
		$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/app/service?wsdl', static function (string $request) use ($requests, $location): string {
			$requests[] = $request;

			return count($requests) === 1
				? "HTTP/1.1 302 Found\r\nLocation: " . $location . "\r\nContent-Length: 0\r\n\r\n"
				: "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n" . RESPONSE_ENVELOPE;
		});

		Assert::same('Canned', $client->call('sayHello', ['name' => 'World'], 'urn:TestService'), $location);
		Assert::count(2, $requests, $location);
		Assert::match($requestLine . "\r\nHost: soap.invalid\r\n%A%", $requests[1], $location);
	}
});

// Test client follows a permanent redirect keeping the method (RFC 7538)
Toolkit::test(static function (): void {
	$requests = new ArrayObject();
	$client = new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service', static function (string $request) use ($requests): string {
		$requests[] = $request;

		return count($requests) === 1
			? "HTTP/1.1 308 Permanent Redirect\r\nLocation: /moved\r\nContent-Length: 0\r\n\r\n"
			: "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n" . RESPONSE_ENVELOPE;
	});

	Assert::same('Canned', $client->call('sayHello', ['name' => 'World'], 'urn:TestService'));
	Assert::match("POST /moved HTTP/1.1\r\n%A%", $requests[1]);
});

// Test client reports a response without content type
Toolkit::test(static function (): void {
	$client = callWithResponse(static fn (): string => "HTTP/1.1 200 OK\r\nContent-Length: " . strlen(RESPONSE_ENVELOPE) . "\r\n\r\n" . RESPONSE_ENVELOPE);

	Assert::false($client->return);
	Assert::same('Response not of type text/xml (no content-type header)', $client->getError());
});
