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
