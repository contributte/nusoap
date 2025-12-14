<?php
/*
 *  NuSOAP Server with Complex Types Example
 *
 *  This example demonstrates a SOAP server with custom complex types
 *  including structures and arrays.
 *
 *  Service: WSDL with complex types
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

// Configure WSDL with service name, namespace, and schema namespace
$server->configureWSDL('PersonService', 'urn:PersonService');

// Define a complex type for Person
$server->wsdl->addComplexType(
    'Person',                                    // name
    'complexType',                               // type class
    'struct',                                    // php type
    'all',                                       // compositor
    '',                                          // restriction base
    array(                                       // elements
        'id' => array('name' => 'id', 'type' => 'xsd:int'),
        'firstName' => array('name' => 'firstName', 'type' => 'xsd:string'),
        'lastName' => array('name' => 'lastName', 'type' => 'xsd:string'),
        'email' => array('name' => 'email', 'type' => 'xsd:string'),
        'age' => array('name' => 'age', 'type' => 'xsd:int')
    )
);

// Define an array of Person type
$server->wsdl->addComplexType(
    'PersonArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:Person[]')
    ),
    'tns:Person'
);

// Register a function that returns a Person
$server->register(
    'getPerson',
    array('id' => 'xsd:int'),
    array('return' => 'tns:Person'),
    'urn:PersonService',
    'urn:PersonService#getPerson',
    'rpc',
    'encoded',
    'Returns a person by their ID'
);

// Register a function that creates a Person and returns the ID
$server->register(
    'createPerson',
    array('person' => 'tns:Person'),
    array('return' => 'xsd:int'),
    'urn:PersonService',
    'urn:PersonService#createPerson',
    'rpc',
    'encoded',
    'Creates a new person and returns the assigned ID'
);

// Register a function that returns all persons
$server->register(
    'getAllPersons',
    array(),
    array('return' => 'tns:PersonArray'),
    'urn:PersonService',
    'urn:PersonService#getAllPersons',
    'rpc',
    'encoded',
    'Returns all persons in the system'
);

// Register a function that searches persons by name
$server->register(
    'searchPersons',
    array('searchTerm' => 'xsd:string'),
    array('return' => 'tns:PersonArray'),
    'urn:PersonService',
    'urn:PersonService#searchPersons',
    'rpc',
    'encoded',
    'Searches for persons by name (first or last name)'
);

// In-memory storage for demo purposes
$persons = array(
    1 => array('id' => 1, 'firstName' => 'John', 'lastName' => 'Doe', 'email' => 'john@example.com', 'age' => 30),
    2 => array('id' => 2, 'firstName' => 'Jane', 'lastName' => 'Smith', 'email' => 'jane@example.com', 'age' => 25),
    3 => array('id' => 3, 'firstName' => 'Bob', 'lastName' => 'Johnson', 'email' => 'bob@example.com', 'age' => 35),
);

/**
 * Returns a person by ID
 *
 * @param int $id Person ID
 * @return array Person data
 */
function getPerson($id) {
    global $persons, $server;

    if (isset($persons[$id])) {
        return $persons[$id];
    }

    // Return a SOAP fault if person not found
    $server->fault('SOAP-ENV:Client', 'Person not found', '', 'No person exists with ID: ' . $id);
    return null;
}

/**
 * Creates a new person
 *
 * @param array $person Person data
 * @return int New person ID
 */
function createPerson($person) {
    global $persons;

    // Generate new ID
    $newId = max(array_keys($persons)) + 1;
    $person['id'] = $newId;
    $persons[$newId] = $person;

    return $newId;
}

/**
 * Returns all persons
 *
 * @return array Array of persons
 */
function getAllPersons() {
    global $persons;
    return array_values($persons);
}

/**
 * Searches persons by name
 *
 * @param string $searchTerm Search term
 * @return array Matching persons
 */
function searchPersons($searchTerm) {
    global $persons;

    $results = array();
    $searchLower = strtolower($searchTerm);

    foreach ($persons as $person) {
        if (stripos($person['firstName'], $searchLower) !== false ||
            stripos($person['lastName'], $searchLower) !== false) {
            $results[] = $person;
        }
    }

    return $results;
}

// Handle the SOAP request
$HTTP_RAW_POST_DATA = file_get_contents('php://input');
$server->service($HTTP_RAW_POST_DATA);
?>
