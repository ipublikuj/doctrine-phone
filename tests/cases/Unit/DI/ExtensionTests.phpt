<?php declare(strict_types = 1);

namespace Tests\Cases;

use Doctrine\DBAL;
use IPub\DoctrinePhone\Events;
use IPub\DoctrinePhone\Types;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

/**
 * @testCase
 */
final class ExtensionTests extends BaseTestCase
{

	public function testFunctional(): void
	{
		$dic = $this->createContainer();

		Assert::true(DBAL\Types\Type::getType('phone') instanceof Types\Phone);
		Assert::true($dic->getService('doctrinePhone.subscriber') instanceof Events\PhoneObjectSubscriber);
	}

}

$test_case = new ExtensionTests();
$test_case->run();
