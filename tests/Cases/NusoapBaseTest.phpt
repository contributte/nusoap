<?php

declare(strict_types=1);

use Tester\Assert;

require __DIR__ . '/../bootstrap.php';


// Test nusoap_base instantiation
$base = new nusoap_base();
Assert::type('nusoap_base', $base);


// Test default properties
Assert::same('NuSOAP', $base->title);
Assert::same('0.9.17', $base->version);
Assert::same('ISO-8859-1', $base->soap_defencoding);
Assert::same('http://www.w3.org/2001/XMLSchema', $base->XMLSchemaVersion);
Assert::same('text/xml', $base->contentType);


// Test namespaces
Assert::same('http://schemas.xmlsoap.org/soap/envelope/', $base->namespaces['SOAP-ENV']);
Assert::same('http://www.w3.org/2001/XMLSchema', $base->namespaces['xsd']);
Assert::same('http://www.w3.org/2001/XMLSchema-instance', $base->namespaces['xsi']);
Assert::same('http://schemas.xmlsoap.org/soap/encoding/', $base->namespaces['SOAP-ENC']);


// Test debug level methods
$originalLevel = $base->getDebugLevel();
Assert::type('int', $originalLevel);

$base->setDebugLevel(5);
Assert::same(5, $base->getDebugLevel());

$base->setDebugLevel(0);
Assert::same(0, $base->getDebugLevel());


// Test global debug level
$globalLevel = $base->getGlobalDebugLevel();
Assert::type('int', $globalLevel);

$base->setGlobalDebugLevel(3);
Assert::same(3, $base->getGlobalDebugLevel());

// Reset to original
$base->setGlobalDebugLevel($globalLevel);


// Test error handling
Assert::false($base->getError());

$base->setError('Test error message');
Assert::same('Test error message', $base->getError());


// Test debug string handling
$base->setDebugLevel(5);
$base->clearDebug();
Assert::same('', $base->getDebug());

$base->appendDebug('Test debug');
Assert::contains('Test debug', $base->getDebug());

$base->clearDebug();
Assert::same('', $base->getDebug());


// Test getDebugAsXMLComment
$base->clearDebug();
$base->appendDebug('Test comment');
$comment = $base->getDebugAsXMLComment();
Assert::contains('<!--', $comment);
Assert::contains('-->', $comment);
Assert::contains('Test comment', $comment);


// Test expandEntities
$base2 = new nusoap_base();
Assert::same('&amp;', $base2->expandEntities('&'));
Assert::same('&lt;', $base2->expandEntities('<'));
Assert::same('&gt;', $base2->expandEntities('>'));
Assert::same('&apos;', $base2->expandEntities("'"));
Assert::same('&quot;', $base2->expandEntities('"'));
Assert::same('hello &amp; world', $base2->expandEntities('hello & world'));


// Test isArraySimpleOrStruct
Assert::same('arraySimple', $base->isArraySimpleOrStruct([1, 2, 3]));
Assert::same('arraySimple', $base->isArraySimpleOrStruct(['a', 'b', 'c']));
Assert::same('arrayStruct', $base->isArraySimpleOrStruct(['foo' => 'bar', 'baz' => 'qux']));
Assert::same('arrayStruct', $base->isArraySimpleOrStruct(['name' => 'John', 'age' => 30]));


// Test getLocalPart
Assert::same('localPart', $base->getLocalPart('prefix:localPart'));
Assert::same('noPrefixString', $base->getLocalPart('noPrefixString'));
Assert::same('last', $base->getLocalPart('first:second:last'));


// Test getPrefix
Assert::same('prefix', $base->getPrefix('prefix:localPart'));
Assert::false($base->getPrefix('noPrefixString'));
Assert::same('first:second', $base->getPrefix('first:second:last'));


// Test getNamespaceFromPrefix
Assert::same('http://schemas.xmlsoap.org/soap/envelope/', $base->getNamespaceFromPrefix('SOAP-ENV'));
Assert::same('http://www.w3.org/2001/XMLSchema', $base->getNamespaceFromPrefix('xsd'));
Assert::false($base->getNamespaceFromPrefix('nonexistent'));


// Test getPrefixFromNamespace
Assert::same('SOAP-ENV', $base->getPrefixFromNamespace('http://schemas.xmlsoap.org/soap/envelope/'));
Assert::same('xsd', $base->getPrefixFromNamespace('http://www.w3.org/2001/XMLSchema'));
Assert::false($base->getPrefixFromNamespace('http://nonexistent.namespace/'));


// Test getmicrotime
$microtime = $base->getmicrotime();
Assert::type('string', $microtime);
Assert::match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d+$~', $microtime);


// Test varDump
$dump = $base->varDump(['test' => 'value']);
Assert::type('string', $dump);
Assert::contains('test', $dump);
Assert::contains('value', $dump);


// Test __toString
$str = (string)$base;
Assert::type('string', $str);
Assert::contains('nusoap_base', $str);


// Test serialize_val with various types
$base3 = new nusoap_base();
$base3->setDebugLevel(0);

// Test null serialization
$xml = $base3->serialize_val(null, 'nullValue');
Assert::contains('nullValue', $xml);
Assert::contains('xsi:nil="true"', $xml);

// Test string serialization
$xml = $base3->serialize_val('hello', 'stringValue');
Assert::contains('stringValue', $xml);
Assert::contains('hello', $xml);

// Test integer serialization
$xml = $base3->serialize_val(42, 'intValue');
Assert::contains('intValue', $xml);
Assert::contains('42', $xml);

// Test boolean serialization
$xml = $base3->serialize_val(true, 'boolValue');
Assert::contains('boolValue', $xml);

// Test array serialization
$xml = $base3->serialize_val(['item1', 'item2'], 'arrayValue');
Assert::contains('arrayValue', $xml);


// Test serializeEnvelope
$envelope = $base->serializeEnvelope('<test>body</test>');
Assert::contains('<?xml version="1.0"', $envelope);
Assert::contains('SOAP-ENV:Envelope', $envelope);
Assert::contains('SOAP-ENV:Body', $envelope);
Assert::contains('<test>body</test>', $envelope);

// Test serializeEnvelope with headers
$envelope = $base->serializeEnvelope('<test>body</test>', '<header>value</header>');
Assert::contains('SOAP-ENV:Header', $envelope);
Assert::contains('<header>value</header>', $envelope);


echo "All nusoap_base tests passed!\n";
