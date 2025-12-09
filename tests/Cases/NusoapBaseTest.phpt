<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test nusoap_base instantiation and default properties
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::type('nusoap_base', $base);
	Assert::same('NuSOAP', $base->title);
	Assert::same('0.9.17', $base->version);
	Assert::same('ISO-8859-1', $base->soap_defencoding);
	Assert::same('http://www.w3.org/2001/XMLSchema', $base->XMLSchemaVersion);
	Assert::same('text/xml', $base->contentType);
});

// Test default namespaces
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('http://schemas.xmlsoap.org/soap/envelope/', $base->namespaces['SOAP-ENV']);
	Assert::same('http://www.w3.org/2001/XMLSchema', $base->namespaces['xsd']);
	Assert::same('http://www.w3.org/2001/XMLSchema-instance', $base->namespaces['xsi']);
	Assert::same('http://schemas.xmlsoap.org/soap/encoding/', $base->namespaces['SOAP-ENC']);
});

// Test debug level methods
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$originalLevel = $base->getDebugLevel();
	Assert::type('int', $originalLevel);

	$base->setDebugLevel(5);
	Assert::same(5, $base->getDebugLevel());

	$base->setDebugLevel(0);
	Assert::same(0, $base->getDebugLevel());
});

// Test global debug level
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$globalLevel = $base->getGlobalDebugLevel();
	Assert::type('int', $globalLevel);

	$base->setGlobalDebugLevel(3);
	Assert::same(3, $base->getGlobalDebugLevel());

	// Reset to original
	$base->setGlobalDebugLevel($globalLevel);
});

// Test error handling
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::false($base->getError());

	$base->setError('Test error message');
	Assert::same('Test error message', $base->getError());
});

// Test debug string handling
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(5);

	$base->clearDebug();
	Assert::same('', $base->getDebug());

	$base->appendDebug('Test debug');
	Assert::contains('Test debug', $base->getDebug());

	$base->clearDebug();
	Assert::same('', $base->getDebug());
});

// Test getDebugAsXMLComment
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(5);

	$base->clearDebug();
	$base->appendDebug('Test comment');
	$comment = $base->getDebugAsXMLComment();

	Assert::contains('<!--', $comment);
	Assert::contains('-->', $comment);
	Assert::contains('Test comment', $comment);
});

// Test expandEntities
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('&amp;', $base->expandEntities('&'));
	Assert::same('&lt;', $base->expandEntities('<'));
	Assert::same('&gt;', $base->expandEntities('>'));
	Assert::same('&apos;', $base->expandEntities("'"));
	Assert::same('&quot;', $base->expandEntities('"'));
	Assert::same('hello &amp; world', $base->expandEntities('hello & world'));
});

// Test isArraySimpleOrStruct
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('arraySimple', $base->isArraySimpleOrStruct([1, 2, 3]));
	Assert::same('arraySimple', $base->isArraySimpleOrStruct(['a', 'b', 'c']));
	Assert::same('arrayStruct', $base->isArraySimpleOrStruct(['foo' => 'bar', 'baz' => 'qux']));
	Assert::same('arrayStruct', $base->isArraySimpleOrStruct(['name' => 'John', 'age' => 30]));
});

// Test getLocalPart
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('localPart', $base->getLocalPart('prefix:localPart'));
	Assert::same('noPrefixString', $base->getLocalPart('noPrefixString'));
	Assert::same('last', $base->getLocalPart('first:second:last'));
});

// Test getPrefix
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('prefix', $base->getPrefix('prefix:localPart'));
	Assert::false($base->getPrefix('noPrefixString'));
	Assert::same('first:second', $base->getPrefix('first:second:last'));
});

// Test getNamespaceFromPrefix
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('http://schemas.xmlsoap.org/soap/envelope/', $base->getNamespaceFromPrefix('SOAP-ENV'));
	Assert::same('http://www.w3.org/2001/XMLSchema', $base->getNamespaceFromPrefix('xsd'));
	Assert::false($base->getNamespaceFromPrefix('nonexistent'));
});

// Test getPrefixFromNamespace
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('SOAP-ENV', $base->getPrefixFromNamespace('http://schemas.xmlsoap.org/soap/envelope/'));
	Assert::same('xsd', $base->getPrefixFromNamespace('http://www.w3.org/2001/XMLSchema'));
	Assert::false($base->getPrefixFromNamespace('http://nonexistent.namespace/'));
});

// Test getmicrotime
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$microtime = $base->getmicrotime();
	Assert::type('string', $microtime);
	Assert::match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$~', $microtime);
});

// Test varDump
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$dump = $base->varDump(['test' => 'value']);
	Assert::type('string', $dump);
	Assert::contains('test', $dump);
	Assert::contains('value', $dump);
});

// Test __toString
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$str = (string) $base;
	Assert::type('string', $str);
	Assert::contains('nusoap_base', $str);
});

// Test serialize_val with null
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(0);

	$xml = $base->serialize_val(null, 'nullValue');
	Assert::contains('nullValue', $xml);
	Assert::contains('xsi:nil="true"', $xml);
});

// Test serialize_val with string
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(0);

	$xml = $base->serialize_val('hello', 'stringValue');
	Assert::contains('stringValue', $xml);
	Assert::contains('hello', $xml);
});

// Test serialize_val with integer
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(0);

	$xml = $base->serialize_val(42, 'intValue');
	Assert::contains('intValue', $xml);
	Assert::contains('42', $xml);
});

// Test serialize_val with boolean
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(0);

	$xml = $base->serialize_val(true, 'boolValue');
	Assert::contains('boolValue', $xml);
});

// Test serialize_val with array
Toolkit::test(static function (): void {
	$base = new nusoap_base();
	$base->setDebugLevel(0);

	$xml = $base->serialize_val(['item1', 'item2'], 'arrayValue');
	Assert::contains('arrayValue', $xml);
});

// Test serializeEnvelope
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$envelope = $base->serializeEnvelope('<test>body</test>');
	Assert::contains('<?xml version="1.0"', $envelope);
	Assert::contains('SOAP-ENV:Envelope', $envelope);
	Assert::contains('SOAP-ENV:Body', $envelope);
	Assert::contains('<test>body</test>', $envelope);
});

// Test serializeEnvelope with headers
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	$envelope = $base->serializeEnvelope('<test>body</test>', '<header>value</header>');
	Assert::contains('SOAP-ENV:Header', $envelope);
	Assert::contains('<header>value</header>', $envelope);
});
