<?php

declare(strict_types=1);

namespace App\Ai\Passage;

use Symfony\Component\Validator\Constraints as Assert;

final class Waypoint
{
    public function __construct(
        #[Assert\NotBlank]
        public string $name,
        #[Assert\Range(min: -90, max: 90)]
        public float $lat,
        #[Assert\Range(min: -180, max: 180)]
        public float $lng,
        public string $note = '',
    ) {
    }
}
