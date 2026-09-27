<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test soapval stores its constructor arguments
Toolkit::test(static function (): void {
	$val = new soapval('name', 'string', 'Joe', 'urn:element', 'urn:type', ['lang' => 'en']);

	Assert::same('name', $val->name);
	Assert::same('string', $val->type);
	Assert::same('Joe', $val->value);
	Assert::same('urn:element', $val->element_ns);
	Assert::same('urn:type', $val->type_ns);
	Assert::same(['lang' => 'en'], $val->attributes);
	Assert::same('Joe', $val->decode());
});

// Test soapval defaults
Toolkit::test(static function (): void {
	$val = new soapval();

	Assert::same('soapval', $val->name);
	Assert::false($val->type);
	Assert::same(-1, $val->value);
});

// Test soapval serializes encoded and literal
Toolkit::test(static function (): void {
	$val = new soapval('name', 'string', 'Joe & Co');

	Assert::same('<name xsi:type="xsd:string">Joe &amp; Co</name>', $val->serialize());
	Assert::same('<name>Joe &amp; Co</name>', $val->serialize('literal'));
});

// Test soapval serializes the element namespace and attributes
Toolkit::test(static function (): void {
	$val = new soapval('item', 'string', 'x', 'urn:element', false, ['lang' => 'en']);

	Assert::match('<nu%d%:item xmlns:nu%d%="urn:element" xsi:type="xsd:string" lang="en">x</nu%d%:item>', $val->serialize());
});

// Test soapval serializes a scalar with a custom type in its own namespace
Toolkit::test(static function (): void {
	Assert::match('<code xmlns:ns%d%="urn:type" xsi:type="ns%d%:Currency">EUR &amp; co</code>', (new soapval('code', 'Currency', 'EUR & co', false, 'urn:type'))->serialize());
	Assert::match('<n xmlns:ns%d%="urn:type" xsi:type="ns%d%:Count">5</n>', (new soapval('n', 'Count', 5, false, 'urn:type'))->serialize());
	Assert::match('<f xmlns:ns%d%="urn:type" xsi:type="ns%d%:Flag">1</f>', (new soapval('f', 'Flag', true, false, 'urn:type'))->serialize());

	// Literal use has no xsi:type
	Assert::match('<code xmlns:ns%d%="urn:type">EUR</code>', (new soapval('code', 'Currency', 'EUR', false, 'urn:type'))->serialize('literal'));
});

// Test soapval serializes a struct with a custom type in its own namespace
Toolkit::test(static function (): void {
	$val = new soapval('person', 'Person', ['name' => 'Joe'], false, 'urn:type');

	Assert::match('<person xmlns:ns%d%="urn:type" xsi:type="ns%d%:Person"><name xsi:type="xsd:string">Joe</name></person>', $val->serialize());
});

// Test soapval serializes a struct value
Toolkit::test(static function (): void {
	$val = new soapval('person', false, ['name' => 'Joe', 'age' => 30]);

	Assert::same('<person><name xsi:type="xsd:string">Joe</name><age xsi:type="xsd:int">30</age></person>', $val->serialize());
});

// Test serialize_val serializes a soapval nested in an array
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same(
		'<params><name xsi:type="xsd:string">Joe</name></params>',
		$base->serialize_val(['n' => new soapval('name', 'string', 'Joe')], 'params')
	);
});

// Test serialize_val serializes floats, lists of structs and attributes
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('<f xsi:type="xsd:float">1.5</f>', $base->serialize_val(1.5, 'f'));
	Assert::same('<n xsi:type="xsd:string" lang="en">x</n>', $base->serialize_val('x', 'n', 'string', false, false, ['lang' => 'en']));
	Assert::same(
		'<list xsi:type="SOAP-ENC:Array" SOAP-ENC:arrayType="unnamed_struct_use_soapval[2]">'
		. '<item><a xsi:type="xsd:int">1</a></item><item><a xsi:type="xsd:int">2</a></item></list>',
		$base->serialize_val([['a' => 1], ['a' => 2]], 'list')
	);
});

// Test serialize_val literal mode omits xsi types
Toolkit::test(static function (): void {
	$base = new nusoap_base();

	Assert::same('<s>a&lt;b</s>', $base->serialize_val('a<b', 's', false, false, false, false, 'literal'));
	Assert::same('<i>1</i>', $base->serialize_val(1, 'i', false, false, false, false, 'literal'));
	Assert::same('<n/>', $base->serialize_val(null, 'n', false, false, false, false, 'literal'));
});
