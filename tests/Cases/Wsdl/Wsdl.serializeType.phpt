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
