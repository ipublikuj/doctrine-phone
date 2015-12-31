<?php
/**
 * PhoneObjectHydrationListener.php
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

namespace IPub\DoctrinePhone\Events;

use Nette;
use Nette\Utils;

use Doctrine;
use Doctrine\Common;
use Doctrine\ORM;

use Kdyby;
use Kdyby\Events;

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
class PhoneObjectHydrationListener extends Nette\Object implements Events\Subscriber
{
	/**
	 * @var Common\Cache\CacheProvider
	 */
	private $cache;

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
			'Doctrine\\ORM\\Event::loadClassMetadata'
		];
	}

	/**
	 * @param Common\Cache\CacheProvider $cache
	 * @param Common\Annotations\Reader $annotationReader
	 * @param Common\Persistence\ManagerRegistry $managerRegistry
	 * @param Phone\Phone $phoneHelper
	 */
	public function __construct(
		Common\Cache\CacheProvider $cache,
		Common\Annotations\Reader $annotationReader,
		Common\Persistence\ManagerRegistry $managerRegistry,
		Phone\Phone $phoneHelper
	)
	{
		$this->cache = $cache;
		$this->cache->setNamespace(get_called_class());
		$this->managerRegistry = $managerRegistry;
		$this->annotationReader = $annotationReader;

		$this->phoneHelper = $phoneHelper;
	}

	/**
	 * @param ORM\Event\LoadClassMetadataEventArgs $eventArgs
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

		if (!$this->hasRegisteredListener($class, ORM\Events::postLoad, get_called_class())) {
			$class->addEntityListener(ORM\Events::postLoad, get_called_class(), ORM\Events::postLoad);
		}

		if (!$this->hasRegisteredListener($class, ORM\Events::preFlush, get_called_class())) {
			$class->addEntityListener(ORM\Events::preFlush, get_called_class(), ORM\Events::preFlush);
		}
	}

	/**
	 * @param $entity
	 *
	 * @throws Phone\Exceptions\NoValidCountryException
	 * @throws Phone\Exceptions\NoValidPhoneException
	 */
	public function postLoad($entity)
	{
		if (!$fieldsMap = $this->getEntityPhoneFields($entity)) {
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
	 *
	 * @throws Phone\Exceptions\NoValidCountryException
	 * @throws Phone\Exceptions\NoValidPhoneException
	 */
	public function preFlush($entity)
	{
		if (!$fieldsMap = $this->getEntityPhoneFields($entity)) {
			return;
		}

		foreach ($fieldsMap as $phoneAssoc => $phoneMeta) {
			foreach ($phoneMeta['fields'] as $phoneField) {
				$number = $phoneMeta['class']->getFieldValue($entity, $phoneField);

				if ($number === NULL) {
					continue;
				}

				if (!$number instanceof Phone\Entities\Phone) {
					$phoneMeta['class']->setFieldValue($entity, $phoneField, Phone\Entities\Phone::fromNumber($number));
				}
			}
		}
	}

	/**
	 * @param $entity
	 * @param ORM\Mapping\ClassMetadata|NULL $class
	 *
	 * @return array
	 */
	private function getEntityPhoneFields($entity, ORM\Mapping\ClassMetadata $class = NULL)
	{
		$class = $class ?: $this->managerRegistry->getManager()->getClassMetadata(get_class($entity));

		if (isset($this->phoneFieldsCache[$class->getName()])) {
			return $this->phoneFieldsCache[$class->getName()];
		}

		if ($this->cache->contains($class->getName()) === TRUE) {
			$phoneFields = Utils\Json::decode($this->cache->fetch($class->getName()), Utils\Json::FORCE_ARRAY);

		} else {
			$phoneFields = $this->buildPhoneFields($class);
			$this->cache->save($class->getName(), $phoneFields ? Utils\Json::encode($phoneFields) : FALSE);
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
	 * @param ORM\Mapping\ClassMetadata $class
	 *
	 * @return array
	 *
	 * @throws ORM\Mapping\MappingException
	 */
	private function buildPhoneFields(ORM\Mapping\ClassMetadata $class)
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
	 * @param ORM\Mapping\ClassMetadata $class
	 * @param string $eventName
	 * @param string $listenerClass
	 *
	 * @return bool
	 */
	private static function hasRegisteredListener(ORM\Mapping\ClassMetadata $class, $eventName, $listenerClass)
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
