<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\HttpServer;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/HttpServer.php';

$httpServer = new HttpServer(__DIR__ . '/../../fixtures/http/soap-server.php');

foreach (['socket' => false, 'curl' => true] as $transport => $useCurl) {
	// Test client calls a remote server without WSDL
	Toolkit::test(static function () use ($httpServer, $transport, $useCurl): void {
		$client = new nusoap_client($httpServer->getUrl());
		$client->setUseCURL($useCurl);

		$result = $client->call('sayHello', ['name' => 'World'], 'urn:TestService', 'urn:TestService#sayHello');

		Assert::false($client->getError(), $transport);
		Assert::same('Hello, World!', $result, $transport);
		Assert::contains($useCurl ? 'connect using cURL' : 'calling fsockopen', $client->getDebug());
	});

	// Test client fetches WSDL from a remote server and calls it
	Toolkit::test(static function () use ($httpServer, $transport, $useCurl): void {
		$client = new nusoap_client($httpServer->getUrl('/?wsdl'), true);
		$client->setUseCURL($useCurl);

		$result = $client->call('sayHello', ['name' => 'WSDL']);

		Assert::false($client->getError(), $transport);
		Assert::same('Hello, WSDL!', $result, $transport);
		Assert::contains($useCurl ? 'connect using cURL' : 'calling fsockopen', $client->getDebug());
	});
}
