<?php declare(strict_types = 1);

namespace Tests\Toolkit;

/**
 * Namespaced class called by nusoap_server as "Tests.Toolkit.ServiceCalculator.method".
 */
final class ServiceCalculator
{

	public static function triple(int $v): int
	{
		return $v * 3;
	}

	public function double(int $v): int
	{
		return $v * 2;
	}

}
