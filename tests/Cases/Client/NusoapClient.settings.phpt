<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

function createCalcWsdlFile(): string
{
	$server = new nusoap_server();
	$server->configureWSDL('Calc', 'urn:Calc', 'http://soap.invalid/calc');
	$server->register('add', ['a' => 'xsd:int', 'b' => 'xsd:int'], ['return' => 'xsd:int'], 'urn:Calc', 'urn:Calc#add');
	$server->register('ping', [], ['return' => 'xsd:string'], 'urn:Calc', 'urn:Calc#ping');

	$file = Environment::getTestDir() . '/calc.wsdl';
	file_put_contents($file, $server->wsdl->serialize());

	return $file;
}

// Test client constructor stores endpoint and connection settings
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service', false, 'proxy.local', '3128', 'pu', 'pp', 10, 60, 'Port');

	Assert::same('http://example.com/service', $client->endpoint);
	Assert::same('soap', $client->endpointType);
	Assert::same('proxy.local', $client->proxyhost);
	Assert::same('3128', $client->proxyport);
	Assert::same('pu', $client->proxyusername);
	Assert::same('pp', $client->proxypassword);
	Assert::same(10, $client->timeout);
	Assert::same(60, $client->response_timeout);
	Assert::same('Port', $client->portName);
});

// Test client in WSDL mode loads the WSDL lazily
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service?wsdl', 'wsdl');

	Assert::same('wsdl', $client->endpointType);
	Assert::same('http://example.com/service?wsdl', $client->wsdlFile);
	Assert::null($client->wsdl);
});

// Test client setters store their values
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');

	$client->setEndpoint('http://example.com/other');
	Assert::same('http://example.com/other', $client->forceEndpoint);

	$client->setHeaders('<h:Auth xmlns:h="urn:h">token</h:Auth>');
	Assert::same('<h:Auth xmlns:h="urn:h">token</h:Auth>', $client->requestHeaders);

	$client->setHTTPProxy('proxy.local', '8080', 'user', 'pass');
	Assert::same(['proxy.local', '8080', 'user', 'pass'], [$client->proxyhost, $client->proxyport, $client->proxyusername, $client->proxypassword]);

	$client->setCredentials('user', 'pass', 'digest', ['sslcertfile' => 'cert.pem']);
	Assert::same(['user', 'pass', 'digest', ['sslcertfile' => 'cert.pem']], [$client->username, $client->password, $client->authtype, $client->certRequest]);

	$client->setHTTPEncoding();
	Assert::same('gzip, deflate', $client->http_encoding);

	$client->setUseCURL(true);
	Assert::true($client->use_curl);

	$client->useHTTPPersistentConnection();
	Assert::true($client->persistentConnection);

	$client->setCurlOption(CURLOPT_TIMEOUT, 5);
	Assert::same([CURLOPT_TIMEOUT => 5], $client->curl_options);

	Assert::true($client->decodeUTF8(false));
	Assert::false($client->decode_utf8);

	$client->setDefaultRpcParams(true);
	Assert::true($client->getDefaultRpcParams());
});

// Test HTTP body and content type helpers
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');

	Assert::same('<soap/>', $client->getHTTPBody('<soap/>'));
	Assert::same('text/xml', $client->getHTTPContentType());
	Assert::same('ISO-8859-1', $client->getHTTPContentTypeCharset());

	$client->setHTTPContentType('application/soap+xml');
	Assert::same('application/soap+xml', $client->getHTTPContentType());

	$client->setHTTPContentType();
	Assert::same('text/xml', $client->getHTTPContentType());
});

// Test setCookie adds cookies and rejects empty names
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');

	Assert::same([], $client->getCookies());
	Assert::true($client->setCookie('a', '1'));
	Assert::true($client->setCookie('b', ''));
	Assert::false($client->setCookie('', 'x'));

	Assert::same([
		['name' => 'a', 'value' => '1'],
		['name' => 'b', 'value' => ''],
	], $client->getCookies());
});

// Test checkCookies drops expired and malformed cookies
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');
	$future = gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT';
	$client->cookies = [
		['name' => 'session', 'value' => '1'],
		['name' => 'expired', 'value' => '2', 'expires' => 'Thu, 01 Jan 1970 00:00:01 GMT'],
		'not-an-array',
		['name' => 'future', 'value' => '3', 'expires' => $future],
	];

	Assert::true($client->checkCookies());
	Assert::same([
		['name' => 'session', 'value' => '1'],
		['name' => 'future', 'value' => '3', 'expires' => $future],
	], $client->getCookies());

	$empty = new nusoap_client('http://example.com/service');
	Assert::true($empty->checkCookies());
	Assert::same([], $empty->getCookies());
});

// Test UpdateCookies takes new cookies when there are none yet
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');

	Assert::true($client->UpdateCookies([]));
	Assert::same([], $client->getCookies());

	Assert::true($client->UpdateCookies([['name' => 'a', 'value' => '1']]));
	Assert::same([['name' => 'a', 'value' => '1']], $client->getCookies());

	// No new cookies keeps the current ones
	Assert::true($client->UpdateCookies([]));
	Assert::same([['name' => 'a', 'value' => '1']], $client->getCookies());
});

// Test UpdateCookies replaces cookies with the same name, domain and path and adds the others
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');
	$client->cookies = [
		['name' => 'a', 'value' => '1', 'domain' => 'example.com', 'path' => '/'],
		['name' => 'b', 'value' => '2'],
	];

	$client->UpdateCookies([
		['name' => 'a', 'value' => 'updated', 'domain' => 'example.com', 'path' => '/'],
		['name' => 'a', 'value' => 'other-path', 'domain' => 'example.com', 'path' => '/admin'],
		['name' => 'b', 'value' => 'updated'],
		['name' => 'c', 'value' => '3'],
		['value' => 'no-name'],
		'not-an-array',
	]);

	Assert::same([
		['name' => 'a', 'value' => 'updated', 'domain' => 'example.com', 'path' => '/'],
		['name' => 'b', 'value' => 'updated'],
		['name' => 'a', 'value' => 'other-path', 'domain' => 'example.com', 'path' => '/admin'],
		['name' => 'c', 'value' => '3'],
	], $client->getCookies());
});

// Test getProxyClassCode generates a method per WSDL operation
Toolkit::test(static function (): void {
	$client = new nusoap_client(createCalcWsdlFile(), 'wsdl');
	$code = $client->getProxyClassCode();

	Assert::false($client->getError());
	Assert::match('~^class nusoap_proxy_\d+ extends nusoap_client \{~', $code);
	Assert::contains('function add($a, $b) {', $code);
	Assert::contains("\$params = array('a' => \$a, 'b' => \$b);", $code);
	Assert::contains("return \$this->call('add', \$params, 'http://testuri.com', 'urn:Calc#add');", $code);
	Assert::contains("// void\n\tfunction ping() {", $code);
});

// Test getProxy returns a client with a method per operation and the client state
Toolkit::test(static function (): void {
	$client = new nusoap_client(createCalcWsdlFile(), 'wsdl');
	$client->setCredentials('user', 'pass');
	$client->setEndpoint('http://soap.invalid/forced');
	$client->setCurlOption(CURLOPT_TIMEOUT, 5);

	$proxy = $client->getProxy();

	Assert::type(nusoap_client::class, $proxy);
	Assert::true(method_exists($proxy, 'add'));
	Assert::true(method_exists($proxy, 'ping'));
	Assert::same('wsdl', $proxy->endpointType);
	Assert::same($client->wsdl, $proxy->wsdl);
	Assert::same($client->operations, $proxy->operations);
	Assert::same('user', $proxy->username);
	Assert::same('pass', $proxy->password);
	Assert::same('http://soap.invalid/forced', $proxy->forceEndpoint);
	Assert::same([CURLOPT_TIMEOUT => 5], $proxy->curl_options);
});

// Test getProxy is only available for WSDL clients
Toolkit::test(static function (): void {
	$client = new nusoap_client('http://example.com/service');

	Assert::null($client->getProxy());
	Assert::same('A proxy can only be created for a WSDL client', $client->getError());
});

// Test getProxy reports a WSDL that cannot be loaded
Toolkit::test(static function (): void {
	$client = new nusoap_client(Environment::getTestDir() . '/missing.wsdl', 'wsdl');

	Assert::null($client->getProxy());
	Assert::contains('missing.wsdl', $client->getError());
});
