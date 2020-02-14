<?php
/**
 * PhoneObjectSubscriber.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           25.12.15
 */

declare(strict_types = 1);

namespace IPub\DoctrinePhone\Events;

use ReflectionClass;
use ReflectionException;

use Nette;
use Nette\Utils;

use Doctrine\Common;
use Doctrine\ORM;
use Doctrine\Persistence;

use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;

use IPub\Phone;

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
	/**
	 * Implement nette smart magic
	 */
	use Nette\SmartObject;

	/**
	 * @var array
	 */
	private $phoneFieldsCache = [];

	/**
	 * Register events
	 *
	 * @return array
	 */
	public function getSubscribedEvents()
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
	) : void {
		$class = $eventArgs->getClassMetadata();

		if (!$class instanceof ORM\Mapping\ClassMetadata || $class->isMappedSuperclass || !$class->getReflectionClass()->isInstantiable()) {
			return;
		}

		if ($this->buildPhoneFields($class) === []) {
			return;
		}

		// Register post load event
		$this->registerEvent($class, ORM\Events::postLoad);
		// Register pre flush event
		$this->registerEvent($class, ORM\Events::preFlush);
	}

	/**
	 * @param $entity
	 * @param ORM\Event\LifecycleEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws Phone\Exceptions\NoValidCountryException
	 * @throws Phone\Exceptions\NoValidPhoneException
	 * @throws ReflectionException
	 */
	public function postLoad(
		$entity,
		ORM\Event\LifecycleEventArgs $eventArgs
	) : void {
		$this->postLoadAndPreFlush($entity, $eventArgs->getObjectManager());
	}

	/**
	 * @param $entity
	 * @param ORM\Event\PreFlushEventArgs $eventArgs
	 *
	 * @return void
	 *
	 * @throws Phone\Exceptions\NoValidCountryException
	 * @throws Phone\Exceptions\NoValidPhoneException
	 * @throws ReflectionException
	 */
	public function preFlush(
		$entity,
		ORM\Event\PreFlushEventArgs $eventArgs
	) : void {
		$this->postLoadAndPreFlush($entity, $eventArgs->getEntityManager());
	}

	/**
	 * @param $entity
	 * @param Common\Persistence\ObjectManager $objectManager
	 *
	 * @return void
	 *
	 * @throws Phone\Exceptions\NoValidCountryException
	 * @throws Phone\Exceptions\NoValidPhoneException
	 * @throws ReflectionException
	 */
	private function postLoadAndPreFlush(
		$entity,
		Common\Persistence\ObjectManager $objectManager
	) : void {
		if (!$fieldsMap = $this->getEntityPhoneFields($entity, $objectManager)) {
			return;
		}

		foreach ($fieldsMap as $phoneAssoc => $phoneMeta) {
			foreach ($phoneMeta['fields'] as $phoneField) {
				$number = $phoneMeta['class']->getFieldValue($entity, $phoneField);

				if ($number instanceof Phone\Entities\Phone || $number === NULL) {
					continue;
				}

				$phoneMeta['class']->setFieldValue($entity, $phoneField, Phone\Entities\Phone::fromNumber($number));
			}
		}
	}

	/**
	 * @param $entity
	 * @param Common\Persistence\ObjectManager $objectManager
	 *
	 * @return array
	 *
	 * @throws ReflectionException
	 */
	private function getEntityPhoneFields(
		$entity,
		Common\Persistence\ObjectManager $objectManager
	) : array {
		$class = $objectManager->getClassMetadata(get_class($entity));

		if (isset($this->phoneFieldsCache[$class->getName()])) {
			return $this->phoneFieldsCache[$class->getName()];
		}

		$phoneFields = $this->buildPhoneFields($class);

		$fieldsMap = [];

		if (is_array($phoneFields) && !empty($phoneFields)) {
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

		return $this->phoneFieldsCache[$class->getName()] = $fieldsMap;
	}

	/**
	 * @param Persistence\Mapping\ClassMetadata $class
	 *
	 * @return array
	 *
	 * @throws ReflectionException
	 */
	private function buildPhoneFields(
		Persistence\Mapping\ClassMetadata $class
	) : array {
		$phoneFields = [];

		foreach ($class->getFieldNames() as $fieldName) {
			$mapping = $class->getFieldMapping($fieldName);

			if ($mapping['type'] !== Types\Phone::PHONE) {
				continue;
			}

			$classReflection = $class->isInheritedField($fieldName) ? new ReflectionClass($mapping['declared']) : $class->getReflectionClass();

			$phoneFields[$fieldName] = [
				'phoneFieldClass' => $classReflection->getName(),
			];
		}

		return $phoneFields;
	}

	/**
	 * @param Common\Persistence\Mapping\ClassMetadata|ORM\Mapping\ClassMetadata $class
	 * @param string $eventName
	 *
	 * @return void
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function registerEvent(
		ORM\Mapping\ClassMetadata $class,
		string $eventName
	) : void {
		if (!$this->hasRegisteredListener($class, $eventName, get_called_class())) {
			$class->addEntityListener($eventName, get_called_class(), $eventName);
		}
	}

	/**
	 * @param ORM\Mapping\ClassMetadata $class
	 * @param string $eventName
	 * @param string $listenerClass
	 *
	 * @return bool
	 */
	private static function hasRegisteredListener(
		ORM\Mapping\ClassMetadata $class,
		string $eventName,
		string $listenerClass
	) : bool {
		if (!isset($class->entityListeners[$eventName])) {
			return FALSE;
		}

		foreach ($class->entityListeners[$eventName] as $listener) {
			if ($listener['class'] === $listenerClass && $listener['method'] === $eventName) {
				return TRUE;
			}
		}

		return FALSE;
	}
}
