<?php
/**
 * Test: IPub\DoctrinePhone\Extension
 *
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           26.12.15
 */

declare(strict_types = 1);

namespace IPubTests\DoctrinePhone;

use Nette;

use Doctrine\DBAL;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Events;
use IPub\DoctrinePhone\Types;

use Doctrine\DBAL\Types\Type;

require __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'bootstrap.php';

/**
 * Registering doctrine phone extension tests
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class ExtensionTest extends Tester\TestCase
{
	public function testRegisterTypes()
	{
		$dic = $this->createContainer();

		/** @var DBAL\Connection $connection */
		$connection = $dic->getByType(DBAL\Connection::class);
		$connection->connect(); // initializes the types

		Assert::true(Type::getType('phone') instanceof Types\Phone);
		Assert::true($dic->getService('doctrinePhone.subscriber') instanceof Events\PhoneObjectSubscriber);
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer() : Nette\DI\Container
	{
		$rootDir = __DIR__ . '/../../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5('extensionTest' . time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . DS . 'files' . DS . 'config.neon');

		DoctrinePhone\DI\DoctrinePhoneExtension::register($config);

		return $config->createContainer();
	}
}

\run(new ExtensionTest());
