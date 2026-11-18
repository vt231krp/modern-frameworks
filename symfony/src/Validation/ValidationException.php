<?php

declare(strict_types=1);

namespace App\Validation;

/**
 * Thrown when request data fails validation. Rendering mirrors Laravel's
 * 422 response: {"message": "...", "errors": {"field": ["..."]}}.
 */
class ValidationException extends \RuntimeException
{
    /**
     * @param array<string, array<int, string>> $errors
     */
    public function __construct(private readonly array $errors)
    {
        $first = $errors[array_key_first($errors)][0] ?? 'The given data was invalid.';

        parent::__construct($first);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
