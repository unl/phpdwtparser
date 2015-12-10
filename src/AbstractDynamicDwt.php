<?php
/**
 * @category  Templates
 * @package   UNL_DWT
 * @author    Kevin Abel <kabel2@unl.edu>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

namespace UNL\DWT;

use zz\Html\HTMLMinify;
use zz\Html\HTMLNames;
use zz\Html\HTMLToken;

abstract class AbstractDynamicDwt extends AbstractDwt
{
    protected $canBeginInstance = false;

    protected $canLockRegion = false;

    protected $regionNestLevel = 0;

    protected $activeRegion;

    protected $tokens;

    /**
     * Parse the DWT into regions
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function parse()
    {
        $this->regions = [];
        $this->params = [];
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
                $this->addParam(Param::fromArray([
                    'name' => $paramMatch[2],
                    'type' => $paramMatch[3],
                    'value' => $paramMatch[4]
                ]));
                continue;
            } elseif ($regionBegin = $this->isRegionBegin($comment)) {
                $this->handleRegionBegin($regionBegin);
                continue;
            } elseif ($this->isRegionEnd($comment)) {
                $this->handleRegionEnd();
                continue;
            }

            // it is a generic comment
            if ($this->activeRegion) {
                $this->activeRegion->setValue($this->activeRegion->getValue() . $comment);
            }
        }
    }

    protected function handleTemplateContent(HTMLToken $token)
    {
        $this->canBeginInstance = false;
        $region = $this->activeRegion;

        if ($token->getTagName() === HTMLNames::htmlTag) {
            $this->canBeginInstance = true;
        }

        if ($region) {
            $region->setValue($region->getValue() . $this->getStringUtils()->buildElement($token));
        }
    }

    protected function handleRegionBegin($name)
    {
        $stringUtils = $this->getStringUtils();
        $region = $this->activeRegion;
        $this->canBeginInstance = false;

        if ($region) {
            // Found a new nested region; remove the previous one
            $region->setValue(str_replace(
                $stringUtils->getRegionBeginMarker($stringUtils::INSTANCE_TOKEN, $region->getName()),
                '',
                $region->getValue()
            ));
            ++$this->regionNestLevel;
        }

         $this->activeRegion = new Region($name);
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
                strpos($region->getValue(), $stringUtils->getNestedRegionLockExpression()) === false
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
        $matches = [];
        $pattern = $this->getStringUtils()->getParamDefPattern();
        if (preg_match($pattern, $comment, $matches)) {
            return $matches;
        }
        return false;
    }

    protected function isRegionBegin($comment)
    {
        $matches = [];
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

    public function addRegion(Region $region)
    {
        $this->regions[$region->getName()] = $region;
        return $this;
    }

    public function addParam(Param $param)
    {
        $this->params[$param->getName()] = $param;
        return $this;
    }
}
