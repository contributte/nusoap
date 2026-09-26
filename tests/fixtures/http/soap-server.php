<?php declare(strict_types = 1);

// Router script for PHP built-in web server, see tests/Toolkit/HttpServer.php

require_once __DIR__ . '/../../../src/nusoap.php';

function sayHello(string $name): string
{
	return 'Hello, ' . $name . '!';
}

$server = new nusoap_server();
$server->configureWSDL('TestService', 'urn:TestService');
$server->register(
	'sayHello',
	['name' => 'xsd:string'],
	['return' => 'xsd:string'],
	'urn:TestService',
	'urn:TestService#sayHello'
);
$server->service(file_get_contents('php://input'));
