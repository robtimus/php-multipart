<?php
namespace Robtimus\Multipart;

use InvalidArgumentException;
use ValueError;

/**
 * Utility functions.
 *
 * @package  Robtimus\Multipart
 * @author   Rob Spoor <robtimus@users.noreply.github.com>
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
     * Validates that an input value is a positive int.
     *
     * @param int    $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not a positive int.
     *
     * @return void
     * @throws ValueError if the value is not positive.
     */
    public static function validatePositiveInt(int $input, string $name, string $message = '')
    {
        if ($input <= 0) {
            throw new ValueError($message === '' ? $name . ' <= 0' : $message);
        }
    }

    /**
     * Validates that an input value is a non-empty string.
     *
     * @param string $input   The input to validate.
     * @param string $name    The name of the input value.
     * @param string $message An optional message to show if the value is not a non-empty string.
     *
     * @return void
     * @throws ValueError if the value is empty.
     */
    public static function validateNonEmptyString(string $input, string $name, string $message = '')
    {
        if (trim($input) === '') {
            throw new ValueError($message === '' ? $name . ' must be non-empty' : $message);
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
     * @throws InvalidArgumentException if the value cannot be used for streaming.
     */
    public static function validateStreamable(mixed $input, string $name, string $message = '')
    {
        if (!is_string($input) && !is_resource($input) && !is_callable($input)) {
            self::_throwIncorrectlyTypedException($name, $message);
        }
    }

    /**
     * Throws an exception that indicates an input value is incorrectly typed.
     *
     * @param string $name    The name of the input value.
     * @param string $message An optional message for the exception.
     *
     * @return never
     * @throws InvalidArgumentException always.
     */
    private static function _throwIncorrectlyTypedException(string $name, string $message)
    {
        throw new InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
    }
}
