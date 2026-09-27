<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';
require_once __DIR__ . '/../../Toolkit/ServiceCalculator.php';

// nusoap_server reads the request from $_SERVER
// phpcs:disable SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

// Methods are called as "Class.method", which cannot reference a namespace
// phpcs:ignore PSR1.Classes.ClassDeclaration.MissingNamespace, Squiz.Classes.ClassFileName.NoMatch
final class ServiceMath
{

	public static function triple(int $v): int
	{
		return $v * 3;
	}

	public function double(int $v): int
	{
		return $v * 2;
	}

}

function serviceEcho(string $v): string
{
	return $v;
}

function serviceFault(): nusoap_fault
{
	return new nusoap_fault('SOAP-ENV:Server', '', 'Custom failure', 'details');
}

function requestEnvelope(string $method, string $params = '<v>hello</v>'): string
{
	return '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
		. '<SOAP-ENV:Body><ns1:' . $method . ' xmlns:ns1="urn:Service">' . $params . '</ns1:' . $method . '></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';
}

/**
 * @param array<string, string> $server
 * @return array{nusoap_server, string}
 */
function serve(nusoap_server $soapServer, string $data, array $server = []): array
{
	$originalServer = $_SERVER;
	$_SERVER = $server + ['REQUEST_METHOD' => 'POST', 'QUERY_STRING' => '', 'CONTENT_TYPE' => 'text/xml; charset=UTF-8'];

	try {
		ob_start();
		$soapServer->service($data);
		$output = (string) ob_get_clean();
	} finally {
		$_SERVER = $originalServer;
	}

	return [$soapServer, $output];
}

function createServer(): nusoap_server
{
	$server = new nusoap_server();
	$server->register('serviceEcho');
	$server->register('serviceFault');
	$server->register('ServiceMath.double');
	$server->register('ServiceMath..triple');
	$server->register('Tests.Toolkit.ServiceCalculator.double');
	$server->register('Tests..Toolkit..ServiceCalculator..triple');

	return $server;
}

// Test server calls a registered function and wraps the result in a response element
Toolkit::test(static function (): void {
	[$server, $output] = serve(createServer(), requestEnvelope('serviceEcho'));

	Assert::false($server->fault);
	Assert::same('successful', $server->result);
	Assert::same('serviceEcho', $server->methodname);
	Assert::same('urn:Service', $server->methodURI);
	Assert::same(['v' => 'hello'], $server->methodparams);
	Assert::contains('<ns1:serviceEchoResponse xmlns:ns1="urn:Service"><return xsi:type="xsd:string">hello</return></ns1:serviceEchoResponse>', $output);
	Assert::same($output, $server->responseSOAP);
});

// Test server calls an instance method with "Class.method"
Toolkit::test(static function (): void {
	[$server, $output] = serve(createServer(), requestEnvelope('ServiceMath.double', '<v>21</v>'));

	Assert::false($server->fault);
	Assert::same(42, $server->methodreturn);
	Assert::contains('<return xsi:type="xsd:int">42</return>', $output);
});

// Test server calls a static method with "Class..method"
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('ServiceMath..triple', '<v>5</v>'));

	Assert::false($server->fault);
	Assert::same(15, $server->methodreturn);
});

// Test server does not call functions that are not registered
Toolkit::test(static function (): void {
	[$server, $output] = serve(createServer(), requestEnvelope('phpversion', ''));

	Assert::type(nusoap_fault::class, $server->fault);
	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::same("Operation 'phpversion' not defined in service.", $server->fault->faultstring);
	Assert::contains('<faultcode xsi:type="xsd:string">SOAP-ENV:Client</faultcode>', $output);
});

// Test server reports unknown methods and classes
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('missingFunction'));
	Assert::match("method 'missingFunction'('missingFunction') not defined in service%a%", $server->fault->faultstring);

	[$server] = serve(createServer(), requestEnvelope('ServiceMath.missing'));
	Assert::match("method 'ServiceMath.missing'/'missing'%a%not defined in service%a%", $server->fault->faultstring);

	[$server] = serve(createServer(), requestEnvelope('MissingClass.method'));
	Assert::match("method 'MissingClass.method'%a%not defined in service%a%", $server->fault->faultstring);
});

// Test server returns a fault returned by the method
Toolkit::test(static function (): void {
	[$server, $output] = serve(createServer(), requestEnvelope('serviceFault', ''));

	Assert::same('Custom failure', $server->fault->faultstring);
	Assert::contains('<faultstring xsi:type="xsd:string">Custom failure</faultstring>', $output);
	Assert::contains('HTTP/1.0 500 Internal Server Error', $server->outgoing_headers);
});

// Test server reports malformed XML as a client fault
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body></SOAP-ENV:Envelope>');

	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::match("error in msg parsing:\nXML error parsing SOAP payload%a%", $server->fault->faultstring);
});

// Test server reads SOAPAction and charset from the request headers
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('serviceEcho'), [
		'HTTP_SOAPACTION' => '"urn:Service#serviceEcho"',
		'CONTENT_TYPE' => 'text/xml; charset="utf-8"',
		'HTTP_X_CUSTOM_HEADER' => 'value',
	]);

	Assert::same('urn:Service#serviceEcho', $server->SOAPAction);
	Assert::same('UTF-8', $server->xml_encoding);
	Assert::same('value', $server->headers['x-custom-header']);
	Assert::contains("x-custom-header: value\r\n", $server->request);
	Assert::contains("\r\n\r\n" . requestEnvelope('serviceEcho'), $server->request);
});

// Test server falls back to US-ASCII for unsupported and ISO-8859-1 for missing charsets
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('serviceEcho'), ['CONTENT_TYPE' => 'text/xml; charset=windows-1250']);
	Assert::same('US-ASCII', $server->xml_encoding);

	[$server] = serve(createServer(), requestEnvelope('serviceEcho'), ['CONTENT_TYPE' => 'text/xml']);
	Assert::same('ISO-8859-1', $server->xml_encoding);
});

// Test server decodes a gzip and a zlib compressed request
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), gzencode(requestEnvelope('serviceEcho')), ['HTTP_CONTENT_ENCODING' => 'gzip']);
	Assert::false($server->fault);
	Assert::same(requestEnvelope('serviceEcho'), $server->requestSOAP);

	[$server] = serve(createServer(), gzcompress(requestEnvelope('serviceEcho')), ['HTTP_CONTENT_ENCODING' => 'deflate']);
	Assert::false($server->fault);
	Assert::same(['v' => 'hello'], $server->methodparams);
});

// Test server compresses large responses when the client accepts it
Toolkit::test(static function (): void {
	$value = str_repeat('x', 2000);

	[$server, $output] = serve(createServer(), requestEnvelope('serviceEcho', '<v>' . $value . '</v>'), ['HTTP_ACCEPT_ENCODING' => 'gzip, deflate']);
	Assert::contains('Content-Encoding: gzip', $server->outgoing_headers);
	Assert::contains($value, gzdecode($output));
	Assert::contains('Content-Length: ' . strlen($output), $server->outgoing_headers);

	[$server, $output] = serve(createServer(), requestEnvelope('serviceEcho', '<v>' . $value . '</v>'), ['HTTP_ACCEPT_ENCODING' => 'deflate']);
	Assert::contains('Content-Encoding: deflate', $server->outgoing_headers);
	Assert::contains($value, gzinflate($output));

	// Small responses are sent uncompressed
	[$server, $output] = serve(createServer(), requestEnvelope('serviceEcho'), ['HTTP_ACCEPT_ENCODING' => 'gzip']);
	Assert::notContains('Content-Encoding: gzip', $server->outgoing_headers);
	Assert::contains('hello', $output);
});

// Test server sends response headers
Toolkit::test(static function (): void {
	[$server, $output] = serve(createServer(), requestEnvelope('serviceEcho'));

	Assert::contains('Content-Type: text/xml; charset=ISO-8859-1', $server->outgoing_headers);
	Assert::contains('Content-Length: ' . strlen($output), $server->outgoing_headers);
	Assert::match('X-SOAP-Server: NuSOAP/%a%', $server->outgoing_headers[0]);
	Assert::match("X-SOAP-Server: %a%\r\nContent-Type: %a%\r\nContent-Length: %d%\r\n\r\n<?xml%A%", $server->response);
});

// Test server appends its debug log with the debug query parameter
Toolkit::test(static function (): void {
	$originalServer = $_SERVER;
	$_SERVER['QUERY_STRING'] = 'foo=bar&debug=1';

	try {
		$server = createServer();
	} finally {
		$_SERVER = $originalServer;
	}

	Assert::same('1', $server->debug_flag);

	[, $output] = serve($server, requestEnvelope('serviceEcho'));
	Assert::match('%A%</SOAP-ENV:Envelope><!--%A%in invoke_method%A%-->', $output);
});

// Test server serves the WSDL for a ?wsdl request
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('EchoService', 'urn:Service', 'http://soap.invalid/service');
	$server->register('serviceEcho', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:Service');

	[, $output] = serve($server, '', ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'wsdl']);

	Assert::match('<?xml version="1.0" encoding="ISO-8859-1"?>%A%<definitions%A%name="EchoService"%A%', $output);
	Assert::contains('<soap:address location="http://soap.invalid/service"/>', $output);
});

// Test server passes through a WSDL file
Toolkit::test(static function (): void {
	$file = Environment::getTestDir() . '/service.wsdl';
	$wsdlServer = new nusoap_server();
	$wsdlServer->configureWSDL('FileService', 'urn:Service', 'http://soap.invalid/service');
	$wsdlServer->register('serviceEcho', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:Service');
	file_put_contents($file, $wsdlServer->wsdl->serialize());

	$server = new nusoap_server($file);
	[, $output] = serve($server, '', ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'wsdl']);

	Assert::same(file_get_contents($file), $output);
});

// Test server without WSDL says so
Toolkit::test(static function (): void {
	[, $output] = serve(createServer(), '', ['REQUEST_METHOD' => 'GET', 'QUERY_STRING' => 'wsdl']);
	Assert::same('This service does not provide WSDL', $output);

	[, $output] = serve(createServer(), '', ['REQUEST_METHOD' => 'GET']);
	Assert::same('This service does not provide a Web description', $output);
});

// Test server with WSDL renders a web description for other GET requests
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('EchoService', 'urn:Service', 'http://soap.invalid/service');
	$server->register('serviceEcho', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:Service', false, false, false, 'Echoes the value');

	[, $output] = serve($server, '', ['REQUEST_METHOD' => 'GET']);

	Assert::contains('<title>NuSOAP: EchoService</title>', $output);
	Assert::contains('serviceEcho', $output);
	Assert::contains('Echoes the value', $output);
});

// Test server with WSDL rejects operations missing in the WSDL
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('EchoService', 'urn:Service', 'http://soap.invalid/service');
	$server->register('serviceEcho', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:Service');

	[$server] = serve($server, requestEnvelope('serviceFault', ''));

	Assert::same("Operation 'serviceFault' is not defined in the WSDL for this service", $server->fault->faultstring);
});

// Test server with WSDL finds a document/literal operation by its SOAPAction
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('EchoService', 'urn:Service', 'http://soap.invalid/service', 'document');
	$server->register('serviceEcho', ['v' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:Service', 'urn:Service#serviceEcho', 'document', 'literal');

	[$server, $output] = serve($server, requestEnvelope('someElement'), ['HTTP_SOAPACTION' => '"urn:Service#serviceEcho"']);

	Assert::false($server->fault);
	Assert::same('serviceEcho', $server->methodname);
	Assert::contains('hello', $output);
});

// Test server reports a request that is not text/xml as a client fault
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('serviceEcho'), ['CONTENT_TYPE' => 'text/plain']);

	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::same('Request not of type text/xml: text/plain', $server->fault->faultstring);
	Assert::false($server->methodreturn);

	[$server] = serve(createServer(), requestEnvelope('serviceEcho'), ['CONTENT_TYPE' => '']);
	Assert::same('Request not of type text/xml: ', $server->fault->faultstring);
});

// Test server calls methods of a namespaced class
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('Tests.Toolkit.ServiceCalculator.double', '<v>21</v>'));
	Assert::false($server->fault);
	Assert::same(42, $server->methodreturn);

	[$server] = serve(createServer(), requestEnvelope('Tests..Toolkit..ServiceCalculator..triple', '<v>5</v>'));
	Assert::false($server->fault);
	Assert::same(15, $server->methodreturn);
});

// Test server reports a missing namespaced class as a client fault
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), requestEnvelope('Missing..Namespace..method'));

	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::match("method 'Missing..Namespace..method'%a%not defined in service%a%", $server->fault->faultstring);
});

// Test server decodes a gzip request with an original file name in the gzip header
Toolkit::test(static function (): void {
	$envelope = requestEnvelope('serviceEcho');
	$gzip = "\x1f\x8b\x08\x08\0\0\0\0\0\x03request.xml\0" . gzdeflate($envelope) . pack('V', crc32($envelope)) . pack('V', strlen($envelope));

	[$server] = serve(createServer(), $gzip, ['HTTP_CONTENT_ENCODING' => 'gzip']);

	Assert::false($server->fault);
	Assert::same($envelope, $server->requestSOAP);
});

// Test server reports an invalid gzip request as a client fault
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), 'not gzip data', ['HTTP_CONTENT_ENCODING' => 'gzip']);

	Assert::same('SOAP-ENV:Client', $server->fault->faultcode);
	Assert::same('Errors occurred when trying to decode the data', $server->fault->faultstring);
});

// Test server decodes a raw deflate request, as sent by some clients
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), gzdeflate(requestEnvelope('serviceEcho')), ['HTTP_CONTENT_ENCODING' => 'deflate']);

	Assert::false($server->fault);
	Assert::same(requestEnvelope('serviceEcho'), $server->requestSOAP);
});

// Test server reads the content encoding case-insensitively
Toolkit::test(static function (): void {
	[$server] = serve(createServer(), gzencode(requestEnvelope('serviceEcho')), ['HTTP_CONTENT_ENCODING' => 'GZIP']);

	Assert::false($server->fault);
	Assert::same(requestEnvelope('serviceEcho'), $server->requestSOAP);
});
