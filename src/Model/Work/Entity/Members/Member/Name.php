<?php

declare(strict_types=1);

namespace App\Model\Work\Entity\Members\Member;

use Doctrine\ORM\Mapping as ORM;
use Webmozart\Assert\Assert;

/**
 * @ORM\Embeddable
 */
class Name
{
    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $first;
    /**
     * @var string
     * @ORM\Column(type="string")
     */
    private $last;

    public function __construct(string $first, string $last)
    {
        $this->validate($first, $last);
        $this->first = $first;
        $this->last = $last;
    }

    public function getFirst(): string
    {
        return $this->first;
    }

    public function getLast(): string
    {
        return $this->last;
    }

    public function getFull(): string
    {
        return $this->first . ' ' . $this->last;
    }

    private function validate(string $first, string $last): void
    {
        Assert::notEmpty($first);
        Assert::notEmpty($last);
    }
}
