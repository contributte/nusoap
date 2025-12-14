<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test addComplexType
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService');

	$server->wsdl->addComplexType(
		'Person',
		'complexType',
		'struct',
		'all',
		'',
		array(
			'name' => array('name' => 'name', 'type' => 'xsd:string'),
			'age' => array('name' => 'age', 'type' => 'xsd:int')
		)
	);

	// Register a method so WSDL can serialize properly
	$server->register(
		'getPerson',
		array('id' => 'xsd:int'),
		array('return' => 'tns:Person'),
		'urn:TestService',
		'urn:TestService#getPerson',
		'rpc',
		'encoded',
		'Get person by ID'
	);

	// Verify the complex type was added by serializing WSDL
	$wsdlXml = $server->wsdl->serialize();
	Assert::contains('Person', $wsdlXml);
	Assert::contains('complexType', $wsdlXml);
});

// Test WSDL generation with array types
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('ArrayService', 'urn:ArrayService');

	$server->wsdl->addComplexType(
		'StringArray',
		'complexType',
		'array',
		'',
		'SOAP-ENC:Array',
		array(),
		array(
			array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'xsd:string[]')
		),
		'xsd:string'
	);

	// Register a method so WSDL can serialize properly
	$server->register(
		'getStrings',
		array(),
		array('return' => 'tns:StringArray'),
		'urn:ArrayService',
		'urn:ArrayService#getStrings',
		'rpc',
		'encoded',
		'Get strings'
	);

	$wsdlXml = $server->wsdl->serialize();
	Assert::contains('StringArray', $wsdlXml);
	Assert::contains('Array', $wsdlXml);
});

// Test nested complex types
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('NestedService', 'urn:NestedService');

	// Add Address type
	$server->wsdl->addComplexType(
		'Address',
		'complexType',
		'struct',
		'all',
		'',
		array(
			'street' => array('name' => 'street', 'type' => 'xsd:string'),
			'city' => array('name' => 'city', 'type' => 'xsd:string')
		)
	);

	// Add Person type with nested Address
	$server->wsdl->addComplexType(
		'Person',
		'complexType',
		'struct',
		'all',
		'',
		array(
			'name' => array('name' => 'name', 'type' => 'xsd:string'),
			'address' => array('name' => 'address', 'type' => 'tns:Address')
		)
	);

	// Register a method so WSDL can serialize properly
	$server->register(
		'getPerson',
		array('id' => 'xsd:int'),
		array('return' => 'tns:Person'),
		'urn:NestedService',
		'urn:NestedService#getPerson',
		'rpc',
		'encoded',
		'Get person'
	);

	$wsdlXml = $server->wsdl->serialize();
	Assert::contains('Address', $wsdlXml);
	Assert::contains('Person', $wsdlXml);
	Assert::contains('tns:Address', $wsdlXml);
});
