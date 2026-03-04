<?php

if (!function_exists('asInt')) {
    /**
     * Normalise a value to int.
     */
    function asInt(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value) || is_string($value)) {
            return (int) $value;
        }

        throw new InvalidArgumentException('Value cannot be converted to int');
    }
}
