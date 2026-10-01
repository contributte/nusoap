<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\FakeHttpStream;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/FakeHttpStream.php';

const CALL_RESPONSE = '<?xml version="1.0" encoding="UTF-8"?>'
	. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
	. '<SOAP-ENV:Body><ns1:opResponse xmlns:ns1="urn:x"><return>ok</return></ns1:opResponse></SOAP-ENV:Body>'
	. '</SOAP-ENV:Envelope>';

/**
 * @param mixed[] $args
 * @return array{nusoap_client, mixed, string}
 */
function callAndRecord(array $args): array
{
	$requests = new ArrayObject();
	$client = new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service', static function (string $request) use ($requests): string {
		$requests[] = $request;

		return "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(CALL_RESPONSE) . "\r\n\r\n" . CALL_RESPONSE;
	});

	$result = $client->call(...$args);
	$request = $requests[0] ?? '';

	return [$client, $result, substr($request, (int) strpos($request, '<?xml'))];
}

// Test rpc/encoded call wraps the parameters in the method element
Toolkit::test(static function (): void {
	[, $result, $xml] = callAndRecord(['op', ['x' => 1], 'urn:x', 'urn:x#op']);

	Assert::same('ok', $result);
	Assert::contains('SOAP-ENV:encodingStyle="http://schemas.xmlsoap.org/soap/encoding/"', $xml);
	Assert::match('%A%<SOAP-ENV:Body><ns%d%:op xmlns:ns%d%="urn:x"><x xsi:type="xsd:int">1</x></ns%d%:op></SOAP-ENV:Body>%A%', $xml);
});

// Test rpc/literal call with and without namespace
Toolkit::test(static function (): void {
	[, , $xml] = callAndRecord(['op', ['x' => 1], 'urn:x', '', false, null, 'rpc', 'literal']);
	Assert::match('%A%<SOAP-ENV:Body><ns%d%:op xmlns:ns%d%="urn:x"><x>1</x></ns%d%:op></SOAP-ENV:Body>%A%', $xml);
	Assert::notContains('encodingStyle', $xml);

	[, , $xml] = callAndRecord(['op', ['x' => 1], '', '', false, null, 'rpc', 'literal']);
	Assert::contains('<SOAP-ENV:Body><op><x>1</x></op></SOAP-ENV:Body>', $xml);
});

// Test document/literal call sends the parameters as the body
Toolkit::test(static function (): void {
	[, $result, $xml] = callAndRecord(['op', ['x' => 1], 'urn:x', '', false, null, 'document', 'literal']);

	Assert::same(['return' => 'ok'], $result);
	Assert::contains('<SOAP-ENV:Body><x>1</x></SOAP-ENV:Body>', $xml);
});

// Test document/literal wrapped call wraps the parameters in the operation element
Toolkit::test(static function (): void {
	[, , $xml] = callAndRecord(['op', ['x' => 1], 'urn:x', '', false, null, 'document', 'literal wrapped']);
	Assert::contains('<SOAP-ENV:Body><op xmlns="urn:x"><x>1</x></op></SOAP-ENV:Body>', $xml);

	[, , $xml] = callAndRecord(['op', ['x' => 1], '', '', false, null, 'document', 'literal wrapped']);
	Assert::contains('<SOAP-ENV:Body><op><x>1</x></op></SOAP-ENV:Body>', $xml);
});

// Test call sends string parameters as they are
Toolkit::test(static function (): void {
	[, , $xml] = callAndRecord(['op', '<raw>1</raw>', 'urn:x']);

	Assert::match('%A%<ns%d%:op xmlns:ns%d%="urn:x"><raw>1</raw></ns%d%:op>%A%', $xml);
});

// Test call rejects parameters that are neither array nor string
Toolkit::test(static function (): void {
	[$client, $result, $xml] = callAndRecord(['op', 5, 'urn:x']);

	Assert::false($result);
	Assert::same('params must be array or string', $client->getError());
	Assert::same('', $xml);
});

// Test call sends SOAP headers given as XML or soapval objects
Toolkit::test(static function (): void {
	[, , $xml] = callAndRecord(['op', [], 'urn:x', '', '<h:Auth xmlns:h="urn:h">token</h:Auth>']);
	Assert::contains('<SOAP-ENV:Header><h:Auth xmlns:h="urn:h">token</h:Auth></SOAP-ENV:Header>', $xml);

	[, , $xml] = callAndRecord(['op', [], 'urn:x', '', [new soapval('Token', 'string', 'abc', 'urn:h')]]);
	Assert::match('%A%<SOAP-ENV:Header><nu%d%:Token xmlns:nu%d%="urn:h" xsi:type="xsd:string">abc</nu%d%:Token></SOAP-ENV:Header>%A%', $xml);
});

// Test call sends the SOAPAction header
Toolkit::test(static function (): void {
	$requests = new ArrayObject();
	$client = new nusoap_client('http://soap.invalid/service');
	$client->persistentConnection = FakeHttpStream::createTransport('http://soap.invalid/service', static function (string $request) use ($requests): string {
		$requests[] = $request;

		return "HTTP/1.1 200 OK\r\nContent-Type: text/xml; charset=UTF-8\r\nContent-Length: " . strlen(CALL_RESPONSE) . "\r\n\r\n" . CALL_RESPONSE;
	});

	$client->call('op', [], 'urn:x', 'urn:x#op');

	Assert::contains("SOAPAction: \"urn:x#op\"\r\n", $requests[0]);
});
