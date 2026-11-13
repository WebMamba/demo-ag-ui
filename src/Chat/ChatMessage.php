<?php

declare(strict_types=1);

namespace App\Chat;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * What the crew types in the basic chat.
 */
final readonly class ChatMessage
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 2000)]
        public string $message = '',
    ) {
    }
}
