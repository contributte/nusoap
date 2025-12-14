<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

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
