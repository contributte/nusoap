<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test configureWSDL
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->configureWSDL('TestService', 'urn:TestService');

	Assert::type('wsdl', $server->wsdl);
	Assert::same('TestService', $server->wsdl->serviceName);
});

// Test configureWSDL with custom endpoint
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->configureWSDL(
		'TestService',
		'urn:TestService',
		'http://example.com/soap/endpoint'
	);

	Assert::type('wsdl', $server->wsdl);
	Assert::same('http://example.com/soap/endpoint', $server->wsdl->endpoint);
});

// Test configureWSDL with document style
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->configureWSDL(
		'DocService',
		'urn:DocService',
		false,
		'document'
	);

	Assert::type('wsdl', $server->wsdl);
});

// Test WSDL serialization includes service definition
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('HelloService', 'urn:HelloService');

	$server->register(
		'sayHello',
		array('name' => 'xsd:string'),
		array('return' => 'xsd:string'),
		'urn:HelloService',
		'urn:HelloService#sayHello',
		'rpc',
		'encoded',
		'Says hello'
	);

	$wsdlXml = $server->wsdl->serialize();

	Assert::contains('HelloService', $wsdlXml);
	Assert::contains('sayHello', $wsdlXml);
	Assert::contains('definitions', $wsdlXml);
	Assert::contains('portType', $wsdlXml);
	Assert::contains('binding', $wsdlXml);
	Assert::contains('service', $wsdlXml);
});

// Test server with document/literal style
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('DocLitService', 'urn:DocLitService', false, 'document');

	$server->wsdl->addComplexType(
		'RequestType',
		'complexType',
		'struct',
		'sequence',
		'',
		array(
			'param' => array('name' => 'param', 'type' => 'xsd:string')
		)
	);

	$server->register(
		'docMethod',
		array('parameters' => 'tns:RequestType'),
		array('return' => 'xsd:string'),
		'urn:DocLitService',
		'urn:DocLitService/docMethod',
		'document',
		'literal',
		'Document literal method'
	);

	$wsdlXml = $server->wsdl->serialize();
	Assert::contains('docMethod', $wsdlXml);
	Assert::contains('RequestType', $wsdlXml);
});
