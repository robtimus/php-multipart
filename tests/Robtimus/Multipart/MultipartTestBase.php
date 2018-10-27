<?php
namespace Robtimus\Multipart;

use PHPUnit_Framework_TestCase;

abstract class MultipartTestBase extends PHPUnit_Framework_TestCase {

    private $config = null;

    public function __construct() {
        $configFilePath = dirname(__FILE__) . '/../../config.json';
        if (file_exists($configFilePath)) {
            $this->config = json_decode(file_get_contents($configFilePath));
        }
    }

    protected function getConfigValue($key, $isRequired = true) {
        $value = is_null($this->config) ? null : $this->getValue($this->config, explode('.', $key), 0);
        if (is_null($value) && $isRequired) {
            throw new \LogicException('could not find property ' . $key);
        }
        return $value;
    }

    private function getValue(\stdClass $object, $keyParts, $index) {
        $key = $keyParts[$index];
        $value = property_exists($object, $key) ? $object->{$key} : null;
        if ($index === count($keyParts) || !($value instanceof \stdClass)) {
            return $value;
        }

        return $this->getValue($value, $keyParts, $index + 1);
    }

    protected function setIniFromConfig($configKey, $iniKey, $isRequired = true) {
        $value = $this->getConfigValue($configKey, $isRequired);
        if (!is_null($value)) {
            ini_set($iniKey, $value);
        }
    }
}
