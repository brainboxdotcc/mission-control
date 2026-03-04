<?php

if (!function_exists('asString')) {
    /**
     * Normalise a value to string.
     */
    function asString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return $value;
        }

        throw new InvalidArgumentException('Value cannot be converted to string');
    }
}
