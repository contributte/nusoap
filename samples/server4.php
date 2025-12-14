<?php
/*
 *  NuSOAP Server with Document/Literal Style
 *
 *  This example demonstrates a SOAP server using document/literal style
 *  which is WS-I Basic Profile compliant.
 *
 *  Service: WSDL with document/literal
 *  Payload: document/literal
 *  Transport: http
 *  Authentication: none
 *
 *  Usage: Access this file directly in a browser to see WSDL (add ?wsdl to URL)
 *         or send SOAP requests to this endpoint.
 */
require_once(__DIR__ . '/../src/nusoap.php');

// Create server instance
$server = new nusoap_server();

// Configure WSDL with document/literal style
$server->configureWSDL(
    'CalculatorService',                        // service name
    'urn:CalculatorService',                    // namespace
    false,                                      // endpoint (auto-detect)
    'document'                                  // style: document (instead of rpc)
);

// Define request wrapper type for add operation
$server->wsdl->addComplexType(
    'AddRequest',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'a' => array('name' => 'a', 'type' => 'xsd:float', 'minOccurs' => '1'),
        'b' => array('name' => 'b', 'type' => 'xsd:float', 'minOccurs' => '1')
    )
);

// Define response wrapper type for add operation
$server->wsdl->addComplexType(
    'AddResponse',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'result' => array('name' => 'result', 'type' => 'xsd:float')
    )
);

// Define request wrapper type for divide operation
$server->wsdl->addComplexType(
    'DivideRequest',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'dividend' => array('name' => 'dividend', 'type' => 'xsd:float', 'minOccurs' => '1'),
        'divisor' => array('name' => 'divisor', 'type' => 'xsd:float', 'minOccurs' => '1')
    )
);

// Define response wrapper type for divide operation
$server->wsdl->addComplexType(
    'DivideResponse',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'quotient' => array('name' => 'quotient', 'type' => 'xsd:float'),
        'remainder' => array('name' => 'remainder', 'type' => 'xsd:float')
    )
);

// Define a statistics response type
$server->wsdl->addComplexType(
    'NumberArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'xsd:float[]')
    ),
    'xsd:float'
);

$server->wsdl->addComplexType(
    'StatisticsRequest',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'numbers' => array('name' => 'numbers', 'type' => 'tns:NumberArray')
    )
);

$server->wsdl->addComplexType(
    'StatisticsResponse',
    'complexType',
    'struct',
    'sequence',
    '',
    array(
        'count' => array('name' => 'count', 'type' => 'xsd:int'),
        'sum' => array('name' => 'sum', 'type' => 'xsd:float'),
        'average' => array('name' => 'average', 'type' => 'xsd:float'),
        'min' => array('name' => 'min', 'type' => 'xsd:float'),
        'max' => array('name' => 'max', 'type' => 'xsd:float')
    )
);

// Register add operation (document/literal style)
$server->register(
    'add',
    array('parameters' => 'tns:AddRequest'),
    array('parameters' => 'tns:AddResponse'),
    'urn:CalculatorService',
    'urn:CalculatorService/add',
    'document',
    'literal',
    'Adds two numbers together'
);

// Register divide operation
$server->register(
    'divide',
    array('parameters' => 'tns:DivideRequest'),
    array('parameters' => 'tns:DivideResponse'),
    'urn:CalculatorService',
    'urn:CalculatorService/divide',
    'document',
    'literal',
    'Divides two numbers and returns quotient and remainder'
);

// Register statistics operation
$server->register(
    'calculateStatistics',
    array('parameters' => 'tns:StatisticsRequest'),
    array('parameters' => 'tns:StatisticsResponse'),
    'urn:CalculatorService',
    'urn:CalculatorService/calculateStatistics',
    'document',
    'literal',
    'Calculates statistics for a list of numbers'
);

/**
 * Adds two numbers
 *
 * @param array $params Contains 'a' and 'b' values
 * @return array Result wrapped in response structure
 */
function add($params) {
    $a = isset($params['a']) ? (float)$params['a'] : 0;
    $b = isset($params['b']) ? (float)$params['b'] : 0;

    return array('result' => $a + $b);
}

/**
 * Divides two numbers
 *
 * @param array $params Contains 'dividend' and 'divisor' values
 * @return array Result with quotient and remainder
 */
function divide($params) {
    global $server;

    $dividend = isset($params['dividend']) ? (float)$params['dividend'] : 0;
    $divisor = isset($params['divisor']) ? (float)$params['divisor'] : 0;

    if ($divisor == 0) {
        $server->fault('SOAP-ENV:Client', 'Division by zero', '', 'Cannot divide by zero');
        return null;
    }

    $quotient = floor($dividend / $divisor);
    $remainder = fmod($dividend, $divisor);

    return array(
        'quotient' => $quotient,
        'remainder' => $remainder
    );
}

/**
 * Calculates statistics for a list of numbers
 *
 * @param array $params Contains 'numbers' array
 * @return array Statistics including count, sum, average, min, max
 */
function calculateStatistics($params) {
    global $server;

    $numbers = isset($params['numbers']) ? $params['numbers'] : array();

    if (empty($numbers)) {
        $server->fault('SOAP-ENV:Client', 'Empty input', '', 'At least one number is required');
        return null;
    }

    // Ensure numbers is an array
    if (!is_array($numbers)) {
        $numbers = array($numbers);
    }

    $count = count($numbers);
    $sum = array_sum($numbers);
    $average = $sum / $count;
    $min = min($numbers);
    $max = max($numbers);

    return array(
        'count' => $count,
        'sum' => $sum,
        'average' => $average,
        'min' => $min,
        'max' => $max
    );
}

// Handle the SOAP request
$HTTP_RAW_POST_DATA = file_get_contents('php://input');
$server->service($HTTP_RAW_POST_DATA);
?>
