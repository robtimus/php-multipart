<?php
namespace Robtimus\Multipart;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

abstract class MultipartTestBase extends TestCase
{
    private ?stdClass $config = null;

    public function __construct()
    {
        parent::__construct();
        $configFilePath = dirname(__FILE__) . '/../../config.json';
        if (file_exists($configFilePath)) {
            // @phpstan-ignore argument.type, assign.propertyType
            $this->config = json_decode(file_get_contents($configFilePath));
        }
    }

    protected function getStringConfigValue(string $key, bool $isRequired = true): ?string
    {
        $value = $this->getConfigValue($key, $isRequired);
        if (is_null($value) || is_string($value)) {
            return $value;
        }
        // @phpstan-ignore binaryOp.invalid
        throw new LogicException('non-string value for property ' . $key . ': ' . $value);
    }

    protected function getConfigValue(string $key, bool $isRequired = true): mixed
    {
        $value = is_null($this->config) ? null : $this->getValue($this->config, explode('.', $key), 0);
        if (is_null($value) && $isRequired) {
            throw new LogicException('could not find property ' . $key);
        }
        return $value;
    }

    /**
     * @param array<string> $keyParts
     */
    private function getValue(stdClass $object, array $keyParts, int $index): mixed
    {
        $key = $keyParts[$index];
        $value = property_exists($object, $key) ? $object->{$key} : null;
        if ($index === count($keyParts) || !($value instanceof stdClass)) {
            return $value;
        }

        return $this->getValue($value, $keyParts, $index + 1);
    }

    protected function setIniFromConfig(string $configKey, string $iniKey, bool $isRequired = true): void
    {
        $value = $this->getConfigValue($configKey, $isRequired);
        if (is_string($value) || is_int($value) || is_bool($value)) {
            ini_set($iniKey, strval($value));
        } elseif (!is_null($value)) {
            // @phpstan-ignore binaryOp.invalid
            throw new LogicException('invalid value for ini_set; key: ' . $configKey . ', value: ' . $value);
        }
    }
}
