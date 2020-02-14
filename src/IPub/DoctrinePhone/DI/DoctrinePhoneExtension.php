<?php
/**
 * DoctrinePhoneExtension.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           25.12.15
 */

declare(strict_types = 1);

namespace IPub\DoctrinePhone\DI;

use Doctrine;

use Nette;
use Nette\DI;
use Nette\PhpGenerator as Code;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Events;
use IPub\DoctrinePhone\Types;

/**
 * Doctrine phone extension container
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class DoctrinePhoneExtension extends DI\CompilerExtension
{
	/**
	 * @return void
	 */
	public function loadConfiguration()
	{
		// Get container builder
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('subscriber'))
			->setType(Events\PhoneObjectSubscriber::class);
	}

	/**
	 * {@inheritdoc}
	 */
	public function beforeCompile()
	{
		parent::beforeCompile();

		$builder = $this->getContainerBuilder();

		$builder->getDefinition($builder->getByType(Doctrine\ORM\EntityManagerInterface::class, TRUE))
			->addSetup('?->getEventManager()->addEventSubscriber(?)', ['@self', $builder->getDefinition($this->prefix('subscriber'))]);
	}

	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(Code\ClassType $class)
	{
		parent::afterCompile($class);

		/** @var Code\Method $initialize */
		$initialize = $class->methods['initialize'];
		$initialize->addBody('if (!Doctrine\DBAL\Types\Type::hasType(\'' . Types\Phone::PHONE . '\')) { Doctrine\DBAL\Types\Type::addType(\'' . Types\Phone::PHONE . '\', \'' . Types\Phone::class . '\'); }');
	}

	/**
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'doctrinePhone'
	) : void {
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName) {
			$compiler->addExtension($extensionName, new DoctrinePhoneExtension);
		};
	}
}
