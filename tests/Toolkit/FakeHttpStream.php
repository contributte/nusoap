<?php declare(strict_types = 1);

namespace Tests\Toolkit;

use nusoap_server;
use soap_transport_http;

// Stream wrapper method names and signatures are given by PHP, nusoap_server reads $_SERVER
// phpcs:disable PSR1.Methods.CamelCapsMethodName, Generic.NamingConventions.CamelCapsFunctionName, SlevomatCodingStandard.PHP.DisallowReference, SlevomatCodingStandard.Variables.DisallowSuperGlobalVariable

/**
 * Stream wrapper faking a remote HTTP server for the socket transport, without binding a port.
 *
 * Everything nusoap writes to the stream is collected as a raw HTTP request. On the first read
 * the request is passed to the handler, whose raw HTTP response is then read back by nusoap.
 * A response with a known length keeps the connection alive, writing after it was read starts
 * a new request. Without a length the response is read until the connection closes.
 * The cURL transport does not use PHP streams and cannot be faked this way, see HttpServer.
 */
final class FakeHttpStream
{

	public const PROTOCOL = 'fakehttp';

	/** @var resource|null */
	public $context;

	/** @var callable(string): string */
	private $handler;

	private string $request = '';

	private ?string $response = null;

	private int $position = 0;

	/**
	 * Creates a transport whose connection is served by the handler.
	 *
	 * @param callable(string): string $handler Receives raw HTTP request, returns raw HTTP response
	 */
	public static function createTransport(string $url, callable $handler): soap_transport_http
	{
		if (!in_array(self::PROTOCOL, stream_get_wrappers(), true)) {
			stream_wrapper_register(self::PROTOCOL, self::class);
		}

		$context = stream_context_create([self::PROTOCOL => ['handler' => $handler]]);

		$http = new soap_transport_http($url);
		// connect() reuses an open persistent connection instead of calling fsockopen()
		$http->usePersistentConnection();
		$http->fp = fopen(self::PROTOCOL . '://' . $url, 'r+', false, $context);

		return $http;
	}

	/**
	 * Creates a handler running the nusoap_server from the factory in-process.
	 *
	 * @param callable(): nusoap_server $serverFactory
	 * @return callable(string): string
	 */
	public static function serve(callable $serverFactory): callable
	{
		return static function (string $request) use ($serverFactory): string {
			[$head, $body] = explode("\r\n\r\n", $request, 2);
			$lines = explode("\r\n", $head);
			[$method, $target] = explode(' ', (string) array_shift($lines));

			$server = [
				'REQUEST_METHOD' => $method,
				'QUERY_STRING' => (string) parse_url($target, PHP_URL_QUERY),
			];
			foreach ($lines as $line) {
				[$name, $value] = explode(':', $line, 2);
				$key = strtoupper(str_replace('-', '_', trim($name)));
				$server[in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true) ? $key : 'HTTP_' . $key] = trim($value);
			}

			$originalServer = $_SERVER;
			$_SERVER = $server + $_SERVER;
			try {
				$soapServer = $serverFactory();
				ob_start();
				$soapServer->service($body);
				$payload = (string) ob_get_clean();
			} finally {
				$_SERVER = $originalServer;
			}

			return "HTTP/1.1 200 OK\r\n" . implode("\r\n", $soapServer->outgoing_headers) . "\r\n\r\n" . $payload;
		};
	}

	public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
	{
		$options = stream_context_get_options($this->context);
		$this->handler = $options[self::PROTOCOL]['handler'];

		return true;
	}

	public function stream_write(string $data): int
	{
		if ($this->isResponseRead()) {
			$this->request = '';
			$this->response = null;
			$this->position = 0;
		}

		$this->request .= $data;

		return strlen($data);
	}

	public function stream_read(int $count): string
	{
		$this->response ??= ($this->handler)($this->request);
		$chunk = substr($this->response, $this->position, $count);
		$this->position += strlen($chunk);

		return $chunk;
	}

	public function stream_eof(): bool
	{
		return $this->isResponseRead() && preg_match('~^(Content-Length|Transfer-Encoding):~mi', explode("\r\n\r\n", (string) $this->response, 2)[0]) !== 1;
	}

	public function stream_set_option(int $option, int $arg1, ?int $arg2): bool
	{
		return false;
	}

	private function isResponseRead(): bool
	{
		return $this->response !== null && $this->position >= strlen($this->response);
	}

}
