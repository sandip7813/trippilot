<?php

namespace App\Exceptions;

/**
 * Thrown when a user tries to use a paid AI feature without enough credits.
 * It extends AiGenerationException so every AI endpoint's existing error
 * handling shows the message to the user without extra catch blocks.
 */
class InsufficientCreditsException extends AiGenerationException
{
    public function __construct(public readonly int $requiredCredits, public readonly int $balance)
    {
        parent::__construct(__(
            'Not enough credits: this needs :required credits and you have :balance. You can buy more under Credits → Buy credits.',
            ['required' => $requiredCredits, 'balance' => $balance],
        ));
    }
}
