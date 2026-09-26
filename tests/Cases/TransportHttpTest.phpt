<?php declare(strict_types = 1);

use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

// Test socket connection goes to the proxy port, not the target port (issue #54)
Toolkit::test(static function (): void {
	$proxy = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
	Assert::type('resource', $proxy);
	$proxyPort = (int) substr(strrchr(stream_socket_get_name($proxy, false), ':'), 1);

	// Target port 1 is not listening, so connecting there would fail
	$http = new soap_transport_http('http://127.0.0.1:1/service');
	$http->setProxy('127.0.0.1', $proxyPort);

	Assert::true($http->connect(2));

	fclose($http->fp);
	fclose($proxy);
});

// Test certificate auth uses CURLOPT_SSL_VERIFYHOST=2, as value 1 is no longer supported by cURL (issue #53)
Toolkit::test(static function (): void {
	$recordingTransport = static function (array $certRequest): array {
		$http = new class ('https://127.0.0.1/service') extends soap_transport_http {

			/** @var array<int, mixed> */
			public array $recordedOptions = [];

			public function setCurlOption(mixed $option, mixed $value): void
			{
				$this->recordedOptions[$option] = $value;
			}

		};
		$http->setCredentials('', '', 'certificate', [], $certRequest);
		$http->connect();

		return $http->recordedOptions;
	};

	Assert::same(2, $recordingTransport([])[CURLOPT_SSL_VERIFYHOST]);
	Assert::same(2, $recordingTransport(['verifyhost' => 1])[CURLOPT_SSL_VERIFYHOST]);
	Assert::same(0, $recordingTransport(['verifyhost' => 0])[CURLOPT_SSL_VERIFYHOST]);
});
