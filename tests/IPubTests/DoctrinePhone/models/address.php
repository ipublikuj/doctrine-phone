<?php
/**
 * Test: IPub\DoctrinePhone\Models
 * @testCase
 *
 * @copyright      More in license.md
 * @license        http://www.ipublikuj.eu
 * @author         Adam Kadlec http://www.ipublikuj.eu
 * @package        iPublikuj:DoctrinePhone!
 * @subpackage     Tests
 * @since          1.0.0
 *
 * @date           29.12.15
 */

namespace IPubTests\DoctrinePhone;

use Doctrine\ORM\Mapping as ORM;

use Kdyby\Doctrine\Entities\BaseEntity;

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
class AddressEntity extends BaseEntity
{
	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 */
	public $id;

	/**
	 * @ORM\Column(type="phone")
	 * @var Phone\Entities\Phone
	 */
	private $phone;

	public function __construct($phone = NULL)
	{
		$this->phone = $phone;
	}

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
