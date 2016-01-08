<?php
/**
 * Test: IPub\DoctrinePhone\HydrationListener
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

namespace IPubTests\DoctrinePhone;

use Nette;

use Doctrine\ORM;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;
use IPub\DoctrinePhone\Events;

use IPub\Phone;

require __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/models/address.php';

/**
 * Doctrine phone hydration tests
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class HydrationListenerTest extends Tester\TestCase
{
	/**
	 * @var \Nette\DI\Container
	 */
	private $container;

	/**
	 * @var \Kdyby\Doctrine\EntityManager
	 */
	private $em;

	/**
	 * @var Events\PhoneObjectSubscriber
	 */
	private $listener;

	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->createContainer();
		$this->em = $this->container->getByType('Kdyby\Doctrine\EntityManager');
		$this->listener = $this->container->getByType('IPub\DoctrinePhone\Events\PhoneObjectSubscriber');
	}

	/**
	 * @return array
	 */
	public function dataEntityClasses()
	{
		return [
			[AddressEntity::getClassName()],
			//[SpecificAddressEntity::getClassName()],
		];
	}

	/**
	 * @dataProvider dataEntityClasses
	 */
	public function testFunctional($className)
	{
		$class = $this->em->getClassMetadata($className);

		// assert that listener was binded to entity
		Assert::same([
			ORM\Events::postLoad => [['class' => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber', 'method' => 'postLoad']],
			ORM\Events::preFlush => [['class' => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber', 'method' => ORM\Events::preFlush]],
		], $class->entityListeners);

		$this->generateDbSchema();

		// Test phone hydration
		$this->em->persist(new $className('+420234567890'))->flush()->clear();

		/** @var AddressEntity $address */
		$address = $this->em->find($className, 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());
	}

	public function testRepeatedLoading()
	{
		$this->generateDbSchema();

		// Test phone hydration
		$this->em->persist(new AddressEntity('+420234567890'))->flush()->clear();

		/** @var AddressEntity $order */
		$address = $this->em->find(AddressEntity::getClassName(), 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());

		// Following loading should not fail
		$address2 = $this->em->createQueryBuilder()
			->select('a')
			->from(AddressEntity::getClassName(), 'a')
			->where('a.id = :id')->setParameter('id', 1)
			->getQuery()->getSingleResult();

		Assert::same($address, $address2);
	}

	private function generateDbSchema()
	{
		$schema = new ORM\Tools\SchemaTool($this->em);
		$schema->createSchema($this->em->getMetadataFactory()->getAllMetadata());
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer()
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5('withModel')]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/files/config.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');
		$config->addConfig(__DIR__ . '/files/address.neon', $config::NONE);

		DoctrinePhone\DI\DoctrinePhoneExtension::register($config);

		return $config->createContainer();
	}
}

\run(new HydrationListenerTest());
