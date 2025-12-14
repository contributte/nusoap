<?php
/*
 *  Basic NuSOAP Server Example
 *
 *  This example demonstrates a simple SOAP server with basic functions.
 *
 *  Service: SOAP endpoint
 *  Payload: rpc/encoded
 *  Transport: http
 *  Authentication: none
 *
 *  Usage: Access this file directly in a browser to see WSDL (add ?wsdl to URL)
 *         or send SOAP requests to this endpoint.
 */
require_once(__DIR__ . '/../src/nusoap.php');

// Create server instance
$server = new nusoap_server();

// Configure WSDL generation
$server->configureWSDL('HelloService', 'urn:HelloService');

// Register a simple function that returns a string
$server->register(
    'sayHello',                                 // method name
    array('name' => 'xsd:string'),              // input parameters
    array('return' => 'xsd:string'),            // output parameters
    'urn:HelloService',                         // namespace
    'urn:HelloService#sayHello',                // soapaction
    'rpc',                                      // style
    'encoded',                                  // use
    'Returns a greeting message for the given name'  // documentation
);

// Register a function that adds two numbers
$server->register(
    'addNumbers',
    array('a' => 'xsd:int', 'b' => 'xsd:int'),
    array('return' => 'xsd:int'),
    'urn:HelloService',
    'urn:HelloService#addNumbers',
    'rpc',
    'encoded',
    'Adds two integers and returns the result'
);

// Register a function that returns current server time
$server->register(
    'getServerTime',
    array(),                                    // no input parameters
    array('return' => 'xsd:string'),
    'urn:HelloService',
    'urn:HelloService#getServerTime',
    'rpc',
    'encoded',
    'Returns the current server timestamp'
);

/**
 * Returns a greeting message for the given name
 *
 * @param string $name The name to greet
 * @return string Greeting message
 */
function sayHello($name) {
    return 'Hello, ' . $name . '!';
}

/**
 * Adds two integers
 *
 * @param int $a First number
 * @param int $b Second number
 * @return int Sum of the two numbers
 */
function addNumbers($a, $b) {
    return $a + $b;
}

/**
 * Returns the current server timestamp
 *
 * @return string Current server time in ISO 8601 format
 */
function getServerTime() {
    return date('c');
}

// Handle the SOAP request
$HTTP_RAW_POST_DATA = file_get_contents('php://input');
$server->service($HTTP_RAW_POST_DATA);
?>
