<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Something was refused for a stock reason. The message is written for the
 * person who will read it on screen, not for a developer - it goes straight
 * into a flash message.
 */
class StockException extends RuntimeException
{
    public static function notEnoughStock(string $itemName, string $available, string $wanted): self
    {
        return new self(
            "Not enough {$itemName}. There is {$available} free and {$wanted} was asked for.",
        );
    }

    public static function wrongStep(string $message): self
    {
        return new self($message);
    }
}
