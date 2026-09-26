<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test socket connection goes to the proxy port, not the target port (issue #54)
Toolkit::test(static function (): void {
	$proxy = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	Assert::type('resource', $proxy);
	$proxyPort = (int) substr(strrchr(stream_socket_get_name($proxy, false), ':'), 1);

	// Target port 1 is not listening, so connecting there would fail
	$http = new soap_transport_http('http://127.0.0.1:1/service');
	$http->setProxy('127.0.0.1', $proxyPort);

	Assert::true($http->connect(2));

	fclose($http->fp);
	fclose($proxy);
});
