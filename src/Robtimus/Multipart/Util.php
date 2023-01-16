<?php
namespace Robtimus\Multipart;

/**
 * Utility functions.
 *
 * @package  Robtimus\Multipart
 * @author   Rob Spoor
 * @license  https://www.apache.org/licenses/LICENSE-2.0.txt The Apache Software License, Version 2.0
 * @internal
 */
final class Util
{
    /**
     * Private constructor to prevent creating instances.
     */
    private function __construct()
    {
    }

    /**
     * Validates that an input value is an int.
     *
     * @param mixed  $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not an int.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the value is not an int.
     */
    public static function validateInt(&$input, $name, $message = '')
    {
        if (!is_int($input)) {
            self::throwIncorrectlyTypedException($name, $message);
        }
    }

    /**
     * Validates that an input value is a positive int.
     *
     * @param mixed  $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not a positive int.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the value is not a positive int.
     */
    public static function validatePositiveInt(&$input, $name, $message = '')
    {
        self::validateInt($input, $name, $message);
        if ($input <= 0) {
            throw new \InvalidArgumentException($message === '' ? $name . ' <= 0' : $message);
        }
    }

    /**
     * Validates that an input value is a string.
     *
     * @param mixed  $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not a string.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the value is not a string.
     */
    public static function validateString(&$input, $name, $message = '')
    {
        if (!is_string($input)) {
            self::throwIncorrectlyTypedException($name, $message);
        }
    }

    /**
     * Validates that an input value is a non-empty string.
     *
     * @param mixed  $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not a non-empty string.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the value is not a non-empty string.
     */
    public static function validateNonEmptyString(&$input, $name, $message = '')
    {
        self::validateString($input, $name, $message);
        if (trim($input) === '') {
            throw new \InvalidArgumentException($message === '' ? $name . ' must be non-empty' : $message);
        }
    }

    /**
     * Validates that an input value can be used for streaming.
     *
     * @param mixed  $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value cannot be used for streaming.
     *
     * @return void
     *
     * @throws \InvalidArgumentException if the value cannot be used for streaming.
     */
    public static function validateStreamable(&$input, $name, $message = '')
    {
        if (!is_string($input) && !is_resource($input) && !is_callable($input)) {
            self::throwIncorrectlyTypedException($name, $message);
        }
    }

    /**
     * Throws an exception that indicates an input value is incorrectly typed.
     *
     * @param string $name    The name of the input value.
     * @param string $message An optional message for the exception.
     *
     * @return never
     *
     * @throws \InvalidArgumentException always.
     */
    private static function throwIncorrectlyTypedException($name, $message)
    {
        throw new \InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
    }
}
