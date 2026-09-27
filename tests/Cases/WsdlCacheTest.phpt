<?php declare(strict_types = 1);

use Contributte\Tester\Environment;
use Contributte\Tester\Toolkit;
use Tester\Assert;

require_once __DIR__ . '/../bootstrap.php';

function createCacheDir(): string
{
	$dir = Environment::getTestDir() . '/wsdlcache-' . uniqid();
	mkdir($dir);

	return $dir;
}

function createWsdl(string $url, string $serviceName): wsdl
{
	$wsdl = new wsdl();
	$wsdl->wsdl = $url;
	$wsdl->serviceName = $serviceName;

	return $wsdl;
}

// Test cache defaults
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache();
	Assert::same('.', $cache->cache_dir);
	Assert::same(0, $cache->cache_lifetime);

	$cache = new nusoap_wsdlcache('', 60);
	Assert::same('.', $cache->cache_dir);
	Assert::same(60, $cache->cache_lifetime);
});

// Test cache filename is derived from the WSDL URL
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache('/cache');

	Assert::same('/cache/wsdlcache-' . md5('http://example.com/?wsdl'), $cache->createFilename('http://example.com/?wsdl'));
});

// Test put, get and remove round trip
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache(createCacheDir());
	$url = 'http://example.com/service?wsdl';

	Assert::null($cache->get($url));
	Assert::true($cache->put(createWsdl($url, 'Service')));

	$cached = $cache->get($url);
	Assert::type(wsdl::class, $cached);
	Assert::same('Service', $cached->serviceName);
	Assert::same($url, $cached->wsdl);

	Assert::true($cache->remove($url));
	Assert::false($cache->remove($url));
	Assert::null($cache->get($url));
});

// Test cache keeps entries for different URLs apart
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache(createCacheDir());

	$cache->put(createWsdl('http://example.com/a?wsdl', 'A'));
	$cache->put(createWsdl('http://example.com/b?wsdl', 'B'));

	Assert::same('A', $cache->get('http://example.com/a?wsdl')->serviceName);
	Assert::same('B', $cache->get('http://example.com/b?wsdl')->serviceName);
});

// Test expired entries are removed from the cache
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache(createCacheDir(), 60);
	$url = 'http://example.com/service?wsdl';
	$cache->put(createWsdl($url, 'Service'));

	$filename = $cache->createFilename($url);
	touch($filename, time() - 120);

	Assert::null($cache->get($url));
	Assert::false(file_exists($filename));
	Assert::contains('Expired', $cache->debug_str);
});

// Test entries within the lifetime are returned
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache(createCacheDir(), 60);
	$url = 'http://example.com/service?wsdl';
	$cache->put(createWsdl($url, 'Service'));

	Assert::same('Service', $cache->get($url)->serviceName);
});

// Test a lock held by the same instance prevents access
Toolkit::test(static function (): void {
	$cache = new nusoap_wsdlcache(createCacheDir());
	$url = 'http://example.com/service?wsdl';
	$filename = $cache->createFilename($url);

	Assert::true($cache->obtainMutex($filename, 'w'));
	Assert::false($cache->obtainMutex($filename, 'r'));
	Assert::null($cache->get($url));
	Assert::false($cache->put(createWsdl($url, 'Service')));
	Assert::contains('Unable to obtain mutex', $cache->debug_str);

	Assert::true($cache->releaseMutex($filename));
	Assert::true($cache->put(createWsdl($url, 'Service')));
});

// Test wsdlcache backward compatibility class
Toolkit::test(static function (): void {
	Assert::type(nusoap_wsdlcache::class, new wsdlcache());
});
