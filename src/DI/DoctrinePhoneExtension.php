<?php declare(strict_types = 1);

/**
 * DoctrinePhoneExtension.php
 *
 * @copyright      More in LICENSE.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     DI
 * @since          1.0.0
 *
 * @date           25.12.15
 */

namespace IPub\DoctrinePhone\DI;

use Doctrine;
use IPub\DoctrinePhone\Events;
use IPub\DoctrinePhone\Types;
use Nette;
use Nette\DI;
use Nette\PhpGenerator as Code;

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
	 * @param Nette\Configurator $config
	 * @param string $extensionName
	 *
	 * @return void
	 */
	public static function register(
		Nette\Configurator $config,
		string $extensionName = 'doctrinePhone'
	): void {
		$config->onCompile[] = function (Nette\Configurator $config, Nette\DI\Compiler $compiler) use ($extensionName): void {
			$compiler->addExtension($extensionName, new DoctrinePhoneExtension());
		};
	}

	/**
	 * {@inheritdoc}
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
	public function beforeCompile(): void
	{
		parent::beforeCompile();

		// Get container builder
		$builder = $this->getContainerBuilder();

		$emServiceName = $builder->getByType(Doctrine\ORM\EntityManagerInterface::class, true);

		if ($emServiceName !== null) {
			/** @var DI\Definitions\ServiceDefinition $emService */
			$emService = $builder->getDefinition($emServiceName);

			$emService
				->addSetup('?->getEventManager()->addEventSubscriber(?)', [
					'@self',
					$builder->getDefinition($this->prefix('subscriber')),
				]);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function afterCompile(Code\ClassType $class)
	{
		parent::afterCompile($class);

		$initialize = $class->methods['initialize'];
		$initialize->addBody('if (!Doctrine\DBAL\Types\Type::hasType(\'' . Types\Phone::PHONE . '\')) { Doctrine\DBAL\Types\Type::addType(\'' . Types\Phone::PHONE . '\', \'' . Types\Phone::class . '\'); }');
	}

}
