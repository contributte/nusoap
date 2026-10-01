<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

const SCHEMA = '<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="urn:t"'
	. ' xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/" xmlns:wsdl="http://schemas.xmlsoap.org/wsdl/"'
	. ' targetNamespace="urn:t" elementFormDefault="qualified">'
	. '<xsd:complexType name="Person"><xsd:sequence>'
	. '<xsd:element name="name" type="xsd:string"/>'
	. '<xsd:element name="age" type="xsd:int" minOccurs="0"/>'
	. '</xsd:sequence><xsd:attribute name="id" type="xsd:string"/></xsd:complexType>'
	. '<xsd:complexType name="PersonArray"><xsd:complexContent><xsd:restriction base="SOAP-ENC:Array">'
	. '<xsd:attribute ref="SOAP-ENC:arrayType" wsdl:arrayType="tns:Person[]"/>'
	. '</xsd:restriction></xsd:complexContent></xsd:complexType>'
	. '<xsd:complexType name="Employee"><xsd:complexContent><xsd:extension base="tns:Person"><xsd:sequence>'
	. '<xsd:element name="salary" type="xsd:double"/>'
	. '</xsd:sequence></xsd:extension></xsd:complexContent></xsd:complexType>'
	. '<xsd:simpleType name="Color"><xsd:restriction base="xsd:string">'
	. '<xsd:enumeration value="red"/><xsd:enumeration value="blue"/>'
	. '</xsd:restriction></xsd:simpleType>'
	. '<xsd:element name="getPerson"><xsd:complexType><xsd:sequence>'
	. '<xsd:element name="id" type="xsd:int"/>'
	. '</xsd:sequence></xsd:complexType></xsd:element>'
	. '<xsd:element name="count" type="xsd:int"/>'
	. '</xsd:schema>';

function parseSchema(): nusoap_xmlschema
{
	$schema = new nusoap_xmlschema('', '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);
	$schema->parseString(SCHEMA, 'schema');
	Assert::false($schema->getError());

	return $schema;
}

// Test schema parsing reads the target namespace and a sequence complexType
Toolkit::test(static function (): void {
	$schema = parseSchema();

	Assert::same('urn:t', $schema->schemaTargetNamespace);
	Assert::same([
		'name' => 'Person',
		'typeClass' => 'complexType',
		'phpType' => 'struct',
		'simpleContent' => 'false',
		'compositor' => 'sequence',
		'elements' => [
			'name' => ['name' => 'name', 'type' => 'http://www.w3.org/2001/XMLSchema:string', 'form' => 'qualified'],
			'age' => ['name' => 'age', 'type' => 'http://www.w3.org/2001/XMLSchema:int', 'minOccurs' => '0', 'form' => 'qualified'],
		],
		'attrs' => [
			'id' => ['name' => 'id', 'type' => 'http://www.w3.org/2001/XMLSchema:string', 'form' => 'unqualified'],
		],
	], $schema->complexTypes['Person']);
});

// Test schema parsing reads a SOAP-ENC array restriction
Toolkit::test(static function (): void {
	$type = parseSchema()->complexTypes['PersonArray'];

	Assert::same('array', $type['phpType']);
	Assert::same('http://schemas.xmlsoap.org/soap/encoding/:Array', $type['restrictionBase']);
	Assert::same('urn:t:Person', $type['arrayType']);
});

// Test schema parsing reads a complexContent extension
Toolkit::test(static function (): void {
	$type = parseSchema()->complexTypes['Employee'];

	Assert::same('struct', $type['phpType']);
	Assert::same('urn:t:Person', $type['extensionBase']);
	Assert::same(['salary'], array_keys($type['elements']));
});

// Test schema parsing reads a simpleType enumeration
Toolkit::test(static function (): void {
	Assert::same([
		'name' => 'Color',
		'typeClass' => 'simpleType',
		'phpType' => 'scalar',
		'type' => 'http://www.w3.org/2001/XMLSchema:string',
		'enumeration' => ['red', 'blue'],
	], parseSchema()->simpleTypes['Color']);
});

// Test schema parsing names anonymous complexTypes after their element
Toolkit::test(static function (): void {
	$schema = parseSchema();

	Assert::same('urn:t:_getPerson_ContainedType', $schema->elements['getPerson']['type']);
	Assert::same('element', $schema->elements['getPerson']['typeClass']);
	Assert::same(['id'], array_keys($schema->complexTypes['_getPerson_ContainedType']['elements']));
});

// Test getTypeDef finds complex types, simple types and elements
Toolkit::test(static function (): void {
	$schema = parseSchema();

	Assert::same('Person', $schema->getTypeDef('Person')['name']);
	Assert::same('Color', $schema->getTypeDef('Color')['name']);

	// Element of an XSD type gets a scalar PHP type
	$count = $schema->getTypeDef('count^');
	Assert::same('element', $count['typeClass']);
	Assert::same('scalar', $count['phpType']);

	// Element of a complex type takes over its PHP type and elements
	$getPerson = $schema->getTypeDef('getPerson^');
	Assert::same('struct', $getPerson['phpType']);
	Assert::same(['id'], array_keys($getPerson['elements']));

	Assert::false($schema->getTypeDef('Missing'));
});

// Test getTypeDef treats unknown contained types as strings
Toolkit::test(static function (): void {
	Assert::same([
		'typeClass' => 'simpleType',
		'phpType' => 'scalar',
		'type' => 'http://www.w3.org/2001/XMLSchema:string',
	], (new nusoap_xmlschema())->getTypeDef('Foo_bar_ContainedType'));
});

// Test getPHPType maps XSD types and complex types
Toolkit::test(static function (): void {
	$schema = parseSchema();

	Assert::same('string', $schema->getPHPType('string', 'http://www.w3.org/2001/XMLSchema'));
	Assert::same('integer', $schema->getPHPType('int', 'http://www.w3.org/2001/XMLSchema'));
	Assert::same('struct', $schema->getPHPType('Person', 'urn:t'));
	Assert::same('array', $schema->getPHPType('PersonArray', 'urn:t'));
	Assert::false($schema->getPHPType('Missing', 'urn:t'));
});

// Test serializeSchema writes types back using prefixes
Toolkit::test(static function (): void {
	$xml = parseSchema()->serializeSchema();

	Assert::contains('targetNamespace="urn:t"', $xml);
	Assert::contains('<xsd:complexType name="Person">', $xml);
	Assert::contains('<xsd:element name="name" type="xsd:string" form="qualified"/>', $xml);
	Assert::contains('<xsd:element name="age" type="xsd:int" minOccurs="0" form="qualified"/>', $xml);
	Assert::contains('<xsd:restriction base="SOAP-ENC:Array">', $xml);
	Assert::contains('<xsd:attribute ref="SOAP-ENC:arrayType" wsdl:arrayType="tns:Person[]" form="unqualified"/>', $xml);
	Assert::contains('<xsd:enumeration value="red"/>', $xml);
	Assert::contains('<xsd:element name="count" type="xsd:int"/>', $xml);
	Assert::contains('<xsd:element name="getPerson" type="tns:_getPerson_ContainedType"/>', $xml);

	// The serialized schema can be parsed again
	$reparsed = new nusoap_xmlschema('', '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);
	$reparsed->parseString($xml, 'schema');
	Assert::false($reparsed->getError());
	Assert::same(array_keys(parseSchema()->complexTypes), array_keys($reparsed->complexTypes));
});

// Test serializeSchema keeps complexContent extensions
Toolkit::test(static function (): void {
	$xml = parseSchema()->serializeSchema();

	Assert::contains(
		" <xsd:complexType name=\"Employee\">\n"
		. "  <xsd:complexContent>\n"
		. "   <xsd:extension base=\"tns:Person\">\n"
		. "  <xsd:sequence>\n"
		. "   <xsd:element name=\"salary\" type=\"xsd:double\" form=\"qualified\"/>\n"
		. "  </xsd:sequence>\n"
		. "   </xsd:extension>\n"
		. "  </xsd:complexContent>\n"
		. " </xsd:complexType>\n",
		$xml
	);

	$reparsed = new nusoap_xmlschema('', '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);
	$reparsed->parseString($xml, 'schema');
	Assert::same('urn:t:Person', $reparsed->complexTypes['Employee']['extensionBase']);
});

// Test serializeSchema keeps simpleContent extensions
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema('', '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);
	$schema->parseString('<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" targetNamespace="urn:t">'
		. '<xsd:complexType name="Price"><xsd:simpleContent><xsd:extension base="xsd:decimal">'
		. '<xsd:attribute name="currency" type="xsd:string"/>'
		. '</xsd:extension></xsd:simpleContent></xsd:complexType>'
		. '</xsd:schema>', 'schema');

	$xml = $schema->serializeSchema();
	Assert::contains('<xsd:simpleContent>', $xml);
	Assert::contains('<xsd:extension base="xsd:decimal">', $xml);
	Assert::contains('<xsd:attribute name="currency" type="xsd:string"', $xml);
});

// Test attributes of a complexType are not declared as global attributes
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema('', '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);
	$schema->parseString('<xsd:schema xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:tns="urn:t" targetNamespace="urn:t">'
		. '<xsd:attribute name="lang" type="xsd:string"/>'
		. '<xsd:complexType name="Text"><xsd:sequence><xsd:element name="v" type="xsd:string"/></xsd:sequence>'
		. '<xsd:attribute name="id" type="xsd:string"/><xsd:attribute ref="tns:lang"/></xsd:complexType>'
		. '</xsd:schema>', 'schema');

	Assert::same(['lang'], array_keys($schema->attributes));
	Assert::same(['id', 'urn:t:lang'], array_keys($schema->complexTypes['Text']['attrs']));

	$xml = $schema->serializeSchema();
	Assert::same(1, substr_count($xml, 'name="id"'));
	Assert::contains('<xsd:attribute ref="tns:lang"', $xml);
	Assert::contains(" <xsd:attribute name=\"lang\" type=\"xsd:string\"\n/>", $xml);
});

// Test addComplexType, addSimpleType and addElement register types
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema();
	$schema->schemaTargetNamespace = 'urn:t';

	$schema->addComplexType('Point', 'complexType', 'struct', 'all', '', ['x' => ['name' => 'x', 'type' => 'xsd:int']]);
	$schema->addSimpleType('Size', 'http://www.w3.org/2001/XMLSchema:string', 'simpleType', 'scalar', ['S', 'M']);
	$schema->addElement(['name' => 'point', 'type' => 'Point']);

	Assert::same('struct', $schema->getPHPType('Point', 'urn:t'));
	Assert::same(['S', 'M'], $schema->getTypeDef('Size')['enumeration']);
	Assert::same('urn:t:Point', $schema->elements['point']['type']);
	Assert::same('element', $schema->elements['point']['typeClass']);

	$xml = $schema->serializeSchema();
	Assert::contains('<xsd:complexType name="Point">', $xml);
	Assert::contains('<xsd:all>', $xml);
	Assert::contains('<xsd:simpleType name="Size">', $xml);
});

// Test typeToForm renders inputs for structs, arrays and scalars
Toolkit::test(static function (): void {
	$schema = parseSchema();

	$struct = $schema->typeToForm('p', 'Person');
	Assert::contains('name (type: string):', $struct);
	Assert::contains("<input type='text' name='parameters[p][name]'>", $struct);
	Assert::contains("<input type='text' name='parameters[p][age]'>", $struct);

	Assert::same(3, substr_count($schema->typeToForm('a', 'PersonArray'), "<input type='text' name='parameters[a][]'>"));
	Assert::same("<input type='text' name='parameters[x]'>", $schema->typeToForm('x', 'Missing'));
});

// Test CreateTypeName builds a name from the enclosing types
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema();
	Assert::same('item_ContainedType', $schema->CreateTypeName('item'));

	$schema->complexTypeStack = ['Order', 'Line'];
	Assert::same('Order_Line_item_ContainedType', $schema->CreateTypeName('item'));
});

// Test schema parsing reports malformed and empty XML
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema();
	$schema->parseString('<xsd:schema', 'schema');
	Assert::match('XML error parsing XML schema on line 1: %a%', $schema->getError());

	$schema = new nusoap_xmlschema();
	$schema->parseString('', 'schema');
	Assert::same('no xml passed to parseString()!!', $schema->getError());
});

// Test schema reports a file that cannot be read
Toolkit::test(static function (): void {
	$schema = new nusoap_xmlschema(__DIR__ . '/missing.xsd');

	Assert::same('Error reading XML from ' . __DIR__ . '/missing.xsd', $schema->getError());
	Assert::false($schema->parseFile(__DIR__ . '/missing.xsd', 'schema'));
});

// Test schema parses a schema file
Toolkit::test(static function (): void {
	$file = Environment::getTestDir() . '/schema.xsd';
	file_put_contents($file, SCHEMA);

	$schema = new nusoap_xmlschema($file, '', ['xsd' => 'http://www.w3.org/2001/XMLSchema']);

	Assert::false($schema->getError());
	Assert::same('urn:t', $schema->schemaTargetNamespace);
	Assert::true(isset($schema->complexTypes['Person']));
});

// Test serializeTypeDef renders a sample of a type
Toolkit::test(static function (): void {
	$schema = parseSchema();

	Assert::same(
		'<Person id="{type = http://www.w3.org/2001/XMLSchema:string}" xmlns="urn:t"><name/><age/></Person>',
		$schema->serializeTypeDef('Person')
	);
	Assert::same('<Color xmlns="urn:t"/>', $schema->serializeTypeDef('Color'));
	Assert::same('<count xmlns="urn:t"></count>', $schema->serializeTypeDef('count^'));
	Assert::false($schema->serializeTypeDef('Missing'));
});

// Test XMLSchema backward compatibility class
Toolkit::test(static function (): void {
	Assert::type(nusoap_xmlschema::class, new XMLSchema());
});
