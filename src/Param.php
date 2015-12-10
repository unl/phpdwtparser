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
 * Object representing a Dreamweaver template param
 */
class Param extends AbstractNameValue
{
    protected $type;

    public static function fromArray(array $array)
    {
        $param = parent::fromArray($array);

        foreach ($array as $name => $value) {
            // normalize key
            switch (strtolower(str_replace(['.', '-', '_'], '', $name))) {
                case 'type':
                    $param->setType($value);
                    break;
            }
        }

        return $param;
    }

    public function __construct($name = '', $value = '', $type = '')
    {
        parent::__construct($name, $value);
        $this->setType($type);
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type)
    {
        $supportedTypes = [
            'text',
            'boolean',
            'color',
            'url',
            'number'
        ];

        if (in_array($type, $supportedTypes)) {
            $this->type = $type;
            return $this;
        }

        $this->type = $supportedTypes;
        return $this;
    }
}
