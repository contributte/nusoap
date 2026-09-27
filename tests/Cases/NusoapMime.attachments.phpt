<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../src/nusoapmime.php';

// Test addAttachment stores the attachment and returns its content id
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://soap.invalid/service');

	Assert::same('my-cid', $client->addAttachment('data', 'file.txt', 'text/plain', 'my-cid'));
	Assert::match('%h%', $client->addAttachment('other'));

	Assert::same([
		'data' => 'data',
		'filename' => 'file.txt',
		'contenttype' => 'text/plain',
		'cid' => 'my-cid',
	], $client->requestAttachments[0]);
	Assert::same('application/octet-stream', $client->requestAttachments[1]['contenttype']);
});

// Test client without attachments sends a plain SOAP body
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://soap.invalid/service');

	Assert::same('<soap/>', $client->getHTTPBody('<soap/>'));
	Assert::same('text/xml', $client->getHTTPContentType());
	Assert::same('ISO-8859-1', $client->getHTTPContentTypeCharset());
});

// Test client with attachments sends a multipart/related body
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://soap.invalid/service');
	$client->addAttachment('attachment data', 'file.txt', 'text/plain', 'cid1');

	$body = $client->getHTTPBody('<soap/>');
	$contentType = $client->getHTTPContentType();

	Assert::match('multipart/related; type="text/xml"; %A%boundary="%a%"', $contentType);
	Assert::false($client->getHTTPContentTypeCharset());
	Assert::notContains("\r\n", $contentType);

	preg_match('~boundary="([^"]+)"~', $contentType, $matches);
	$parts = explode('--' . $matches[1], $body);

	Assert::count(4, $parts);
	Assert::contains('Content-Type: text/xml; charset=ISO-8859-1', $parts[1]);
	Assert::contains('<soap/>', $parts[1]);
	Assert::contains('Content-ID: <cid1>', $parts[2]);
	Assert::contains('Content-Transfer-Encoding: base64', $parts[2]);
	Assert::contains('filename=file.txt', $parts[2]);
	Assert::contains(base64_encode('attachment data'), $parts[2]);
});

// Test client reads attachment data from the file when no data is given
Toolkit::test(static function (): void {
	$file = Environment::getTestDir() . '/attachment.txt';
	file_put_contents($file, 'from file');

	$client = new nusoap_client_mime('http://soap.invalid/service');
	$client->addAttachment('', $file, 'text/plain', 'cid1');

	Assert::contains(base64_encode('from file'), $client->getHTTPBody('<soap/>'));
});

// Test clearAttachments switches back to a plain SOAP body
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://soap.invalid/service');
	$client->addAttachment('data');
	$client->clearAttachments();

	Assert::same([], $client->requestAttachments);
	Assert::same('<soap/>', $client->getHTTPBody('<soap/>'));
	Assert::same('text/xml', $client->getHTTPContentType());
});

// Test client attachments round trip through the MIME server decoding
Toolkit::test(static function (): void {
	$client = new nusoap_client_mime('http://soap.invalid/service');
	$client->addAttachment('attachment data', 'file.txt', 'text/plain', 'cid1');

	$envelope = '<?xml version="1.0" encoding="ISO-8859-1"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/">'
		. '<SOAP-ENV:Body><ns1:echo xmlns:ns1="urn:x"><v>1</v></ns1:echo></SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';
	$body = $client->getHTTPBody($envelope);

	$server = new nusoap_server_mime();
	$server->parseRequest(['content-type' => $client->getHTTPContentType()], $body);

	Assert::false($server->getError());
	Assert::same('echo', $server->methodname);
	Assert::same(['v' => '1'], $server->methodparams);
	// Content type and id are kept as sent in the MIME part headers
	Assert::same([
	[
		'data' => 'attachment data',
		'filename' => 'file.txt',
		'contenttype' => 'text/plain; name=file.txt',
		'cid' => '<cid1>',
	]], $server->getAttachments());
});

// Test server with attachments sends a multipart/related response
Toolkit::test(static function (): void {
	$server = new nusoap_server_mime();
	$server->addAttachment('response data', 'r.bin', 'application/octet-stream', 'rcid');
	$body = $server->getHTTPBody('<soap/>');

	Assert::match('multipart/related; type="text/xml"; %A%boundary="%a%"', $server->getHTTPContentType());
	Assert::false($server->getHTTPContentTypeCharset());
	Assert::contains('Content-ID: <rcid>', $body);
	Assert::contains(base64_encode('response data'), $body);

	$server->clearAttachments();
	Assert::same('<soap/>', $server->getHTTPBody('<soap/>'));
	Assert::same('text/xml', $server->getHTTPContentType());
});
