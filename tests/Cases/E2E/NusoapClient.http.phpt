<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\HttpServer;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/HttpServer.php';

$httpServer = new HttpServer(__DIR__ . '/../../fixtures/http/soap-server.php');

// Socket transport is covered without a real server in NusoapClient.fake.phpt,
// cURL does not use PHP streams, so it needs a real one

// Test client calls a remote server without WSDL over cURL
Toolkit::test(static function () use ($httpServer): void {
	$client = new nusoap_client($httpServer->getUrl());
	$client->setUseCURL(true);

	$result = $client->call('sayHello', ['name' => 'World'], 'urn:TestService', 'urn:TestService#sayHello');

	Assert::false($client->getError());
	Assert::same('Hello, World!', $result);
	Assert::contains('connect using cURL', $client->getDebug());
});

// Test client fetches WSDL from a remote server and calls it over cURL
Toolkit::test(static function () use ($httpServer): void {
	$client = new nusoap_client($httpServer->getUrl('/?wsdl'), true);
	$client->setUseCURL(true);

	$result = $client->call('sayHello', ['name' => 'WSDL']);

	Assert::false($client->getError());
	Assert::same('Hello, WSDL!', $result);
	Assert::contains('connect using cURL', $client->getDebug());
});
