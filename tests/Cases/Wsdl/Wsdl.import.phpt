<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../../bootstrap.php';

// Test WSDL file imports a schema relative to its own path
Toolkit::test(static function (): void {
	$wsdl = new wsdl(__DIR__ . '/../../fixtures/import/service.wsdl');

	Assert::false($wsdl->getError());
	Assert::same(['urn:svc', 'urn:types'], array_keys($wsdl->schemas));
	Assert::same('struct', $wsdl->getTypeDef('Person', 'urn:types')['phpType']);
	Assert::same(['get'], array_keys($wsdl->getOperations()));
});

// Test import locations are resolved against the URL of the WSDL
Toolkit::test(static function (): void {
	$wsdl = new wsdl();
	$wsdl->wsdl = 'http://example.com:8080/app/service.php?wsdl';

	Assert::same('http://example.com:8080/app/types.xsd', $wsdl->resolveImportUrl('types.xsd'));
	Assert::same('http://example.com:8080/schemas/types.xsd', $wsdl->resolveImportUrl('/schemas/types.xsd'));
	Assert::same('https://other.example.com/types.xsd', $wsdl->resolveImportUrl('https://other.example.com/types.xsd'));
});

// Test import locations keep their query
Toolkit::test(static function (): void {
	$wsdl = new wsdl();
	$wsdl->wsdl = 'http://example.com/Service.svc?wsdl';

	Assert::same('http://example.com/Service.svc?xsd=xsd0', $wsdl->resolveImportUrl('Service.svc?xsd=xsd0'));
	Assert::same('http://example.com/xsd/types.php?v=2', $wsdl->resolveImportUrl('/xsd/types.php?v=2'));
});

// Test referenced attributes are serialized with a prefix
Toolkit::test(static function (): void {
	$wsdl = new wsdl(__DIR__ . '/../../fixtures/import/attributes.wsdl');
	Assert::false($wsdl->getError());

	Assert::same(
		'<t tns:lang="cs" xml:lang="en" xsi:type="tns:Text"><v xsi:type="xsd:string">x</v></t>',
		$wsdl->serializeType('t', 'urn:svc:Text', ['v' => 'x', '!tns:lang' => 'cs', '!xml:lang' => 'en'])
	);

	// The namespace qualified name is still accepted
	Assert::contains(' tns:lang="cs"', $wsdl->serializeType('t', 'urn:svc:Text', ['v' => 'x', '!urn:svc:lang' => 'cs']));
});
