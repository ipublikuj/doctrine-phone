<?php
/**
 * Test: IPub\DoctrinePhone\HydrationListener
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

declare(strict_types = 1);

namespace IPubTests\DoctrinePhone;

use Nette;

use Doctrine\ORM;

use Nettrine;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;
use IPub\DoctrinePhone\Events;

use IPub\Phone;

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';
require __DIR__ . DS . 'models' . DS . 'address.php';

/**
 * Doctrine phone hydration tests
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class HydrationListenerTest extends Tester\TestCase
{
	/**
	 * @var Nette\DI\Container
	 */
	private $container;

	/**
	 * @var Nettrine\ORM\EntityManagerDecorator
	 */
	private $em;

	/**
	 * @var Events\PhoneObjectSubscriber
	 */
	private $listener;

	/**
	 * {@inheritdoc}
	 */
	protected function setUp()
	{
		parent::setUp();

		$this->container = $this->createContainer();
		$this->em = $this->container->getByType(Nettrine\ORM\EntityManagerDecorator::class);
		$this->listener = $this->container->getByType(Events\PhoneObjectSubscriber::class);
	}

	/**
	 * @return array
	 */
	public function dataEntityClasses() : array
	{
		return [
			[AddressEntity::class],
			[SpecificAddressEntity::class],
		];
	}

	/**
	 * @dataProvider dataEntityClasses
	 */
	public function testFunctional($className)
	{
		$this->generateDbSchema();

		// Test phone hydration
		$this->em->persist(new $className(Phone\Entities\Phone::fromNumber('+420234567890')));
		$this->em->flush();
		$this->em->clear();

		/** @var AddressEntity $address */
		$address = $this->em->find($className, 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());

		$class = $this->em->getClassMetadata($className);

		// assert that listener was binded to entity
		Assert::same([
			ORM\Events::postLoad => [['class' => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber', 'method' => ORM\Events::postLoad]],
			ORM\Events::preFlush => [['class' => 'IPub\\DoctrinePhone\\Events\\PhoneObjectSubscriber', 'method' => ORM\Events::preFlush]],
		], $class->entityListeners);
	}

	/**
	 * @dataProvider dataEntityClasses
	 */
	public function testNullable($className)
	{
		$class = $this->em->getClassMetadata($className);

		$this->generateDbSchema();

		// Test phone hydration
		$this->em->persist(new $className(null));
		$this->em->flush();
		$this->em->clear();

		/** @var AddressEntity $address */
		$address = $this->em->find($className, 1);

		Assert::null($address->getPhone());
	}

	public function testRepeatedLoading()
	{
		$this->generateDbSchema();

		// Test phone hydration
		$this->em->persist(new AddressEntity(Phone\Entities\Phone::fromNumber('+420234567890')));
		$this->em->flush();
		$this->em->clear();

		/** @var AddressEntity $order */
		$address = $this->em->find(AddressEntity::class, 1);

		Assert::equal(Phone\Entities\Phone::fromNumber('+420234567890'), $address->getPhone());

		// Following loading should not fail
		$address2 = $this->em->createQueryBuilder()
			->select('a')
			->from(AddressEntity::class, 'a')
			->where('a.id = :id')->setParameter('id', 1)
			->getQuery()->getSingleResult();

		Assert::same($address, $address2);
	}

	/**
	 * @return void
	 *
	 * @throws ORM\Tools\ToolsException
	 */
	private function generateDbSchema()
	{
		$schema = new ORM\Tools\SchemaTool($this->em);
		$schema->createSchema($this->em->getMetadataFactory()->getAllMetadata());
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer() : Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5('withModel' . time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . DS . 'files' . DS . 'config.neon');
		$config->addConfig(__DIR__ . DS . 'files' . DS . 'address.neon');

		DoctrinePhone\DI\DoctrinePhoneExtension::register($config);

		return $config->createContainer();
	}
}

\run(new HydrationListenerTest());
