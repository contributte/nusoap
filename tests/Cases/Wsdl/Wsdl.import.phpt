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
