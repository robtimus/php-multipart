<?php
namespace Robtimus\Multipart;

final class Util {

    private function __construct() {
    }

    static function validateInt(&$input, $name, $message = '') {
        if (!is_int($input)) {
            throw new \InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
        }
    }

    static function validatePositiveInt(&$input, $name, $message = '') {
        self::validateInt($input, $name, $message);
        if ($input <= 0) {
            throw new \InvalidArgumentException($message === '' ? $name . ' <= 0' : $message);
        }
    }

    static function validateString(&$input, $name, $message = '') {
        if (!is_string($input)) {
            throw new \InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
        }
    }

    static function validateNonEmptyString(&$input, $name, $message = '') {
        self::validateString($input, $name, $message);
        if (trim($input) === '') {
            throw new \InvalidArgumentException($message === '' ? $name . ' must be non-empty' : $message);
        }
    }

    static function validateStreamable(&$input, $name, $message = '') {
        if (!is_string($input) && !is_resource($input) && !is_callable($input)) {
            throw new \InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
        }
    }

    static function validateArray(&$input, $name, $message = '') {
        if (!is_array($input)) {
            throw new \InvalidArgumentException($message === '' ? $name . ' is incorrectly typed' : $message);
        }
    }

    static function validateNonEmptyArray(&$input, $name, $message = '') {
        self::validateArray($input, $name, $message);
        if (count($input) === 0) {
            throw new \InvalidArgumentException($message === '' ? $name . ' is empty' : $message);
        }
    }

    static function validateHomogeneousArray(&$array, &$element, $message) {
        if (self::getType($array[0]) !== self::getType($element)) {
            throw new \InvalidArgumentException($message);
        }
    }

    static function getType(&$input) {
        return is_object($input) ? get_class($input) : gettype($input);
    }
}
