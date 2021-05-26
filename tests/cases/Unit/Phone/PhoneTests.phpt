<?php declare(strict_types = 1);

namespace Tests\Cases;

use Doctrine\ORM;
use IPub\Phone;
use Tester\Assert;

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../BaseTestCase.php';

require_once __DIR__ . '/../../../libs/models/AddressEntity.php';

/**
 * @testCase
 */
class PhoneTests extends BaseTestCase
{

	/** @var string[] */
	protected array $additionalConfigs = [
		__DIR__ . DIRECTORY_SEPARATOR . 'entities.neon',
	];

	public function setUp(): void
	{
		parent::setUp();

		$this->generateDbSchema();
	}

	/**
	 * @return mixed[]
	 */
	public function dataEntityClasses(): array
	{
		return [
			[Models\AddressEntity::class],
		];
	}

	/**
	 * @dataProvider dataEntityClasses
	 */
	public function testFunctional($className)
	{
		// Test phone hydration
		$this->getEntityManager()->persist(new $className(Phone\Entities\Phone::fromNumber('+420234567890')));
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		/** @var Models\AddressEntity $address */
		$address = $this->getEntityManager()->find($className, 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());

		$class = $this->getEntityManager()->getClassMetadata($className);

		// assert that listener was binded to entity
		Assert::same([
			ORM\Events::postLoad => [
				[
					'class'  => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber',
					'method' => ORM\Events::postLoad,
				],
			],
			ORM\Events::preFlush => [
				[
					'class'  => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber',
					'method' => ORM\Events::preFlush,
				],
			],
		], $class->entityListeners);
	}

	/**
	 * @dataProvider dataEntityClasses
	 */
	public function testNullable($className)
	{
		$this->getEntityManager()->getClassMetadata($className);

		// Test phone hydration
		$this->getEntityManager()->persist(new $className(null));
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		/** @var Models\AddressEntity $address */
		$address = $this->getEntityManager()->find($className, 1);

		Assert::null($address->getPhone());
	}

	public function testRepeatedLoading()
	{
		// Test phone hydration
		$this->getEntityManager()->persist(new Models\AddressEntity(Phone\Entities\Phone::fromNumber('+420234567890')));
		$this->getEntityManager()->flush();
		$this->getEntityManager()->clear();

		/** @var Models\AddressEntity $address */
		$address = $this->getEntityManager()->find(Models\AddressEntity::class, 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());

		// Following loading should not fail
		$address2 = $this->getEntityManager()
			->createQueryBuilder()
			->select('a')
			->from(Models\AddressEntity::class, 'a')
			->where('a.id = :id')
			->setParameter('id', 1)
			->getQuery()
			->getSingleResult();

		Assert::same($address, $address2);
	}

}

$test_case = new PhoneTests();
$test_case->run();
