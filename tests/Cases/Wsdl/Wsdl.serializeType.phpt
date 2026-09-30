<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

function createWsdl(): wsdl
{
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService', 'http://soap.invalid/service');

	$server->wsdl->addComplexType('Person', 'complexType', 'struct', 'all', '', [
		'name' => ['name' => 'name', 'type' => 'xsd:string'],
		'age' => ['name' => 'age', 'type' => 'xsd:int'],
	]);
	$server->wsdl->addComplexType('Tagged', 'complexType', 'struct', 'all', '', [
		'label' => ['name' => 'label', 'type' => 'xsd:string'],
	], [
		'lang' => ['name' => 'lang', 'type' => 'xsd:string'],
	]);
	$server->wsdl->addComplexType('PersonList', 'complexType', 'array', '', 'SOAP-ENC:Array', [], [
		['ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:Person[]'],
	], 'tns:Person');
	$server->wsdl->addComplexType('Strings', 'complexType', 'array', '', 'SOAP-ENC:Array', [], [
		['ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'xsd:string[]'],
	], 'xsd:string');
	$server->wsdl->addSimpleType('Color', 'xsd:string', 'simpleType', 'scalar', ['red', 'blue']);
	$server->wsdl->addComplexType('Colors', 'complexType', 'array', '', 'SOAP-ENC:Array', [], [
		['ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:Color[]'],
	], 'tns:Color');

	return $server->wsdl;
}

// Test serializeType encodes XSD scalars with xsi types
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::same('<s xsi:type="xsd:string">a &amp; b</s>', $wsdl->serializeType('s', 'xsd:string', 'a & b'));
	Assert::same('<i xsi:type="xsd:int">5</i>', $wsdl->serializeType('i', 'xsd:int', 5));
	Assert::same('<b xsi:type="xsd:boolean">false</b>', $wsdl->serializeType('b', 'xsd:boolean', false));
	Assert::same('<b xsi:type="xsd:boolean">true</b>', $wsdl->serializeType('b', 'xsd:boolean', true));
	Assert::same('<n xsi:nil="true" xsi:type="xsd:string"/>', $wsdl->serializeType('n', 'xsd:string', null));
});

// Test serializeType literal mode omits xsi types
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::same('<s>a &amp; b</s>', $wsdl->serializeType('s', 'xsd:string', 'a & b', 'literal'));
	Assert::same('<b>true</b>', $wsdl->serializeType('b', 'xsd:boolean', true, 'literal'));
	Assert::same('<i>5</i>', $wsdl->serializeType('i', 'xsd:int', 5, 'literal'));
});

// Test serializeType encodes a struct complexType
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::same(
		'<p xsi:type="tns:Person"><name xsi:type="xsd:string">Joe &amp; Co</name><age xsi:type="xsd:int">30</age></p>',
		$wsdl->serializeType('p', 'tns:Person', ['name' => 'Joe & Co', 'age' => 30])
	);
	Assert::same(
		'<p><name>Joe</name><age>30</age></p>',
		$wsdl->serializeType('p', 'tns:Person', ['name' => 'Joe', 'age' => 30], 'literal')
	);
});

// Test serializeType accepts an object for a struct complexType
Toolkit::test(static function (): void {
	$person = new stdClass();
	$person->name = 'Joe';
	$person->age = 30;

	Assert::same(
		'<p xsi:type="tns:Person"><name xsi:type="xsd:string">Joe</name><age xsi:type="xsd:int">30</age></p>',
		createWsdl()->serializeType('p', 'tns:Person', $person)
	);
});

// Test serializeType serializes complexType attributes from "!" keys
Toolkit::test(static function (): void {
	Assert::same(
		'<t lang="en" xsi:type="tns:Tagged"><label xsi:type="xsd:string">Hi</label></t>',
		createWsdl()->serializeType('t', 'tns:Tagged', ['!lang' => 'en', 'label' => 'Hi'])
	);
});

// Test serializeType encodes SOAP-ENC arrays of structs and scalars
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::same(
		'<l xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="tns:Person[2]">'
		. '<item xsi:type="tns:Person"><name xsi:type="xsd:string">A</name><age xsi:type="xsd:int">1</age></item>'
		. '<item xsi:type="tns:Person"><name xsi:type="xsd:string">B</name><age xsi:type="xsd:int">2</age></item>'
		. '</l>',
		$wsdl->serializeType('l', 'tns:PersonList', [['name' => 'A', 'age' => 1], ['name' => 'B', 'age' => 2]])
	);
	Assert::same(
		'<s xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:string[2]"><item xsi:type="xsd:string">x</item><item xsi:type="xsd:string">y</item></s>',
		$wsdl->serializeType('s', 'tns:Strings', ['x', 'y'])
	);
	Assert::same(
		'<s xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="xsd:string[0]"></s>',
		$wsdl->serializeType('s', 'tns:Strings', [])
	);
});

// Test serializeType encodes an array of a simpleType
Toolkit::test(static function (): void {
	Assert::same(
		'<c xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="tns:Color[2]"><item xsi:type="tns:Color">red</item><item xsi:type="tns:Color">blue</item></c>',
		createWsdl()->serializeType('c', 'tns:Colors', ['red', 'blue'])
	);
});

// Test serializeType encodes a simpleType
Toolkit::test(static function (): void {
	Assert::same('<c xsi:type="tns:Color">red</c>', createWsdl()->serializeType('c', 'tns:Color', 'red'));
});

// Test serializeType reports unknown types
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::false($wsdl->serializeType('x', 'tns:Unknown', 5));
	Assert::same('tns:Unknown (Unknown) is not a supported type.', $wsdl->getError());
});

// Test serializeType writes the attributes of a soapval with an XSD type
Toolkit::test(static function (): void {
	$wsdl = createWsdl();
	$value = new soapval('price', 'decimal', '9.5', false, 'http://www.w3.org/2001/XMLSchema', ['currency' => 'EUR & co']);

	Assert::same('<price currency="EUR &amp; co" xsi:type="xsd:decimal">9.5</price>', $wsdl->serializeType('price', 'xsd:decimal', $value));
	Assert::same('<price currency="EUR &amp; co" xsi:type="xsd:decimal">9.5</price>', $wsdl->serializeType('price', 'xsd:decimal', $value, 'literal'));
});

// Test serializeType escapes string values of all XSD types
Toolkit::test(static function (): void {
	$wsdl = createWsdl();

	Assert::same('<u xsi:type="xsd:anyURI">http://example.com/?a=1&amp;b=2</u>', $wsdl->serializeType('u', 'xsd:anyURI', 'http://example.com/?a=1&b=2'));
	Assert::same('<t>a &lt;b&gt;</t>', $wsdl->serializeType('t', 'xsd:token', 'a <b>', 'literal'));
});

// Test serializeType escapes the simple content of a complexType
Toolkit::test(static function (): void {
	$wsdl = createWsdl();
	$wsdl->addComplexType('Note', 'complexType', 'struct', '', '', [], ['lang' => ['name' => 'lang', 'type' => 'xsd:string']]);
	$wsdl->schemas['urn:TestService'][0]->complexTypes['Note']['simpleContent'] = 'true';

	Assert::same(
		'<n lang="en" xsi:type="tns:Note">a &amp; &lt;b&gt;</n>',
		$wsdl->serializeType('n', 'tns:Note', ['!lang' => 'en', '!' => 'a & <b>'])
	);
});

// Test serializeType escapes values of a simpleType
Toolkit::test(static function (): void {
	$wsdl = createWsdl();
	$wsdl->addSimpleType('Company', 'xsd:string', 'simpleType', 'scalar', ['A&B']);

	Assert::same('<c xsi:type="tns:Company">A&amp;B</c>', $wsdl->serializeType('c', 'tns:Company', 'A&B'));
	Assert::same('<c>A&amp;B</c>', $wsdl->serializeType('c', 'tns:Company', 'A&B', 'literal'));
});

// Test serializeType serializes an untyped element holding a struct
Toolkit::test(static function (): void {
	$wsdl = createWsdl();
	$wsdl->addComplexType('Box', 'complexType', 'struct', 'all', '', ['content' => ['name' => 'content']]);

	Assert::same(
		'<b xsi:type="tns:Box"><content><a xsi:type="xsd:int">1</a></content></b>',
		$wsdl->serializeType('b', 'tns:Box', ['content' => ['a' => 1]])
	);
});

// Test serializeType serializes an Apache Map with struct values
Toolkit::test(static function (): void {
	Assert::match(
		'<m xsi:type="ns%d%:Map"><item><key xsi:type="xsd:string">k</key><value><a xsi:type="xsd:int">1</a></value></item></m>',
		createWsdl()->serializeType('m', 'http://xml.apache.org/xml-soap:Map', ['k' => ['a' => 1]])
	);
});

// Test serializeType serializes repeated untyped elements holding structs
Toolkit::test(static function (): void {
	$wsdl = createWsdl();
	$wsdl->addComplexType('Boxes', 'complexType', 'struct', 'sequence', '', ['box' => ['name' => 'box', 'maxOccurs' => 'unbounded']]);

	Assert::same(
		'<b xsi:type="tns:Boxes"><box><a xsi:type="xsd:int">1</a></box><box><a xsi:type="xsd:int">2</a></box></b>',
		$wsdl->serializeType('b', 'tns:Boxes', ['box' => [['a' => 1], ['a' => 2]]])
	);
});
