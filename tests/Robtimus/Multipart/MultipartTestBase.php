<?php
namespace Robtimus\Multipart;

use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

abstract class MultipartTestBase extends TestCase
{
    private $_config = null;

    public function __construct()
    {
        parent::__construct();
        $configFilePath = dirname(__FILE__) . '/../../config.json';
        if (file_exists($configFilePath)) {
            $this->_config = json_decode(file_get_contents($configFilePath));
        }
    }

    protected function getConfigValue($key, $isRequired = true)
    {
        $value = is_null($this->_config) ? null : $this->_getValue($this->_config, explode('.', $key), 0);
        if (is_null($value) && $isRequired) {
            throw new LogicException('could not find property ' . $key);
        }
        return $value;
    }

    private function _getValue(stdClass $object, $keyParts, $index)
    {
        $key = $keyParts[$index];
        $value = property_exists($object, $key) ? $object->{$key} : null;
        if ($index === count($keyParts) || !($value instanceof stdClass)) {
            return $value;
        }

        return $this->_getValue($value, $keyParts, $index + 1);
    }

    protected function setIniFromConfig($configKey, $iniKey, $isRequired = true)
    {
        $value = $this->getConfigValue($configKey, $isRequired);
        if (!is_null($value)) {
            ini_set($iniKey, $value);
        }
    }
}
