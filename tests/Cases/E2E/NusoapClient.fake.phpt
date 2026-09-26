<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\FakeHttpStream;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/FakeHttpStream.php';

function sayHello(string $name): string
{
	return 'Hello, ' . $name . '!';
}

function createServer(): nusoap_server
{
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService', 'http://soap.invalid/service');
	$server->register('sayHello', ['name' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:TestService', 'urn:TestService#sayHello');

	return $server;
}

// Test client calls an in-process server through a fake socket connection
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport(
		'http://soap.invalid/service',
		FakeHttpStream::serve(static fn (): nusoap_server => createServer())
	);

	$result = $client->call('sayHello', ['name' => 'World'], 'urn:TestService', 'urn:TestService#sayHello');

	Assert::false($client->getError());
	Assert::same('Hello, World!', $result);
	Assert::notContains('calling fsockopen', $client->getDebug());
});

// Test client in WSDL mode calls an in-process server through a fake socket connection
Toolkit::test(static function (): void {
	$client = new nusoap_client(createServer()->wsdl, true);
	$client->persistentConnection = FakeHttpStream::createTransport(
		'http://soap.invalid/service',
		FakeHttpStream::serve(static fn (): nusoap_server => createServer())
	);

	$result = $client->call('sayHello', ['name' => 'WSDL']);

	Assert::false($client->getError());
	Assert::same('Hello, WSDL!', $result);
	Assert::notContains('calling fsockopen', $client->getDebug());
});

// Test the raw HTTP request can be inspected and the response can be canned
Toolkit::test(static function (): void {
	$recorded = new stdClass();
	$handler = static function (string $raw) use ($recorded): string {
		$recorded->request = $raw;
		$body = '<?xml version="1.0" encoding="UTF-8"?>'
			. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
			. '<SOAP-ENV:Body><ns1:sayHelloResponse xmlns:ns1="urn:TestService"><return>Canned</return></ns1:sayHelloResponse></SOAP-ENV:Body>'
			. '</SOAP-ENV:Envelope>';

		return "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen($body) . "\r\n\r\n" . $body;
	};

	$client = new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service', $handler);

	$result = $client->call('sayHello', ['name' => 'World'], 'urn:TestService', 'urn:TestService#sayHello');

	Assert::same('Canned', $result);
	Assert::match('POST /service HTTP/1.1%A%', $recorded->request);
	Assert::contains('SOAPAction: "urn:TestService#sayHello"', $recorded->request);
	Assert::contains('<name xsi:type="xsd:string">World</name>', $recorded->request);
});
