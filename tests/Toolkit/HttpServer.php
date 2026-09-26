<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use RuntimeException;

/**
 * Runs PHP built-in web server with the given router script on a random local port.
 *
 * Unlike stream wrappers (e.g. Tester\FileMock), a real server is reachable by both
 * cURL and fsockopen(), so it covers both nusoap transports.
 */
final class HttpServer
{

	/** @var resource */
	private $process;

	private int $port;

	public function __construct(string $router, float $timeout = 5.0)
	{
		$this->port = self::findFreePort();

		$process = proc_open(
			[PHP_BINARY, '-d', 'display_errors=stderr', '-S', '127.0.0.1:' . $this->port, $router],
			[['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']],
			$pipes
		);

		if ($process === false) {
			throw new RuntimeException('Unable to start PHP built-in web server');
		}

		$this->process = $process;
		$this->waitUntilReady($timeout);
	}

	public function __destruct()
	{
		proc_terminate($this->process);
		proc_close($this->process);
	}

	public function getUrl(string $path = '/'): string
	{
		return 'http://127.0.0.1:' . $this->port . $path;
	}

	private static function findFreePort(): int
	{
		$socket = stream_socket_server('tcp://127.0.0.1:0');
		if ($socket === false) {
			throw new RuntimeException('Unable to find a free port');
		}

		$name = (string) stream_socket_get_name($socket, false);
		fclose($socket);

		return (int) substr((string) strrchr($name, ':'), 1);
	}

	private function waitUntilReady(float $timeout): void
	{
		$deadline = microtime(true) + $timeout;
		while (microtime(true) < $deadline) {
			$fp = @fsockopen('127.0.0.1', $this->port, $errno, $errstr, 0.1);
			if ($fp !== false) {
				fclose($fp);

				return;
			}

			usleep(20000);
		}

		throw new RuntimeException('PHP built-in web server did not start on port ' . $this->port);
	}

}
