<?php declare(strict_types = 1);

/**
 * Phone.php
 *
 * @copyright      More in LICENSE.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Types
 * @since          1.0.0
 *
 * @date           25.12.15
 */

namespace IPub\DoctrinePhone\Types;

use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Types;
use IPub\Phone\Entities;
use IPub\Phone\Exceptions;

/**
 * Doctrine phone data type
 *
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Types
 *
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 */
class Phone extends Types\StringType
{

	// Data type name
	public const PHONE = 'phone';

	/** @return string */
	public function getName(): string
	{
		return self::PHONE;
	}

	/**
	 * @param mixed $value
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return Entities\Phone|null
	 *
	 * @throws Exceptions\NoValidCountryException
	 * @throws Exceptions\NoValidPhoneException
	 */
	// phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps
	public function convertToPHPValue($value, Platforms\AbstractPlatform $platform): ?Entities\Phone
	{
		return $value === null ? null : Entities\Phone::fromNumber($value);
	}

	/**
	 * @param mixed $value
	 * @param Platforms\AbstractPlatform $platform
	 *
	 * @return mixed|string|null
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
	// phpcs:ignore Generic.NamingConventions.CamelCapsFunctionName.ScopeNotCamelCaps
	public function requiresSQLCommentHint(Platforms\AbstractPlatform $platform): bool
	{
		return true;
	}

}
