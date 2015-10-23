<?php
/**
 * Object representing a Dreamweaver template region
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */
class UNL_DWT_Region
{
    public $name;
    public $type = 'string';
    public $len;
    public $line;
    public $flags;
    public $value;

    public function __toString()
    {
        return $this->value;
    }
}
