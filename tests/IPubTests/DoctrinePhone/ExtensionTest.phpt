<?php
/**
 * Test: IPub\DoctrinePhone\Extension
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           26.12.15
 */

namespace IPubTests\DoctrinePhone;

use Nette;

use Tester;
use Tester\Assert;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;

use Doctrine\DBAL\Types\Type;

require __DIR__ . '/../bootstrap.php';

/**
 * Registering doctrine phone extension tests
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class ExtensionTest extends Tester\TestCase
{
	public function testRegisterTypes()
	{
		$dic = $this->createContainer();

		/** @var \Kdyby\Doctrine\Connection $connection */
		$connection = $dic->getByType('Kdyby\Doctrine\Connection');
		$connection->connect(); // initializes the types

		Assert::true(Type::getType('phone') instanceof Types\Phone);
	}

	/**
	 * @return Nette\DI\Container
	 */
	protected function createContainer()
	{
		$rootDir = __DIR__ . '/../';

		$config = new Nette\Configurator();
		$config->setTempDirectory(TEMP_DIR);

		$config->addParameters(['container' => ['class' => 'SystemContainer_' . md5(time())]]);
		$config->addParameters(['appDir' => $rootDir, 'wwwDir' => $rootDir]);

		$config->addConfig(__DIR__ . '/files/config.neon', !isset($config->defaultExtensions['nette']) ? 'v23' : 'v22');

		DoctrinePhone\DI\DoctrinePhoneExtension::register($config);

		return $config->createContainer();
	}
}

\run(new ExtensionTest());
