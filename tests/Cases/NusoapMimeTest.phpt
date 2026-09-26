<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/nusoapmime.php';

function createMultipartMessage(string $soapEnvelope): string
{
	return "--MIME_boundary\r\n"
		. "Content-Type: text/xml; charset=UTF-8\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n"
		. "Content-ID: <root>\r\n"
		. "\r\n"
		. $soapEnvelope . "\r\n"
		. "--MIME_boundary\r\n"
		. "Content-Type: text/plain\r\n"
		. "Content-Transfer-Encoding: 8bit\r\n"
		. "Content-ID: <attachment1>\r\n"
		. "\r\n"
		. "Hello attachment\r\n"
		. "--MIME_boundary--\r\n";
}

// Test client decodes multipart/related response on PHP 8 (issue #127)
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://localhost/service');

	$envelope = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
		. '<SOAP-ENV:Body><ns1:sayHelloResponse xmlns:ns1="urn:TestService"><return xsi:type="xsd:string">Hello, World!</return></ns1:sayHelloResponse></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';

	$result = $client->parseResponse(
		['content-type' => 'multipart/related; type="text/xml"; boundary="MIME_boundary"'],
		createMultipartMessage($envelope)
	);

	Assert::same(['return' => 'Hello, World!'], $result);

	$attachments = $client->getAttachments();
	Assert::count(1, $attachments);
	Assert::same('Hello attachment', rtrim($attachments[0]['data']));
	Assert::same('<attachment1>', $attachments[0]['cid']);
});

// Test server decodes multipart/related request on PHP 8 (issue #127)
Toolkit::test(static function (): void {
	$server = new nusoap_server_mime();
	$server->configureWSDL('TestService', 'urn:TestService');
	$server->register('sayHello', ['name' => 'xsd:string'], ['return' => 'xsd:string'], 'urn:TestService', 'urn:TestService#sayHello');

	$envelope = '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema">'
		. '<SOAP-ENV:Body><ns1:sayHello xmlns:ns1="urn:TestService"><name xsi:type="xsd:string">World</name></ns1:sayHello></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';

	$server->parseRequest(
		['content-type' => 'multipart/related; type="text/xml"; boundary="MIME_boundary"'],
		createMultipartMessage($envelope)
	);

	Assert::same('sayHello', $server->methodname);
	Assert::same(['name' => 'World'], $server->methodparams);

	$attachments = $server->getAttachments();
	Assert::count(1, $attachments);
	Assert::same('Hello attachment', rtrim($attachments[0]['data']));
});
