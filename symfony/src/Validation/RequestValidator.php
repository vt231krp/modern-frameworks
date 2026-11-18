<?php

declare(strict_types=1);

namespace App\Validation;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\Request;

/**
 * A compact validator implementing the subset of Laravel validation rules
 * used by the Gym API. Rules are written with Laravel's pipe syntax:
 *
 *   'name' => 'required|string|max:100'
 *   'status' => 'nullable|in:present,absent,late'
 *   'room_id' => 'nullable|integer|exists:rooms,room_id'
 */
class RequestValidator
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array<string, string|array<int, string>> $rules
     *
     * @return array<string, mixed> validated input
     */
    public function validate(Request $request, array $rules): array
    {
        $data = $this->input($request);
        $errors = [];
        $result = [];

        foreach ($rules as $field => $fieldRules) {
            $list = \is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $present = \array_key_exists($field, $data);
            $sometimes = \in_array('sometimes', $list, true);
            $nullable = \in_array('nullable', $list, true);

            if ($sometimes && !$present) {
                continue;
            }

            if (!$present) {
                if ($this->isRequired($list, $data)) {
                    $errors[$field][] = "The {$field} field is required.";
                    continue;
                }

                if ($nullable) {
                    $result[$field] = null;
                }

                continue;
            }

            $value = $data[$field];

            // Laravel's ConvertEmptyStringsToNull middleware.
            if ('' === $value) {
                $value = null;
            }

            if ($this->isRequired($list, $data) && (null === $value || '' === $value)) {
                $errors[$field][] = "The {$field} field is required.";
                continue;
            }

            if (null === $value) {
                if ($nullable) {
                    $result[$field] = null;
                } else {
                    $errors[$field][] = "The {$field} field is invalid.";
                }

                continue;
            }

            $message = null;
            $normalized = $value;
            $isNumeric = false;
            $isInteger = false;

            foreach ($list as $rule) {
                [$name, $argument] = array_pad(explode(':', $rule, 2), 2, null);

                switch ($name) {
                    case 'sometimes':
                    case 'nullable':
                    case 'required':
                        break;

                    case 'required_without':
                        // Handled by isRequired() before the rule loop.
                        break;

                    case 'string':
                        if (!\is_string($normalized)) {
                            $message = "The {$field} field must be a string.";
                        }
                        break;

                    case 'integer':
                        $isInteger = true;
                        $isNumeric = true;
                        $integer = filter_var($normalized, FILTER_VALIDATE_INT);
                        if (false === $integer) {
                            $message = "The {$field} field must be an integer.";
                        } else {
                            $normalized = $integer;
                        }
                        break;

                    case 'numeric':
                        $isNumeric = true;
                        if (!is_numeric($normalized)) {
                            $message = "The {$field} field must be a number.";
                        } else {
                            $normalized = (float) $normalized;
                        }
                        break;

                    case 'boolean':
                        $boolean = $this->parseBoolean($normalized);
                        if (null === $boolean) {
                            $message = "The {$field} field must be true or false.";
                        } else {
                            $normalized = $boolean;
                        }
                        break;

                    case 'email':
                        if (!\is_string($normalized) || false === filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
                            $message = "The {$field} field must be a valid email address.";
                        }
                        break;

                    case 'date':
                        $date = $this->parseDate($normalized);
                        if (null === $date) {
                            $message = "The {$field} field must be a valid date.";
                        } else {
                            $normalized = $date;
                        }
                        break;

                    case 'in':
                        $allowed = explode(',', (string) $argument);
                        if (!\in_array((string) $normalized, $allowed, true)) {
                            $message = "The selected {$field} is invalid.";
                        }
                        break;

                    case 'max':
                        $max = (float) $argument;
                        $actual = ($isNumeric || $isInteger) ? (float) $normalized : mb_strlen((string) $normalized);
                        if ($actual > $max) {
                            $message = ($isNumeric || $isInteger)
                                ? "The {$field} field must not be greater than {$argument}."
                                : "The {$field} field must not be greater than {$argument} characters.";
                        }
                        break;

                    case 'min':
                        $min = (float) $argument;
                        $actual = ($isNumeric || $isInteger) ? (float) $normalized : mb_strlen((string) $normalized);
                        if ($actual < $min) {
                            $message = ($isNumeric || $isInteger)
                                ? "The {$field} field must be at least {$argument}."
                                : "The {$field} field must be at least {$argument} characters.";
                        }
                        break;

                    case 'unique':
                        [$table, $column, $ignore, $ignoreColumn] = array_pad(explode(',', (string) $argument), 4, null);
                        if ($this->existsInDatabase((string) $table, (string) $column, $normalized, $ignore, $ignoreColumn)) {
                            $message = "The {$field} has already been taken.";
                        }
                        break;

                    case 'exists':
                        [$table, $column] = array_pad(explode(',', (string) $argument), 2, 'id');
                        if (!$this->existsInDatabase((string) $table, (string) $column, $normalized)) {
                            $message = "The selected {$field} is invalid.";
                        }
                        break;

                    case 'after_or_equal':
                        $other = (string) $argument;
                        $otherValue = $result[$other] ?? $data[$other] ?? null;

                        // Mirrors Laravel's Rule::when($request->filled(...)).
                        // When the comparison field is absent, the rule is skipped.
                        if (null === $otherValue || '' === $otherValue) {
                            break;
                        }

                        $otherDate = $this->parseDate($otherValue);
                        $current = $this->parseDate($normalized);

                        if (null !== $otherDate && null !== $current && $current < $otherDate) {
                            $message = "The {$field} field must be a date after or equal to {$other}.";
                        } elseif (null !== $current) {
                            $normalized = $current;
                        }
                        break;

                    case 'confirmed':
                        if (($data[$field.'_confirmation'] ?? null) !== $normalized) {
                            $message = "The {$field} field confirmation does not match.";
                        }
                        break;
                }

                if (null !== $message) {
                    break;
                }
            }

            if (null !== $message) {
                $errors[$field][] = $message;
            } else {
                $result[$field] = $normalized;
            }
        }

        if ([] !== $errors) {
            throw new ValidationException($errors);
        }

        return $result;
    }

    /**
     * @param array<int, string> $rules
     * @param array<string, mixed> $data
     */
    private function isRequired(array $rules, array $data): bool
    {
        if (\in_array('required', $rules, true)) {
            return true;
        }

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'required_without:')) {
                $other = substr($rule, \strlen('required_without:'));

                return !isset($data[$other]) || null === $data[$other] || '' === $data[$other];
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function input(Request $request): array
    {
        $data = array_merge($request->query->all(), $request->request->all());

        $content = $request->getContent();
        if ('' !== $content && str_contains((string) $request->headers->get('Content-Type'), 'json')) {
            $decoded = json_decode($content, true);
            if (!\is_array($decoded)) {
                throw new ValidationException(['body' => ['The request body must be valid JSON.']]);
            }
            $data = array_merge($data, $decoded);
        }

        return $data;
    }

    private function parseBoolean(mixed $value): ?bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        return match ($value) {
            1, '1', 'true' => true,
            0, '0', 'false' => false,
            default => null,
        };
    }

    private function parseDate(mixed $value): ?\DateTimeImmutable
    {
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        if (!\is_string($value) && !\is_int($value)) {
            return null;
        }

        try {
            return new \DateTimeImmutable((string) $value);
        } catch (\Exception) {
            return null;
        }
    }

    private function existsInDatabase(
        string $table,
        string $column,
        mixed $value,
        ?string $ignore = null,
        ?string $ignoreColumn = null,
    ): bool {
        $sql = sprintf('SELECT 1 FROM %s WHERE %s = ?', $table, $column);
        $parameters = [$value];

        if (null !== $ignore && '' !== $ignore) {
            $sql .= sprintf(' AND %s <> ?', $ignoreColumn ?: 'id');
            $parameters[] = $ignore;
        }

        return false !== $this->connection->fetchOne($sql, $parameters);
    }
}
