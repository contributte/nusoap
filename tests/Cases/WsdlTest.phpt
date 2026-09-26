<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test wsdl instantiation
Toolkit::test(static function (): void {
	$wsdl = new wsdl();

	Assert::type('wsdl', $wsdl);
});

// Test schemaTargetNamespace property exists and is initialized (fix for issue #135)
// This prevents PHP 8.2+ deprecation warning: "Creation of dynamic property wsdl::$schemaTargetNamespace is deprecated"
Toolkit::test(static function (): void {
	$wsdl = new wsdl();

	// Property should exist and be initialized to empty string
	Assert::true(property_exists($wsdl, 'schemaTargetNamespace'));
	Assert::same('', $wsdl->schemaTargetNamespace);

	// Should be able to set the property without triggering deprecation warning
	$wsdl->schemaTargetNamespace = 'http://example.com/schema';
	Assert::same('http://example.com/schema', $wsdl->schemaTargetNamespace);
});

// Test wsdl default properties
Toolkit::test(static function (): void {
	$wsdl = new wsdl();

	Assert::same([], $wsdl->schemas);
	Assert::same([], $wsdl->messages);
	Assert::same([], $wsdl->portTypes);
	Assert::same([], $wsdl->bindings);
	Assert::same([], $wsdl->ports);
	Assert::same('', $wsdl->status);
	Assert::same('', $wsdl->endpoint);
});

// Test wsdl inherits from nusoap_base
Toolkit::test(static function (): void {
	$wsdl = new wsdl();

	Assert::type('nusoap_base', $wsdl);
	Assert::same('NuSOAP', $wsdl->title);
});

// Test parsing WSDL with a one-way operation (input only) does not emit warnings (issue #92)
Toolkit::test(static function (): void {
	$wsdl = new wsdl(__DIR__ . '/../fixtures/one-way.wsdl');

	Assert::false($wsdl->getError());

	$notify = $wsdl->getOperationData('notify');
	Assert::same('notifyRequest', $notify['input']['message']);
	Assert::false(isset($notify['output']));

	$ping = $wsdl->getOperationData('ping');
	Assert::same('pingRequest', $ping['input']['message']);
	Assert::same('pingResponse', $ping['output']['message']);
});
