<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

function createWsdlServer(): nusoap_server
{
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService', 'http://soap.invalid/service');

	return $server;
}

// Test WSDL of a service without operations can be serialized
Toolkit::test(static function (): void {
	$xml = createWsdlServer()->wsdl->serialize();

	Assert::contains("<portType name=\"TestServicePortType\">\n</portType>", $xml);
	Assert::contains('<binding name="TestServiceBinding" type="tns:TestServicePortType">', $xml);
	Assert::contains('<soap:address location="http://soap.invalid/service"/>', $xml);
});
