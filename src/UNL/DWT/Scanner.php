<?php
/**
 * Will scan a dreamweaver templated file for regions and other relevant info.
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */
class UNL_DWT_Scanner extends UNL_DWT_DynamicAbstract
{
    /**
     * The contents of the .dwt file you wish to scan.
     *
     * @param string $dwt Source of the .dwt file
     */
    public function __construct($dwt)
    {
        $this->__template = $dwt;
        $this->parse();
    }

    /**
     * Return the template markup
     *
     * @return string
     */
    public function getTemplateFile()
    {
        return $this->__template;
    }
}
