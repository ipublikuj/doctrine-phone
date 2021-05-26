# Quickstart

This extension brings you a phone number extension into your doctrine entities. This extension is based on the [iPublikuj:Phone!](https://github.com/iPublikuj/phone) extension.

## Installation

The best way to install **ipub/doctrine-phone** is using [Composer](http://getcomposer.org/):

```sh
composer require ipub/doctrine-phone
```

After that, you have to register extension in config.neon.

```neon
extensions:
    doctrinePhone: IPub\DoctrinePhone\DI\DoctrinePhoneExtension
```

## Usage

Usage is simple. Just set column type to phone in your doctrine entity:

```php
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 */
class AddressEntity extends BaseEntity
{

    // ...

    /**
     * @ORM\Column(type="phone")
     * @var Phone\Entities\Phone
     */
    private $phone;

    /**
     * @return IPub\Phone\Entities\Phone
     */
    public function getPhone()
    {
        return $this->phone;
    }

    /**
     * @var $phone IPub\Phone\Entities\Phone
     */
    public function setPhone($phone)
    {
        $this->phone = $phone
    }

}
```

and that is it. Now when you get phone from your entity, an object of IPub\Phone\Entities\Phone will be returned.
