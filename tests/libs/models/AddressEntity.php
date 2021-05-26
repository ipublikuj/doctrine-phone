<?php declare(strict_types = 1);

namespace Tests\Cases\Models;

use Doctrine\ORM\Mapping as ORM;
use IPub\Phone;

/**
 * @ORM\Entity
 */
class AddressEntity
{

	/**
	 * @ORM\Id()
	 * @ORM\GeneratedValue()
	 * @ORM\Column(type="integer")
	 */
	public int $id;

	/**
	 * @var Phone\Entities\Phone|null
	 * @ORM\Column(type="phone", nullable=true)
	 */
	private ?Phone\Entities\Phone $phone;

	/**
	 * @param Phone\Entities\Phone|null $phone
	 */
	public function __construct(?Phone\Entities\Phone $phone = null)
	{
		$this->phone = $phone;
	}

	/**
	 * @return Phone\Entities\Phone|null
	 */
	public function getPhone(): ?Phone\Entities\Phone
	{
		return $this->phone;
	}

}
