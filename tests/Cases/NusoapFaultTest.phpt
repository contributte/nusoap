<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test nusoap_fault instantiation
Toolkit::test(static function (): void {
	$fault = new nusoap_fault('SOAP-ENV:Client');

	Assert::type('nusoap_fault', $fault);
	Assert::type('nusoap_base', $fault);
});

// Test constructor with all parameters
Toolkit::test(static function (): void {
	$fault = new nusoap_fault(
		'SOAP-ENV:Server',
		'http://example.com/actor',
		'Server error occurred',
		'Detailed error information'
	);

	Assert::same('SOAP-ENV:Server', $fault->faultcode);
	Assert::same('http://example.com/actor', $fault->faultactor);
	Assert::same('Server error occurred', $fault->faultstring);
	Assert::same('Detailed error information', $fault->faultdetail);
});

// Test constructor with default values
Toolkit::test(static function (): void {
	$fault = new nusoap_fault('SOAP-ENV:Client');

	Assert::same('SOAP-ENV:Client', $fault->faultcode);
	Assert::same('', $fault->faultactor);
	Assert::same('', $fault->faultstring);
	Assert::same('', $fault->faultdetail);
});

// Test serialize method
Toolkit::test(static function (): void {
	$fault = new nusoap_fault(
		'SOAP-ENV:Client',
		'',
		'Invalid request',
		'Missing required parameter'
	);

	$xml = $fault->serialize();

	Assert::contains('<?xml version="1.0"', $xml);
	Assert::contains('SOAP-ENV:Envelope', $xml);
	Assert::contains('SOAP-ENV:Body', $xml);
	Assert::contains('SOAP-ENV:Fault', $xml);
	Assert::contains('faultcode', $xml);
	Assert::contains('faultstring', $xml);
	Assert::contains('Invalid request', $xml);
});

// Test inherited methods from nusoap_base
Toolkit::test(static function (): void {
	$fault = new nusoap_fault('SOAP-ENV:Server');

	$fault->setDebugLevel(5);
	Assert::same(5, $fault->getDebugLevel());

	Assert::false($fault->getError());
	$fault->setError('Custom error');
	Assert::same('Custom error', $fault->getError());
});

// Test soap_fault backward compatibility class
Toolkit::test(static function (): void {
	$backwardFault = new soap_fault('SOAP-ENV:Client', '', 'Backward compatible fault');

	Assert::type('soap_fault', $backwardFault);
	Assert::type('nusoap_fault', $backwardFault);
	Assert::type('nusoap_base', $backwardFault);

	$xml = $backwardFault->serialize();
	Assert::contains('SOAP-ENV:Fault', $xml);
	Assert::contains('Backward compatible fault', $xml);
});

// Test fault with array detail
Toolkit::test(static function (): void {
	$fault = new nusoap_fault(
		'SOAP-ENV:Server',
		'',
		'Multiple errors',
		['error1' => 'First error', 'error2' => 'Second error']
	);

	$xml = $fault->serialize();
	Assert::contains('detail', $xml);
	Assert::contains('First error', $xml);
	Assert::contains('Second error', $xml);
});

// Test special characters in fault string
Toolkit::test(static function (): void {
	$fault = new nusoap_fault(
		'SOAP-ENV:Client',
		'',
		'Error: <invalid> & "problematic"',
		''
	);

	$xml = $fault->serialize();
	Assert::contains('faultstring', $xml);
	Assert::contains('&lt;', $xml);
	Assert::contains('&amp;', $xml);
});
