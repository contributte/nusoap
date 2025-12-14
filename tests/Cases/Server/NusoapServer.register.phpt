<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

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
