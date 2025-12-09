<?php declare(strict_types = 1);

use Tester\Environment;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

Environment::setup();

// Create a temporary directory for tests
define('TEMP_DIR', __DIR__ . '/tmp/' . getmypid());
@mkdir(TEMP_DIR, 0777, true);

// Include nusoap source files
require_once __DIR__ . '/../src/nusoap.php';
