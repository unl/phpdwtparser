<?php
/**
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Kevin Abel <kabel2@unl.edu>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

use zz\Html\HTMLMinify;
use zz\Html\HTMLNames;
use zz\Html\HTMLToken;

abstract class UNL_DWT_DynamicAbstract extends UNL_DWT
{
    /**
     * @var UNL_DWT_Region[] Assoc array of template region names.
     */
    protected $regions = array();

    protected $canBeginInstance = false;

    protected $canLockRegion = false;

    protected $regionNestLevel = 0;

    protected $activeRegion;

    protected $tokens;

    /**
     * Parse the DWT into regions
     */
    protected function parse()
    {
        $this->regions = array();
        $this->__params = array();
        $this->canBeginInstance = false;
        $this->regionNestLevel = 0;
        $this->activeRegion = null;
        $this->canLockRegion = false;

        $dwt = $this->getTemplateFile();
        $htmlParser = new HTMLMinify($dwt);
        $this->tokens = $htmlParser->getTokens();

        foreach ($this->tokens as $token) {
            $type = $token->getType();

            if ($type !== HTMLToken::Comment) {
                $this->handleTemplateContent($token);
                continue;
            }

            $comment = $token->getData();

            if ($this->canBeginInstance && $this->isInstnaceBegin($comment)) {
                $this->canBeginInstance = false;
                $this->canLockRegion = true;
                continue;
            } elseif ($paramMatch = $this->isParamDef($comment)) {
                $this->addParam(array(
                    'name' => $paramMatch[2],
                    'type' => $paramMatch[3],
                    'value' => $paramMatch[4]
                ));
                continue;
            } elseif ($regionBegin = $this->isRegionBegin($comment)) {
                $this->handleRegionBegin($regionBegin);
                continue;
            } elseif ($this->isRegionEnd($comment)) {
                $this->handleRegionEnd();
                continue;
            }

            // it is a generic comment
            if ($region) {
                $region->value .= $comment;
            }
        }
    }

    protected function handleTemplateContent(HTMLToken $token)
    {
        $this->canBeginInstance = false;

        if ($token->getTagName() === HTMLNames::htmlTag) {
            $this->canBeginInstance = true;
        }

        if ($this->activeRegion) {
            $this->activeRegion->value .= $this->getStringUtils()->buildElement($token);
        }
    }

    protected function handleRegionBegin($name)
    {
        $stringUtils = $this->getStringUtils();
        $region = $this->activeRegion;
        $this->canBeginInstance = false;

        if ($region) {
            // Found a new nested region; remove the previous one
            $region->value = str_replace(
                $stringUtils->getRegionBeginMarker(UNL_DWT::INSTANCE_TOKEN, $region->name),
                '',
                $region->value
            );
            ++$this->regionNestLevel;
        }

         $this->activeRegion = new UNL_DWT_Region($name);
    }

    protected function handleRegionEnd()
    {
        $stringUtils = $this->getStringUtils();
        $region = $this->activeRegion;
        $this->canBeginInstance = false;

        if ($this->regionNestLevel) {
            --$this->regionNestLevel;

            if ($region) {
                $this->addRegion($region);
                $this->activeRegion = null;
            }

            return;
        }

        if ($region) {
            if (!$this->canLockRegion ||
                strpos($region->value, $stringUtils->getNestedRegionLockExpression()) === false
            ) {
                $this->addRegion($region);
                $this->activeRegion = null;
            }

            return;
        }
    }

    protected function isInstnaceBegin($comment)
    {
        $pattern = $this->getStringUtils()->getInstanceBeginPattern();
        if (preg_match($pattern, $comment)) {
            return true;
        }
        return false;
    }

    protected function isParamDef($comment)
    {
        $matches = array();
        $pattern = $this->getStringUtils()->getParamDefPattern();
        if (preg_match($pattern, $comment, $matches)) {
            return $matches;
        }
        return false;
    }

    protected function isRegionBegin($comment)
    {
        $matches = array();
        $pattern = $this->getStringUtils()->getRegionBeginPattern();
        if (preg_match($pattern, $comment, $matches)) {
            return $matches[2];
        }
        return false;
    }

    protected function isRegionEnd($comment)
    {
        $pattern = $this->getStringUtils()->getRegionEndPattern();
        if (preg_match($pattern, $comment)) {
            return true;
        }
        return false;
    }

    /**
     * returns the region object
     *
     * @param string $region
     * @return UNL_DWT_Region
     */
    public function getRegion($region)
    {
        if (isset($this->regions[$region])) {
            return $this->regions[$region];
        }
        return null;
    }

    /**
     * returns array of all the regions found
     *
     * @return UNL_DWT_Region[]
     */
    public function getRegions()
    {
        return $this->regions;
    }

    public function addRegion(UNL_DWT_Region $region)
    {
        $this->regions[$region->name] = $region;
        return $this;
    }

    public function addParam($spec)
    {
        $this->__params[$spec['name']] = $spec;
        return $this;
    }

    /**
     * returns if the named region exists in this DWT
     *
     * @param string $region Region name to look for
     * @return bool
     */
    public function __isset($region)
    {
        return isset($this->regions[$region]);
    }

    /**
     * returns the stored value of the named region
     *
     * @param  string $region Region name to return value of
     * @return mixed
     */
    public function __get($region)
    {
        if (isset($this->regions[$region])) {
            return $this->regions[$region]->value;
        }

        $trace = debug_backtrace();
        trigger_error(
            'Undefined property: ' . $region .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line'],
            E_USER_NOTICE
        );

        return null;
    }

    /**
     * sets the value of the named region, creating a new one if it doesn't exist
     *
     * @param string $region Region name
     * @param string $value  Region value
     */
    public function __set($region, $value)
    {
        $dwtRegion = $this->getRegion($region);

        if (!$dwtRegion) {
            $dwtRegion = new UNL_DWT_Region($region);
            $this->addRegion($dwtRegion);
        }

        $dwtRegion->value = $value;
    }
}
