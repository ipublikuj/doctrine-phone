<?php
/**
 * PhoneObjectSubscriber.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Events
 * @since          1.0.0
 *
 * @date           25.12.15
 */

declare(strict_types = 1);

namespace IPub\DoctrinePhone\Events;

use Nette;
use Nette\Utils;

use Doctrine;
use Doctrine\Common;
use Doctrine\ORM;

use IPub;
use IPub\DoctrinePhone;
use IPub\DoctrinePhone\Types;

use IPub\Phone;

/**
 * Doctrine phone hydration listener
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Events
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
final class PhoneObjectSubscriber extends Nette\Object implements Common\EventSubscriber
{
	/**
	 * @var Common\Annotations\Reader
	 */
	private $annotationReader;

	/**
	 * @var Common\Persistence\ManagerRegistry
	 */
	private $managerRegistry;

	/**
	 * @var Phone\Phone
	 */
	private $phoneHelper;

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
	 * @param Common\Annotations\Reader $annotationReader
	 * @param Common\Persistence\ManagerRegistry $managerRegistry
	 * @param Phone\Phone $phoneHelper
	 */
	public function __construct(
		Common\Annotations\Reader $annotationReader,
		Common\Persistence\ManagerRegistry $managerRegistry,
		Phone\Phone $phoneHelper
	)
	{
		$this->managerRegistry = $managerRegistry;
		$this->annotationReader = $annotationReader;

		$this->phoneHelper = $phoneHelper;
	}

	/**
	 * @param ORM\Event\LoadClassMetadataEventArgs $eventArgs
	 *
	 * @return void
	 */
	public function loadClassMetadata(ORM\Event\LoadClassMetadataEventArgs $eventArgs)
	{
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
	 */
	public function postLoad($entity, ORM\Event\LifecycleEventArgs $eventArgs)
	{
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
	 */
	public function preFlush($entity, ORM\Event\PreFlushEventArgs $eventArgs)
	{
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
	 */
	private function postLoadAndPreFlush($entity, Common\Persistence\ObjectManager $objectManager)
	{
		$cache = $objectManager->getMetadataFactory()->getCacheDriver();

		if (!$fieldsMap = $this->getEntityPhoneFields($entity, $cache)) {
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
	 * @param Common\Cache\CacheProvider $cache
	 * @param Common\Persistence\Mapping\ClassMetadata|NULL $class
	 *
	 * @return array
	 */
	private function getEntityPhoneFields($entity, Common\Cache\CacheProvider $cache, Common\Persistence\Mapping\ClassMetadata $class = NULL) : array
	{
		$class = $class ?: $this->managerRegistry->getManager()->getClassMetadata(get_class($entity));

		if (isset($this->phoneFieldsCache[$class->getName()])) {
			return $this->phoneFieldsCache[$class->getName()];
		}

		if ($cache->contains($class->getName()) === TRUE) {
			$phoneFields = Utils\Json::decode($cache->fetch($class->getName()), Utils\Json::FORCE_ARRAY);

		} else {
			$phoneFields = $this->buildPhoneFields($class);
			$cache->save($class->getName(), $phoneFields ? Utils\Json::encode($phoneFields) : FALSE);
		}

		$fieldsMap = [];

		if (is_array($phoneFields) && !empty($phoneFields)) {
			foreach ($phoneFields as $phoneField => $mapping) {
				if (!isset($fieldsMap[$mapping['phoneFieldClass']])) {
					$fieldsMap[$mapping['phoneFieldClass']] = [
						'class'  => $this->managerRegistry->getManager()->getClassMetadata($mapping['phoneFieldClass']),
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
	 * @param Common\Persistence\Mapping\ClassMetadata|ORM\Mapping\ClassMetadata $class
	 *
	 * @return array
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function buildPhoneFields(Common\Persistence\Mapping\ClassMetadata $class) : array
	{
		$phoneFields = [];

		foreach ($class->getFieldNames() as $fieldName) {
			$mapping = $class->getFieldMapping($fieldName);

			if ($mapping['type'] !== Types\Phone::PHONE) {
				continue;
			}

			$classReflection = $class->isInheritedField($fieldName) ? new \ReflectionClass($mapping['declared']) : $class->getReflectionClass();

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
	private function registerEvent(ORM\Mapping\ClassMetadata $class, string $eventName)
	{
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
	private static function hasRegisteredListener(ORM\Mapping\ClassMetadata $class, string $eventName, string $listenerClass) : bool
	{
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
