<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;
use Tests\Toolkit\FakeHttpStream;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/FakeHttpStream.php';

function getPrice(string $name): int
{
	return ['book' => 20, 'pen' => 10][$name] ?? 0;
}

/**
 * @return array{return: int}
 */
function getPriceWrapped(string $name): array
{
	return ['return' => getPrice($name)];
}

function createDocumentLiteralServer(): nusoap_server
{
	$server = new nusoap_server();
	$server->configureWSDL('SoapDemo', 'urn:soapdemo', 'http://soap.invalid/service');
	foreach (['getPrice', 'getPriceWrapped'] as $method) {
		$server->register($method, ['name' => 'xsd:string'], ['return' => 'xsd:int'], 'urn:soapdemo', 'urn:soapdemo#' . $method, 'document', 'literal');
	}

	return $server;
}

function callDocumentLiteral(string $method, string $name): mixed
{
	$client = new nusoap_client(createDocumentLiteralServer()->wsdl, true);
	$client->persistentConnection = FakeHttpStream::createTransport(
		'http://soap.invalid/service',
		FakeHttpStream::serve(static fn (): nusoap_server => createDocumentLiteralServer())
	);

	$result = $client->call($method, ['name' => $name]);
	Assert::false($client->getError());

	return $result;
}

// Test document/literal method with a single output may return its value directly (issue #118)
Toolkit::test(static function (): void {
	Assert::same(['return' => '20'], callDocumentLiteral('getPrice', 'book'));
});

// Test document/literal method may still return the response element as an array
Toolkit::test(static function (): void {
	Assert::same(['return' => '10'], callDocumentLiteral('getPriceWrapped', 'pen'));
});
