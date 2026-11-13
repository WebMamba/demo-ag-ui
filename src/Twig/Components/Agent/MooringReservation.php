<?php

declare(strict_types=1);

namespace App\Twig\Components\Agent;

use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * The question behind the `reserve_mooring` interrupt. Its buttons resume the run.
 */
#[AsTwigComponent]
final class MooringReservation
{
    public string $marina = '';
    public string $island = '';
    public string $arrival = '';
    public int $nights = 3;
    public float $boatLengthMeters = 12.0;
}
