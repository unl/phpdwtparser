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
 * Will scan a dreamweaver templated file for regions and other relevant info.
 */
class Scanner extends AbstractDynamicDwt
{
    /**
     * The contents of the .dwt file you wish to scan.
     *
     * @param string $dwt Source of the .dwt file
     */
    public function __construct($dwt)
    {
        $this->template = $dwt;
        $this->parse();
    }

    /**
     * Return the template markup
     *
     * @return string
     */
    public function getTemplateFile()
    {
        return $this->template;
    }
}
