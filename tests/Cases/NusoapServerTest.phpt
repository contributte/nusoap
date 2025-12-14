<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test nusoap_server instantiation
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	Assert::type('nusoap_server', $server);
	Assert::type('nusoap_base', $server);
});

// Test soap_server backward compatibility class
Toolkit::test(static function (): void {
	$server = new soap_server();

	Assert::type('soap_server', $server);
	Assert::type('nusoap_server', $server);
});

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

// Test register method
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService');

	$server->register(
		'testMethod',
		array('input' => 'xsd:string'),
		array('return' => 'xsd:string'),
		'urn:TestService',
		'urn:TestService#testMethod',
		'rpc',
		'encoded',
		'Test method documentation'
	);

	Assert::true(isset($server->operations['testMethod']));
	Assert::same('testMethod', $server->operations['testMethod']['name']);
	Assert::same(array('input' => 'xsd:string'), $server->operations['testMethod']['in']);
	Assert::same(array('return' => 'xsd:string'), $server->operations['testMethod']['out']);
});

// Test register multiple methods
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService');

	$server->register(
		'method1',
		array('a' => 'xsd:int'),
		array('return' => 'xsd:int'),
		'urn:TestService',
		'urn:TestService#method1'
	);

	$server->register(
		'method2',
		array('b' => 'xsd:string'),
		array('return' => 'xsd:string'),
		'urn:TestService',
		'urn:TestService#method2'
	);

	Assert::true(isset($server->operations['method1']));
	Assert::true(isset($server->operations['method2']));
	Assert::count(2, $server->operations);
});

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

// Test server fault method
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->fault('SOAP-ENV:Client', 'Test fault message');

	Assert::type('nusoap_fault', $server->fault);
	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::same('Test fault message', $server->fault->faultstring);
});

// Test server fault with all parameters
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->fault(
		'SOAP-ENV:Server',
		'Server error',
		'http://example.com/actor',
		'Detailed error information'
	);

	Assert::type('nusoap_fault', $server->fault);
	Assert::same('SOAP-ENV:Server', $server->fault->faultcode);
	Assert::same('Server error', $server->fault->faultstring);
	Assert::same('http://example.com/actor', $server->fault->faultactor);
	Assert::same('Detailed error information', $server->fault->faultdetail);
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

// Test server inherited methods from nusoap_base
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	$server->setDebugLevel(5);
	Assert::same(5, $server->getDebugLevel());

	Assert::false($server->getError());
	$server->setError('Test error');
	Assert::same('Test error', $server->getError());
});

// Test server properties
Toolkit::test(static function (): void {
	$server = new nusoap_server();

	Assert::type('array', $server->headers);
	Assert::type('string', $server->request);
	Assert::type('array', $server->operations);
});

// Test WSDL with multiple operations
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('MultiOpService', 'urn:MultiOpService');

	$server->register('op1', array('a' => 'xsd:int'), array('return' => 'xsd:int'), 'urn:MultiOpService', 'urn:MultiOpService#op1');
	$server->register('op2', array('b' => 'xsd:string'), array('return' => 'xsd:string'), 'urn:MultiOpService', 'urn:MultiOpService#op2');
	$server->register('op3', array('c' => 'xsd:float'), array('return' => 'xsd:float'), 'urn:MultiOpService', 'urn:MultiOpService#op3');

	$wsdlXml = $server->wsdl->serialize();

	Assert::contains('op1', $wsdlXml);
	Assert::contains('op2', $wsdlXml);
	Assert::contains('op3', $wsdlXml);
	Assert::contains('MultiOpService', $wsdlXml);
});
