<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

function envelope(string $body, string $header = ''): string
{
	return '<?xml version="1.0" encoding="UTF-8"?>'
		. '<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"'
		. ' xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"'
		. ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"'
		. ' xmlns:xsd="http://www.w3.org/2001/XMLSchema"'
		. ' xmlns:apache="http://xml.apache.org/xml-soap">'
		. ($header !== '' ? '<SOAP-ENV:Header>' . $header . '</SOAP-ENV:Header>' : '')
		. '<SOAP-ENV:Body>' . $body . '</SOAP-ENV:Body>'
		. '</SOAP-ENV:Envelope>';
}

function parse(string $body, string $header = ''): nusoap_parser
{
	$parser = new nusoap_parser(envelope($body, $header));
	Assert::false($parser->getError());

	return $parser;
}

// Test parser decodes simple xsi types into PHP values
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<int xsi:type="xsd:int">42</int>'
		. '<double xsi:type="xsd:double">1.5</double>'
		. '<decimal xsi:type="xsd:decimal">2.25</decimal>'
		. '<false xsi:type="xsd:boolean">false</false>'
		. '<f xsi:type="xsd:boolean">f</f>'
		. '<true xsi:type="xsd:boolean">1</true>'
		. '<base64 xsi:type="xsd:base64Binary">SGVsbG8=</base64>'
		. '<unsigned xsi:type="xsd:unsignedInt">7</unsigned>'
		. '<positive xsi:type="xsd:positiveInteger">8</positive>'
		. '<long xsi:type="xsd:long">9999999999</long>'
		. '<date xsi:type="xsd:dateTime">2023-06-15T12:00:00Z</date>'
		. '<nil xsi:nil="true"/>'
		. '<untyped>text</untyped>'
		. '</ns1:r>');

	Assert::same([
		'int' => 42,
		'double' => 1.5,
		'decimal' => 2.25,
		'false' => false,
		'f' => false,
		'true' => true,
		'base64' => 'Hello',
		'unsigned' => 7,
		'positive' => 8,
		'long' => '9999999999',
		'date' => '2023-06-15T12:00:00Z',
		'nil' => null,
		'untyped' => 'text',
	], $parser->get_soapbody());
});

// Test parser exposes the method element name and namespace
Toolkit::test(static function (): void {
	$parser = parse('<ns1:getResponse xmlns:ns1="urn:x"><return>1</return></ns1:getResponse>');

	Assert::same('getResponse', $parser->root_struct_name);
	Assert::same('urn:x', $parser->root_struct_namespace);
	Assert::same($parser->get_soapbody(), $parser->get_response());
	Assert::null($parser->get_soapheader());
	Assert::same('', $parser->getHeaders());
});

// Test parser decodes SOAP-ENC arrays using the arrayType
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<return xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:int[3]"><item>1</item><item>2</item><item>3</item></return>'
		. '</ns1:r>');

	Assert::same(['return' => [1, 2, 3]], $parser->get_soapbody());
});

// Test parser decodes empty SOAP-ENC arrays
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<return xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:string[0]"/>'
		. '</ns1:r>');

	Assert::same(['return' => []], $parser->get_soapbody());
});

// Test parser decodes two-dimensional SOAP-ENC arrays into rows
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<return SOAP-ENC:arrayType="xsd:int[2,2]"><item>1</item><item>2</item><item>3</item><item>4</item></return>'
		. '</ns1:r>');

	Assert::same(['return' => [[1, 2], [3, 4]]], $parser->get_soapbody());
});

// Test parser decodes nested structs and turns repeated elements into a list
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<person><name>Joe</name><address><city>Prague</city></address></person>'
		. '<tag>a</tag><tag>b</tag><tag>c</tag>'
		. '</ns1:r>');

	Assert::same([
		'person' => ['name' => 'Joe', 'address' => ['city' => 'Prague']],
		'tag' => ['a', 'b', 'c'],
	], $parser->get_soapbody());
});

// Test parser decodes Apache Map into an associative array and Vector into a list
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<map xsi:type="apache:Map">'
		. '<item><key>k1</key><value>v1</value></item>'
		. '<item><key>k2</key><value>v2</value></item>'
		. '</map>'
		. '<vector xsi:type="apache:Vector"><item>a</item><item>b</item></vector>'
		. '</ns1:r>');

	Assert::same([
		'map' => ['k1' => 'v1', 'k2' => 'v2'],
		'vector' => ['a', 'b'],
	], $parser->get_soapbody());
});

// Test parser keeps attributes prefixed with "!" and simple content under "!"
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x">'
		. '<price currency="EUR" xsi:type="xsd:double">9.5</price>'
		. '<person id="p1"><name>Joe</name></person>'
		. '<empty flag="yes"/>'
		. '</ns1:r>');

	Assert::same([
		'price' => ['!currency' => 'EUR', '!' => 9.5],
		'person' => ['name' => 'Joe', '!id' => 'p1'],
		'empty' => ['!flag' => 'yes'],
	], $parser->get_soapbody());
});

// Test parser resolves href references to multiRef elements
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x"><first href="#id1"/><second href="#id1"/></ns1:r>'
		. '<multiRef id="id1"><name>Joe</name><age xsi:type="xsd:int">30</age></multiRef>');

	Assert::same([
		'first' => ['name' => 'Joe', 'age' => 30],
		'second' => ['name' => 'Joe', 'age' => 30],
	], $parser->get_soapbody());
});

// Test parser uses the element marked with root="1" as the method element
Toolkit::test(static function (): void {
	$parser = parse('<helper id="h1"><v>ignored</v></helper>'
		. '<ns1:r xmlns:ns1="urn:x" SOAP-ENC:root="1"><v>root</v></ns1:r>');

	Assert::same('r', $parser->root_struct_name);
	Assert::same(['v' => 'root'], $parser->get_soapbody());
});

// Test parser decodes the SOAP Header separately from the Body
Toolkit::test(static function (): void {
	$parser = parse(
		'<ns1:r xmlns:ns1="urn:x"><v>1</v></ns1:r>',
		'<ns2:Auth xmlns:ns2="urn:h"><token>abc</token></ns2:Auth>'
	);

	Assert::same(['v' => '1'], $parser->get_soapbody());
	Assert::same(['Auth' => ['token' => 'abc']], $parser->get_soapheader());
	Assert::same('<ns2:Auth xmlns:ns2="urn:h"><token>abc</token></ns2:Auth>', $parser->getHeaders());
});

// Test parser decodes a SOAP fault
Toolkit::test(static function (): void {
	$parser = parse('<SOAP-ENV:Fault><faultcode>SOAP-ENV:Client</faultcode><faultstring>Bad input</faultstring></SOAP-ENV:Fault>');

	Assert::same('Fault', $parser->root_struct_name);
	Assert::same(['faultcode' => 'SOAP-ENV:Client', 'faultstring' => 'Bad input'], $parser->get_soapbody());
});

// Test parser decodes entities and converts UTF-8 content to ISO-8859-1 by default
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x"><v>a &amp; b &lt;c&gt; café</v></ns1:r>');

	Assert::same(['v' => "a & b <c> caf\xE9"], $parser->get_soapbody());
});

// Test parser keeps UTF-8 content when decode_utf8 is disabled
Toolkit::test(static function (): void {
	$parser = new nusoap_parser(envelope('<ns1:r xmlns:ns1="urn:x"><v>Žluťoučký kůň</v></ns1:r>'), 'UTF-8', '', false);

	Assert::same(['v' => 'Žluťoučký kůň'], $parser->get_soapbody());
});

// Test parser keeps entities escaped in the header and document XML
Toolkit::test(static function (): void {
	$parser = parse(
		'<ns1:r xmlns:ns1="urn:x"><v>a &amp; &lt;b&gt;</v></ns1:r>',
		'<ns2:Auth xmlns:ns2="urn:h"><token>x&amp;y</token></ns2:Auth>'
	);

	Assert::same(['v' => 'a & <b>'], $parser->get_soapbody());
	Assert::same('<ns2:Auth xmlns:ns2="urn:h"><token>x&amp;y</token></ns2:Auth>', $parser->getHeaders());
	Assert::same('<ns1:r xmlns:ns1="urn:x"><v>a &amp; &lt;b&gt;</v></ns1:r>', $parser->document);
});

// Test parser keeps attribute values escaped in the header and document XML
Toolkit::test(static function (): void {
	$parser = parse(
		'<ns1:r xmlns:ns1="urn:x"><v note="&quot;a&quot; &amp; &lt;b&gt;">1</v></ns1:r>',
		'<ns2:Auth xmlns:ns2="urn:h" realm="a&amp;b"/>'
	);

	Assert::same(['v' => ['!note' => '"a" & <b>', '!' => '1']], $parser->get_soapbody());
	Assert::same('<ns2:Auth xmlns:ns2="urn:h" realm="a&amp;b"></ns2:Auth>', $parser->getHeaders());
	Assert::same('<ns1:r xmlns:ns1="urn:x"><v note="&quot;a&quot; &amp; &lt;b&gt;">1</v></ns1:r>', $parser->document);
});

// Test parser does not take text resembling MIME headers for attachments
Toolkit::test(static function (): void {
	$parser = parse('<ns1:r xmlns:ns1="urn:x"><text>Hello' . "\n-- \nJoe\nContent-Type: text/plain" . '</text></ns1:r>');

	Assert::same(['text' => "Hello\n-- \nJoe\nContent-Type: text/plain"], $parser->get_soapbody());
	Assert::same([], $parser->attachments);
});

// Test parser reports malformed and empty XML
Toolkit::test(static function (): void {
	$parser = new nusoap_parser(envelope('<ns1:r xmlns:ns1="urn:x"><v></ns1:r>'));
	Assert::match('XML error parsing SOAP payload on line 1: Mismatched tag', $parser->getError());

	$parser = new nusoap_parser('');
	Assert::same("xml was empty, didn't parse!", $parser->getError());
});

// Test parser accepts an element with an undeclared namespace prefix
Toolkit::test(static function (): void {
	$parser = new nusoap_parser(envelope('<ns1:r><v>1</v></ns1:r>'));

	Assert::false($parser->getError());
	Assert::same(['v' => '1'], $parser->get_soapbody());
	Assert::same('', $parser->root_struct_namespace);
});

// Test parser accepts an empty SOAP Body
Toolkit::test(static function (): void {
	$parser = new nusoap_parser(envelope(''));

	Assert::false($parser->getError());
	Assert::null($parser->get_soapbody());
	Assert::same('', $parser->root_struct_name);
});

// Test parser reports truncated XML
Toolkit::test(static function (): void {
	$xml = envelope('<ns1:r xmlns:ns1="urn:x"><v>1</v></ns1:r>');
	$parser = new nusoap_parser(substr($xml, 0, -20));

	Assert::match('XML error parsing SOAP payload on line 1: %a%', $parser->getError());
	Assert::null($parser->get_soapbody());
});

// Test parser rejects an XML declaration encoding that differs from the HTTP charset
Toolkit::test(static function (): void {
	$xml = str_replace('encoding="UTF-8"', 'encoding="ISO-8859-2"', envelope('<ns1:r xmlns:ns1="urn:x"/>'));
	$parser = new nusoap_parser($xml, 'UTF-8');

	Assert::same("Charset from HTTP Content-Type 'UTF-8' does not match encoding from XML declaration 'ISO-8859-2'", $parser->getError());
});

// Test parser accepts UTF-8 XML declared as ISO-8859-1 by the HTTP charset
Toolkit::test(static function (): void {
	$parser = new nusoap_parser(envelope('<ns1:r xmlns:ns1="urn:x"><v>1</v></ns1:r>'), 'ISO-8859-1');

	Assert::false($parser->getError());
	Assert::same(['v' => '1'], $parser->get_soapbody());
});

// Test soap_parser backward compatibility class
Toolkit::test(static function (): void {
	$parser = new soap_parser(envelope('<ns1:r xmlns:ns1="urn:x"><v>1</v></ns1:r>'));

	Assert::type(nusoap_parser::class, $parser);
	Assert::same(['v' => '1'], $parser->get_soapbody());
});
