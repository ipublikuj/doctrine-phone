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

declare(strict_types = 1);

namespace IPub\DoctrinePhone\Types;

use Doctrine;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Types;

use IPub;
use IPub\Phone\Entities;

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
	 * @return Entities\Phone
	 */
	public function convertToPHPValue($value, Platforms\AbstractPlatform $platform)
	{
		return Entities\Phone::fromNumber($value);
	}

	/**
	 * @param mixed $value
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return mixed|NULL|string
	 */
	public function convertToDatabaseValue($value, Platforms\AbstractPlatform $platform)
	{
		if ($value instanceof Entities\Phone) {
			return $value->getRawOutput();
		}

		return $value;
	}

	/**
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return bool
	 */
	public function requiresSQLCommentHint(Platforms\AbstractPlatform $platform)
	{
		return TRUE;
	}
}
