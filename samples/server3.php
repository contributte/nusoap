<?php
/*
 *  NuSOAP Server with Nested Complex Types Example
 *
 *  This example demonstrates a SOAP server with nested complex types,
 *  including an Order that contains OrderItems and Customer information.
 *
 *  Service: WSDL with nested complex types
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

// Configure WSDL
$server->configureWSDL('OrderService', 'urn:OrderService');

// Define Address complex type
$server->wsdl->addComplexType(
    'Address',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'street' => array('name' => 'street', 'type' => 'xsd:string'),
        'city' => array('name' => 'city', 'type' => 'xsd:string'),
        'state' => array('name' => 'state', 'type' => 'xsd:string'),
        'zipCode' => array('name' => 'zipCode', 'type' => 'xsd:string'),
        'country' => array('name' => 'country', 'type' => 'xsd:string')
    )
);

// Define Customer complex type (with nested Address)
$server->wsdl->addComplexType(
    'Customer',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'id' => array('name' => 'id', 'type' => 'xsd:int'),
        'name' => array('name' => 'name', 'type' => 'xsd:string'),
        'email' => array('name' => 'email', 'type' => 'xsd:string'),
        'billingAddress' => array('name' => 'billingAddress', 'type' => 'tns:Address'),
        'shippingAddress' => array('name' => 'shippingAddress', 'type' => 'tns:Address')
    )
);

// Define OrderItem complex type
$server->wsdl->addComplexType(
    'OrderItem',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'productId' => array('name' => 'productId', 'type' => 'xsd:int'),
        'productName' => array('name' => 'productName', 'type' => 'xsd:string'),
        'quantity' => array('name' => 'quantity', 'type' => 'xsd:int'),
        'unitPrice' => array('name' => 'unitPrice', 'type' => 'xsd:float'),
        'totalPrice' => array('name' => 'totalPrice', 'type' => 'xsd:float')
    )
);

// Define OrderItemArray type
$server->wsdl->addComplexType(
    'OrderItemArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:OrderItem[]')
    ),
    'tns:OrderItem'
);

// Define Order complex type (with nested Customer and OrderItemArray)
$server->wsdl->addComplexType(
    'Order',
    'complexType',
    'struct',
    'all',
    '',
    array(
        'orderId' => array('name' => 'orderId', 'type' => 'xsd:int'),
        'orderDate' => array('name' => 'orderDate', 'type' => 'xsd:string'),
        'status' => array('name' => 'status', 'type' => 'xsd:string'),
        'customer' => array('name' => 'customer', 'type' => 'tns:Customer'),
        'items' => array('name' => 'items', 'type' => 'tns:OrderItemArray'),
        'subtotal' => array('name' => 'subtotal', 'type' => 'xsd:float'),
        'tax' => array('name' => 'tax', 'type' => 'xsd:float'),
        'total' => array('name' => 'total', 'type' => 'xsd:float')
    )
);

// Define OrderArray type
$server->wsdl->addComplexType(
    'OrderArray',
    'complexType',
    'array',
    '',
    'SOAP-ENC:Array',
    array(),
    array(
        array('ref' => 'SOAP-ENC:arrayType', 'wsdl:arrayType' => 'tns:Order[]')
    ),
    'tns:Order'
);

// Register operations
$server->register(
    'getOrder',
    array('orderId' => 'xsd:int'),
    array('return' => 'tns:Order'),
    'urn:OrderService',
    'urn:OrderService#getOrder',
    'rpc',
    'encoded',
    'Returns an order by ID'
);

$server->register(
    'getCustomerOrders',
    array('customerId' => 'xsd:int'),
    array('return' => 'tns:OrderArray'),
    'urn:OrderService',
    'urn:OrderService#getCustomerOrders',
    'rpc',
    'encoded',
    'Returns all orders for a customer'
);

$server->register(
    'createOrder',
    array(
        'customerId' => 'xsd:int',
        'items' => 'tns:OrderItemArray'
    ),
    array('return' => 'tns:Order'),
    'urn:OrderService',
    'urn:OrderService#createOrder',
    'rpc',
    'encoded',
    'Creates a new order'
);

$server->register(
    'calculateOrderTotal',
    array('items' => 'tns:OrderItemArray'),
    array(
        'subtotal' => 'xsd:float',
        'tax' => 'xsd:float',
        'total' => 'xsd:float'
    ),
    'urn:OrderService',
    'urn:OrderService#calculateOrderTotal',
    'rpc',
    'encoded',
    'Calculates the total for given order items'
);

// Sample data
$customers = array(
    1 => array(
        'id' => 1,
        'name' => 'Acme Corporation',
        'email' => 'orders@acme.com',
        'billingAddress' => array(
            'street' => '123 Main St',
            'city' => 'New York',
            'state' => 'NY',
            'zipCode' => '10001',
            'country' => 'USA'
        ),
        'shippingAddress' => array(
            'street' => '456 Warehouse Ave',
            'city' => 'Newark',
            'state' => 'NJ',
            'zipCode' => '07102',
            'country' => 'USA'
        )
    )
);

$orders = array(
    1001 => array(
        'orderId' => 1001,
        'orderDate' => '2024-01-15T10:30:00',
        'status' => 'shipped',
        'customer' => $customers[1],
        'items' => array(
            array('productId' => 101, 'productName' => 'Widget A', 'quantity' => 5, 'unitPrice' => 10.00, 'totalPrice' => 50.00),
            array('productId' => 102, 'productName' => 'Widget B', 'quantity' => 3, 'unitPrice' => 25.00, 'totalPrice' => 75.00)
        ),
        'subtotal' => 125.00,
        'tax' => 10.00,
        'total' => 135.00
    )
);

/**
 * Returns an order by ID
 */
function getOrder($orderId) {
    global $orders, $server;

    if (isset($orders[$orderId])) {
        return $orders[$orderId];
    }

    $server->fault('SOAP-ENV:Client', 'Order not found', '', 'No order exists with ID: ' . $orderId);
    return null;
}

/**
 * Returns all orders for a customer
 */
function getCustomerOrders($customerId) {
    global $orders;

    $customerOrders = array();
    foreach ($orders as $order) {
        if ($order['customer']['id'] == $customerId) {
            $customerOrders[] = $order;
        }
    }

    return $customerOrders;
}

/**
 * Creates a new order
 */
function createOrder($customerId, $items) {
    global $orders, $customers, $server;

    if (!isset($customers[$customerId])) {
        $server->fault('SOAP-ENV:Client', 'Customer not found', '', 'No customer exists with ID: ' . $customerId);
        return null;
    }

    // Calculate totals
    $subtotal = 0;
    foreach ($items as &$item) {
        $item['totalPrice'] = $item['quantity'] * $item['unitPrice'];
        $subtotal += $item['totalPrice'];
    }
    $tax = $subtotal * 0.08; // 8% tax
    $total = $subtotal + $tax;

    // Create new order
    $newOrderId = max(array_keys($orders)) + 1;
    $newOrder = array(
        'orderId' => $newOrderId,
        'orderDate' => date('c'),
        'status' => 'pending',
        'customer' => $customers[$customerId],
        'items' => $items,
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
    );

    $orders[$newOrderId] = $newOrder;
    return $newOrder;
}

/**
 * Calculates order total
 */
function calculateOrderTotal($items) {
    $subtotal = 0;
    foreach ($items as $item) {
        $itemTotal = $item['quantity'] * $item['unitPrice'];
        $subtotal += $itemTotal;
    }
    $tax = $subtotal * 0.08;
    $total = $subtotal + $tax;

    return array(
        'subtotal' => $subtotal,
        'tax' => $tax,
        'total' => $total
    );
}

// Handle the SOAP request
$HTTP_RAW_POST_DATA = file_get_contents('php://input');
$server->service($HTTP_RAW_POST_DATA);
?>
