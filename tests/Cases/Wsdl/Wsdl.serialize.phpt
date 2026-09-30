<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

function createWsdlServer(): nusoap_server
{
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService', 'http://soap.invalid/service');

	return $server;
}

function parseSerializedWsdl(nusoap_server $server): wsdl
{
	$file = Environment::getTestDir() . '/' . uniqid() . '.wsdl';
	file_put_contents($file, $server->wsdl->serialize());

	$wsdl = new wsdl($file);
	Assert::false($wsdl->getError());

	return $wsdl;
}

// Test WSDL of a service without operations can be serialized
Toolkit::test(static function (): void {
	$xml = createWsdlServer()->wsdl->serialize();

	Assert::contains("<portType name=\"TestServicePortType\">\n</portType>", $xml);
	Assert::contains('<binding name="TestServiceBinding" type="tns:TestServicePortType">', $xml);
	Assert::contains('<soap:address location="http://soap.invalid/service"/>', $xml);
});

// Test WSDL of a service without operations can be parsed again
Toolkit::test(static function (): void {
	$wsdl = parseSerializedWsdl(createWsdlServer());

	Assert::same(['TestServicePortType' => []], $wsdl->portTypes);
	Assert::same([], $wsdl->getOperations());
});

// Test WSDL parsing does not add the next section to the last portType operation
Toolkit::test(static function (): void {
	$server = createWsdlServer();
	$server->register('echo', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:TestService');

	Assert::same([
		'TestServicePortType' => [
			'echo' => [
				'input' => ['message' => 'echo'],
				'output' => ['message' => 'echoResponse'],
			],
		],
	], parseSerializedWsdl($server)->portTypes);
});

// Test operation lookup in a WSDL without operations
Toolkit::test(static function (): void {
	Assert::same([], createWsdlServer()->wsdl->getOperationData('missing'));
});

// Test SOAPAction lookup in a WSDL without operations
Toolkit::test(static function (): void {
	Assert::same([], createWsdlServer()->wsdl->getOperationDataForSoapAction('urn:TestService#missing'));
});

// Test binding lookup
Toolkit::test(static function (): void {
	$wsdl = createWsdlServer()->wsdl;

	Assert::same('TestServicePortType', $wsdl->getBindingData('TestServiceBinding')['portType']);
	Assert::false($wsdl->getBindingData('MissingBinding'));
});
