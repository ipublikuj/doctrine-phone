<?php
/**
 * DoctrinePhoneExtension.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           25.12.15
 */

namespace IPub\DoctrinePhone\DI;

use Nette;
use Nette\DI;
use Nette\PhpGenerator as Code;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;

use Kdyby;
use Kdyby\Doctrine;
use Kdyby\DoctrineCache;
use Kdyby\Events;

/**
 * Doctrine phone extension container
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class DoctrinePhoneExtension extends DI\CompilerExtension implements Doctrine\DI\IDatabaseTypeProvider
{
	/**
	 * @var array
	 */
	public $defaults = [
		'cache' => 'default',
	];

	public function loadConfiguration()
	{
		$config = $this->getConfig($this->defaults);
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('phoneHydrationListener'))
			->setClass('IPub\DoctrinePhone\Events\PhoneObjectHydrationListener', array(
				DoctrineCache\DI\Helpers::processCache($this, $config['cache'], 'phone'),
			))
			->addTag(Events\DI\EventsExtension::TAG_SUBSCRIBER);
	}

	/**
	 * Returns array of typeName => typeClass
	 *
	 * @return array
	 */
	public function getDatabaseTypes()
	{
		return [
			Types\Phone::PHONE => 'IPub\DoctrinePhone\Types\Phone',
		];
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 */
	public static function register(Nette\Configurator $config, $extensionName = 'doctrinePhone')
	{
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new DoctrinePhoneExtension);
		};
	}
}
