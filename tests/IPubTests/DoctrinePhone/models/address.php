<?php
/**
 * Test: IPub\DoctrinePhone\Models
 *
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec <adam.kadlec@ipublikuj.eu>
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

declare(strict_types = 1);

namespace IPubTests\DoctrinePhone;

use Doctrine\ORM\Mapping as ORM;

use IPub\Phone;

/**
 * @ORM\Entity()
 *
 * @ORM\InheritanceType("SINGLE_TABLE")
 * @ORM\DiscriminatorColumn(name="type", type="string")
 * @ORM\DiscriminatorMap({
 *    "address" = "AddressEntity",
 *    "specific" = "SpecificAddressEntity",
 * })
 */
class AddressEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 */
	public $id;

	/**
	 * @ORM\Column(type="phone", nullable=true)
	 * @var Phone\Entities\Phone|NULL
	 */
	private $phone;

	/**
	 * @param Phone\Entities\Phone|NULL $phone
	 */
	public function __construct(Phone\Entities\Phone $phone = NULL)
	{
		$this->phone = $phone;
	}

	/**
	 * @return Phone\Entities\Phone|NULL
	 */
	public function getPhone()
	{
		return $this->phone;
	}
}

/**
 * @ORM\Entity()
 */
class SpecificAddressEntity extends AddressEntity
{

}
