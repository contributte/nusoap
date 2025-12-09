<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test nusoap_fault instantiation
$fault = new nusoap_fault('SOAP-ENV:Client');
Assert::type('nusoap_fault', $fault);
Assert::type('nusoap_base', $fault); // Inherits from nusoap_base


// Test constructor with all parameters
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


// Test constructor with default values
$fault2 = new nusoap_fault('SOAP-ENV:Client');
Assert::same('SOAP-ENV:Client', $fault2->faultcode);
Assert::same('', $fault2->faultactor);
Assert::same('', $fault2->faultstring);
Assert::same('', $fault2->faultdetail);


// Test serialize method
$fault3 = new nusoap_fault(
	'SOAP-ENV:Client',
	'',
	'Invalid request',
	'Missing required parameter'
);

$xml = $fault3->serialize();

// Check XML structure
Assert::contains('<?xml version="1.0"', $xml);
Assert::contains('SOAP-ENV:Envelope', $xml);
Assert::contains('SOAP-ENV:Body', $xml);
Assert::contains('SOAP-ENV:Fault', $xml);
Assert::contains('faultcode', $xml);
Assert::contains('faultstring', $xml);
Assert::contains('Invalid request', $xml);


// Test inherited methods from nusoap_base
$fault4 = new nusoap_fault('SOAP-ENV:Server');

// Test debug methods
$fault4->setDebugLevel(5);
Assert::same(5, $fault4->getDebugLevel());

// Test error methods
Assert::false($fault4->getError());
$fault4->setError('Custom error');
Assert::same('Custom error', $fault4->getError());


// Test soap_fault backward compatibility class
$backwardFault = new soap_fault('SOAP-ENV:Client', '', 'Backward compatible fault');
Assert::type('soap_fault', $backwardFault);
Assert::type('nusoap_fault', $backwardFault);
Assert::type('nusoap_base', $backwardFault);

$xml = $backwardFault->serialize();
Assert::contains('SOAP-ENV:Fault', $xml);
Assert::contains('Backward compatible fault', $xml);


// Test fault with array detail
$faultWithArrayDetail = new nusoap_fault(
	'SOAP-ENV:Server',
	'',
	'Multiple errors',
	['error1' => 'First error', 'error2' => 'Second error']
);

$xml = $faultWithArrayDetail->serialize();
Assert::contains('detail', $xml);
Assert::contains('First error', $xml);
Assert::contains('Second error', $xml);


// Test special characters in fault string
$faultWithSpecialChars = new nusoap_fault(
	'SOAP-ENV:Client',
	'',
	'Error: <invalid> & "problematic"',
	''
);

$xml = $faultWithSpecialChars->serialize();
Assert::contains('faultstring', $xml);
// Special characters should be escaped
Assert::contains('&lt;', $xml);
Assert::contains('&amp;', $xml);


echo "All nusoap_fault tests passed!\n";
