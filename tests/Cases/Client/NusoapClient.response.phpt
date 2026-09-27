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
