<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

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
