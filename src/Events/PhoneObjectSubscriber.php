<?php declare(strict_types = 1);

/**
 * PhoneObjectSubscriber.php
 *
 * @copyright      More in LICENSE.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           25.12.15
 */

namespace IPub\DoctrinePhone\Events;

use Doctrine\Common;
use Doctrine\ORM;
use IPub\DoctrinePhone\Types;
use IPub\Phone;
use Nette;
use ReflectionClass;
use ReflectionException;

/**
 * Doctrine phone hydration listener
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
final class PhoneObjectSubscriber implements Common\EventSubscriber
{

	use Nette\SmartObject;

	/** @var mixed[] */
	private array $phoneFieldsCache = [];

	/**
	 * Register events
	 *
	 * @return string[]
	 */
	public function getSubscribedEvents(): array
	{
		return [
			ORM\Events::loadClassMetadata,
		];
	}

	/**
	 * @param ORM\Event\LoadClassMetadataEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 */
	public function loadClassMetadata(
		ORM\Event\LoadClassMetadataEventArgs $eventArgs
	): void {
		/** @phpstan-var ORM\Mapping\ClassMetadata<object> $classMetadata */
		$classMetadata = $eventArgs->getClassMetadata();

		if ($classMetadata->isMappedSuperclass || !$classMetadata->getReflectionClass()->isInstantiable()) {
			return;
		}

		if ($this->buildPhoneFields($classMetadata) === []) {
			return;
		}

		// Register post load event
		$this->registerEvent($classMetadata, ORM\Events::postLoad);
		// Register pre flush event
		$this->registerEvent($classMetadata, ORM\Events::preFlush);
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $eventName
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 *
	 * @phpstan-param ORM\Mapping\ClassMetadata<object> $classMetadata
	 */
	private function registerEvent(ORM\Mapping\ClassMetadata $classMetadata, string $eventName): void
	{
		// phpcs:ignore SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall
		if (!self::hasRegisteredListener($classMetadata, $eventName, get_called_class())) {
			// phpcs:ignore SlevomatCodingStandard.Classes.ModernClassNameReference.ClassNameReferencedViaFunctionCall
			$classMetadata->addEntityListener($eventName, get_called_class(), $eventName);
		}
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 * @param string $eventName
	 * @param string $listenerClass
	 *
	 * @return bool
	 *
	 * @phpstan-param ORM\Mapping\ClassMetadata<object> $classMetadata
	 */
	private static function hasRegisteredListener(
		ORM\Mapping\ClassMetadata $classMetadata,
		string $eventName,
		string $listenerClass
	): bool {
		if (!isset($classMetadata->entityListeners[$eventName])) {
			return false;
		}

		foreach ($classMetadata->entityListeners[$eventName] as $listener) {
			if ($listener['class'] === $listenerClass && $listener['method'] === $eventName) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param object $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 */
	public function postLoad(
		object $entity,
		ORM\Event\LifecycleEventArgs $eventArgs
	): void {
		$em = $eventArgs->getObjectManager();

		if (!$em instanceof ORM\EntityManagerInterface) {
			return;
		}

		$this->postLoadAndPreFlush($entity, $em);
	}

	/**
	 * @param object $entity
	 * @param ORM\Event\PreFlushEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 */
	public function preFlush(
		object $entity,
		ORM\Event\PreFlushEventArgs $eventArgs
	): void {
		$this->postLoadAndPreFlush($entity, $eventArgs->getEntityManager());
	}

	/**
	 * @param object $entity
	 * @param ORM\EntityManagerInterface $objectManager
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 */
	private function postLoadAndPreFlush(
		object $entity,
		ORM\EntityManagerInterface $objectManager
	): void {
		$fieldsMap = $this->getEntityPhoneFields($entity, $objectManager);

		if ($fieldsMap === []) {
			return;
		}

		foreach ($fieldsMap as $phoneMeta) {
			foreach ($phoneMeta['fields'] as $phoneField) {
				$number = $phoneMeta['class']->getFieldValue($entity, $phoneField);

				if ($number instanceof Phone\Entities\Phone || $number === null) {
					continue;
				}

				$phoneMeta['class']->setFieldValue($entity, $phoneField, Phone\Entities\Phone::fromNumber($number));
			}
		}
	}

	/**
	 * @param object $entity
	 * @param ORM\EntityManagerInterface $objectManager
	 *
	 * @return mixed[]
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 */
	private function getEntityPhoneFields(
		object $entity,
		ORM\EntityManagerInterface $objectManager
	): array {
		/** @phpstan-var ORM\Mapping\ClassMetadata<object> $classMetadata */
		$classMetadata = $objectManager->getClassMetadata(get_class($entity));

		if (isset($this->phoneFieldsCache[$classMetadata->getName()])) {
			return $this->phoneFieldsCache[$classMetadata->getName()];
		}

		$phoneFields = $this->buildPhoneFields($classMetadata);

		$fieldsMap = [];

		if ($phoneFields !== []) {
			foreach ($phoneFields as $phoneField => $mapping) {
				if (!isset($fieldsMap[$mapping['phoneFieldClass']])) {
					$fieldsMap[$mapping['phoneFieldClass']] = [
						'class'  => $objectManager->getClassMetadata($mapping['phoneFieldClass']),
						'fields' => [$phoneField],
					];

					continue;
				}

				$fieldsMap[$mapping['phoneFieldClass']]['fields'][] = $phoneField;
			}
		}

		return $this->phoneFieldsCache[$classMetadata->getName()] = $fieldsMap;
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $classMetadata
	 *
	 * @return Array<string, Array<string, string>>
	 *
	 * @throws ORM\Mapping\MappingException
	 * @throws ReflectionException
	 *
	 * @phpstan-param ORM\Mapping\ClassMetadata<object> $classMetadata
	 */
	private function buildPhoneFields(
		ORM\Mapping\ClassMetadata $classMetadata
	): array {
		$phoneFields = [];

		foreach ($classMetadata->getFieldNames() as $fieldName) {
			$mapping = $classMetadata->getFieldMapping($fieldName);

			if ($mapping['type'] !== Types\Phone::PHONE) {
				continue;
			}

			/** @phpstan-ignore-next-line */
			$rc = $classMetadata->isInheritedField($fieldName) ? new ReflectionClass($mapping['declared']) : $classMetadata->getReflectionClass();

			$phoneFields[$fieldName] = [
				'phoneFieldClass' => $rc->getName(),
			];
		}

		return $phoneFields;
	}

}
