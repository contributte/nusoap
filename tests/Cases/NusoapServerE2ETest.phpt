<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Helper function to set up HTTP environment for SOAP requests
function setupHttpEnvironment(string $soapAction = ''): void {
	$_SERVER['REQUEST_METHOD'] = 'POST';
	$_SERVER['QUERY_STRING'] = '';
	$_SERVER['CONTENT_TYPE'] = 'text/xml; charset=UTF-8';
	$_SERVER['HTTP_SOAPACTION'] = $soapAction;
}

// Helper function to create a server and process a SOAP request
function createTestServer(): nusoap_server {
	$server = new nusoap_server();
	$server->configureWSDL('TestService', 'urn:TestService', 'http://localhost/test');

	// Register a simple greeting method
	$server->register(
		'sayHello',
		array('name' => 'xsd:string'),
		array('return' => 'xsd:string'),
		'urn:TestService',
		'urn:TestService#sayHello',
		'rpc',
		'encoded',
		'Returns a greeting'
	);

	// Register a math method
	$server->register(
		'addNumbers',
		array('a' => 'xsd:int', 'b' => 'xsd:int'),
		array('return' => 'xsd:int'),
		'urn:TestService',
		'urn:TestService#addNumbers',
		'rpc',
		'encoded',
		'Adds two numbers'
	);

	return $server;
}

// Define the service functions globally so the server can call them
function sayHello($name) {
	return 'Hello, ' . $name . '!';
}

function addNumbers($a, $b) {
	return $a + $b;
}

// Test E2E: sayHello method
Toolkit::test(static function (): void {
	$server = createTestServer();

	// Set up HTTP environment
	setupHttpEnvironment('urn:TestService#sayHello');

	// Create a SOAP request envelope
	$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:sayHello>
            <name xsi:type="xsd:string">World</name>
        </ns1:sayHello>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	// Capture output
	ob_start();
	$server->service($soapRequest);
	$response = ob_get_clean();

	// Verify response
	Assert::contains('SOAP-ENV:Envelope', $response);
	Assert::contains('SOAP-ENV:Body', $response);
	Assert::contains('sayHelloResponse', $response);
	Assert::contains('Hello, World!', $response);
	Assert::notContains('SOAP-ENV:Fault', $response);
});

// Test E2E: addNumbers method
Toolkit::test(static function (): void {
	$server = createTestServer();

	setupHttpEnvironment('urn:TestService#addNumbers');

	$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:addNumbers>
            <a xsi:type="xsd:int">15</a>
            <b xsi:type="xsd:int">27</b>
        </ns1:addNumbers>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server->service($soapRequest);
	$response = ob_get_clean();

	Assert::contains('SOAP-ENV:Envelope', $response);
	Assert::contains('addNumbersResponse', $response);
	Assert::contains('42', $response); // 15 + 27 = 42
	Assert::notContains('SOAP-ENV:Fault', $response);
});

// Test E2E: WSDL request
Toolkit::test(static function (): void {
	$server = createTestServer();

	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['QUERY_STRING'] = 'wsdl';

	ob_start();
	$server->service('');
	$wsdl = ob_get_clean();

	Assert::contains('<?xml', $wsdl);
	Assert::contains('definitions', $wsdl);
	Assert::contains('TestService', $wsdl);
	Assert::contains('sayHello', $wsdl);
	Assert::contains('addNumbers', $wsdl);
	Assert::contains('portType', $wsdl);
	Assert::contains('binding', $wsdl);
	Assert::contains('service', $wsdl);
});

// Test E2E: Invalid method should return fault
Toolkit::test(static function (): void {
	$server = createTestServer();

	setupHttpEnvironment('urn:TestService#nonExistentMethod');

	$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:nonExistentMethod>
            <param>value</param>
        </ns1:nonExistentMethod>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server->service($soapRequest);
	$response = ob_get_clean();

	Assert::contains('SOAP-ENV:Envelope', $response);
	Assert::contains('SOAP-ENV:Fault', $response);
	Assert::contains('faultcode', $response);
	Assert::contains('faultstring', $response);
});

// Test E2E: Complex type handling
Toolkit::test(static function (): void {
	$server = new nusoap_server();
	$server->configureWSDL('PersonService', 'urn:PersonService', 'http://localhost/person');

	// Define Person complex type
	$server->wsdl->addComplexType(
		'Person',
		'complexType',
		'struct',
		'all',
		'',
		array(
			'id' => array('name' => 'id', 'type' => 'xsd:int'),
			'name' => array('name' => 'name', 'type' => 'xsd:string'),
			'email' => array('name' => 'email', 'type' => 'xsd:string')
		)
	);

	$server->register(
		'getPerson',
		array('id' => 'xsd:int'),
		array('return' => 'tns:Person'),
		'urn:PersonService',
		'urn:PersonService#getPerson',
		'rpc',
		'encoded',
		'Get person by ID'
	);

	setupHttpEnvironment('urn:PersonService#getPerson');

	$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ns1="urn:PersonService">
    <SOAP-ENV:Body>
        <ns1:getPerson>
            <id xsi:type="xsd:int">123</id>
        </ns1:getPerson>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server->service($soapRequest);
	$response = ob_get_clean();

	Assert::contains('SOAP-ENV:Envelope', $response);
	Assert::contains('getPersonResponse', $response);
	Assert::contains('John Doe', $response);
	Assert::contains('john@example.com', $response);
	Assert::contains('123', $response);
	Assert::notContains('SOAP-ENV:Fault', $response);
});

// Define getPerson function for the complex type test
function getPerson($id) {
	return array(
		'id' => $id,
		'name' => 'John Doe',
		'email' => 'john@example.com'
	);
}

// Test E2E: Client-Server integration using nusoap_client in local mode
Toolkit::test(static function (): void {
	$server = createTestServer();

	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_SERVER['QUERY_STRING'] = 'wsdl';

	// Get WSDL from server
	ob_start();
	$server->service('');
	$wsdl = ob_get_clean();

	// Parse WSDL and verify it's valid
	$doc = new DOMDocument();
	$loaded = @$doc->loadXML($wsdl);

	Assert::true($loaded, 'WSDL should be valid XML');

	// Check WSDL structure
	$xpath = new DOMXPath($doc);
	$xpath->registerNamespace('wsdl', 'http://schemas.xmlsoap.org/wsdl/');

	$definitions = $xpath->query('/wsdl:definitions');
	Assert::same(1, $definitions->length, 'Should have one definitions element');

	$portTypes = $xpath->query('//wsdl:portType');
	Assert::true($portTypes->length > 0, 'Should have portType elements');

	$operations = $xpath->query('//wsdl:operation');
	Assert::true($operations->length >= 2, 'Should have at least 2 operations (sayHello, addNumbers)');
});

// Test E2E: Multiple sequential requests
Toolkit::test(static function (): void {
	// First request
	$server1 = createTestServer();
	setupHttpEnvironment('urn:TestService#addNumbers');

	$soapRequest1 = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:addNumbers>
            <a xsi:type="xsd:int">10</a>
            <b xsi:type="xsd:int">20</b>
        </ns1:addNumbers>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server1->service($soapRequest1);
	$response1 = ob_get_clean();
	Assert::contains('30', $response1);

	// Second request with different values
	$server2 = createTestServer();
	setupHttpEnvironment('urn:TestService#addNumbers');

	$soapRequest2 = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:addNumbers>
            <a xsi:type="xsd:int">100</a>
            <b xsi:type="xsd:int">200</b>
        </ns1:addNumbers>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server2->service($soapRequest2);
	$response2 = ob_get_clean();
	Assert::contains('300', $response2);
});

// Test E2E: Empty/null parameter handling
Toolkit::test(static function (): void {
	$server = createTestServer();

	setupHttpEnvironment('urn:TestService#sayHello');

	$soapRequest = '<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope
    xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"
    xmlns:xsd="http://www.w3.org/2001/XMLSchema"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xmlns:ns1="urn:TestService">
    <SOAP-ENV:Body>
        <ns1:sayHello>
            <name xsi:type="xsd:string"></name>
        </ns1:sayHello>
    </SOAP-ENV:Body>
</SOAP-ENV:Envelope>';

	ob_start();
	$server->service($soapRequest);
	$response = ob_get_clean();

	Assert::contains('sayHelloResponse', $response);
	Assert::contains('Hello, !', $response);
	Assert::notContains('SOAP-ENV:Fault', $response);
});
