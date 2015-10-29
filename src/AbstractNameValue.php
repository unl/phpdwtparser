<?php
/**
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

namespace UNL\DWT;

/**
 * Object representing a Dreamweaver template named value
 */
abstract class AbstractNameValue
{
    protected $name;

    protected $value;

    public static function fromArray(array $array)
    {
        if (!isset($array['name'])) {
            throw new \InvalidArgumentException(
                'Param requires that a name is provided for this object'
            );
        }

        $nameValue = new static($array['name']);
        foreach ($array as $name => $value) {
            // normalize key
            switch (strtolower(str_replace(['.', '-', '_'], '', $name))) {
                case 'value':
                    $nameValue->setValue($value);
                    break;
            }
        }

        return $nameValue;
    }

    public function __construct($name = '', $value = '')
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value)
    {
        $this->value = $value;
        return $this;
    }

    public function __toString()
    {
        return $this->value;
    }
}
