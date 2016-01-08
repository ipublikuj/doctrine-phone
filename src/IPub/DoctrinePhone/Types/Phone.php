<?php
/**
 * Phone.php
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           25.12.15
 */

namespace IPub\DoctrinePhone\Types;

use Doctrine;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Types;

use IPub;

/**
 * Doctrine phone data type
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@fastybird.com>
 */
class Phone extends Types\StringType
{
	/**
	 * Define class name
	 */
	const CLASS_NAME = __CLASS__;

	/**
	 * Data type name
	 */
	const PHONE = 'phone';

	/**
	 * @return string
	 */
	public function getName()
	{
		return self::PHONE;
	}

	/**
	 * @param mixed $value
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return IPub\Phone\Entities\Phone
	 */
	public function convertToPHPValue($value, Platforms\AbstractPlatform $platform)
	{
		return $value;
	}

	/**
	 * @param mixed $value
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return mixed|NULL|string
	 */
	public function convertToDatabaseValue($value, Platforms\AbstractPlatform $platform)
	{
		if ($value instanceof IPub\Phone\Entities\Phone) {
			return $value->getRawOutput();
		}

		return $value;
	}
}
