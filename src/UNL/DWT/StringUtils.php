<?php
/**
 * Base class which understands Dreamweaver Templates.
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Kevin Abel <kabel2@unl.edu>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

use zz\Html\HTMLToken;

class UNL_DWT_StringUtils
{
    const TEMPLATE_TOKEN = 'Template';
    const INSTANCE_TOKEN = 'Instance';

    const INSTANCE_BEGIN_TOKEN = '<!-- InstanceBegin template="%s" codeOutsideHTMLIsLocked="%s" -->';
    const INSTANCE_END_TOKEN   = '<!-- InstanceEnd -->';

    const REGION_BEGIN_TOKEN = '<!-- %sBeginEditable name="%s" -->';
    const REGION_END_TOKEN   = '<!-- %sEndEditable -->';

    const PARAM_DEF_TOKEN         = '<!-- %sParam name="%s" type="%s" value="%s" -->';
    const PARAM_REPLACE_TOKEN     = '@@(%s)@@';
    const PARAM_REPLACE_TOKEN_ALT = '@@(_document[\'%s\'])@@';

    protected static $instance;

    public static function getInstance()
    {
        if (!static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    public function getInstanceBeginMarker($template, $lockOutsideHtml = 'false')
    {
        return sprintf(self::INSTANCE_BEGIN_TOKEN, $template, $lockOutsideHtml);
    }

    public function getInstanceEndMarker()
    {
        return self::INSTANCE_END_TOKEN;
    }

    public function getInstanceBeginPattern($nameGroup = null)
    {
        $attributePattern = $this->getMarkerAttributePatternGroup();

        if (null === $nameGroup) {
            $nameGroup = $attributePattern;
        }

        return '/' . sprintf(
            self::INSTANCE_BEGIN_TOKEN,
            $nameGroup,
            $attributePattern
        ) . '/';
    }

    public function getMarkerTypes()
    {
        return array(
            self::TEMPLATE_TOKEN,
            self::INSTANCE_TOKEN,
        );
    }

    protected function sanitizeMarkerType($type)
    {
        $markerTypes = $this->getMarkerTypes();

        if (empty($type) || !in_array($type, $markerTypes)) {
            return current($markerTypes);
        }

        return $type;
    }

    protected function getMarkerTypePatternGroup()
    {
        return '(' . implode('|', $this->getMarkerTypes()) . ')';
    }

    protected function getMarkerAttributePatternGroup()
    {
        return '([^"]*)';
    }

    public function getRegionBeginMarker($type, $region)
    {
        $type = $this->sanitizeMarkerType($type);
        return sprintf(self::REGION_BEGIN_TOKEN, $type, $region);
    }

    public function getRegionBeginPattern($nameGroup = null)
    {
        if (null === $nameGroup) {
            $nameGroup = $this->getMarkerAttributePatternGroup();
        }

        return '/' . sprintf(
            self::REGION_BEGIN_TOKEN,
            $this->getMarkerTypePatternGroup(),
            $nameGroup
        ) . '/';
    }

    public function getRegionBeginReplacePattern($name)
    {
        return $this->getRegionBeginPattern($name);
    }

    public function getRegionEndPattern()
    {
        return '/' . sprintf(
            self::REGION_END_TOKEN,
            $this->getMarkerTypePatternGroup()
        ) . '/';
    }

    public function getRegionEndMarker($type)
    {
        $type = $this->sanitizeMarkerType($type);
        return sprintf(self::REGION_END_TOKEN, $type);
    }

    public function getParamDefMarker($type, $name, $paramType = 'text', $value = '')
    {
        $type = $this->sanitizeMarkerType($type);
        return sprintf(self::PARAM_DEF_TOKEN, $type, $name, $paramType, $value);
    }

    public function getParamDefPattern($nameGroup = null)
    {
        $attributePattern = $this->getMarkerAttributePatternGroup();

        if (null === $nameGroup) {
            $nameGroup = $attributePattern;
        }

        return '/' . sprintf(
            self::PARAM_DEF_TOKEN,
            $this->getMarkerTypePatternGroup(),
            $nameGroup,
            $attributePattern,
            $attributePattern
        ) . '/';
    }

    public function getParamReplacePattern($name)
    {
        return $this->getParamDefPattern($name);
    }

    public function getNestedRegionLockExpression()
    {
        return sprintf(self::PARAM_REPLACE_TOKEN, '" "');
    }

    public function getParamNeedle($name)
    {
        return array(
            sprintf(self::PARAM_REPLACE_TOKEN, $name),
            sprintf(self::PARAM_REPLACE_TOKEN_ALT, $name)
        );
    }

    public function buildElement(HTMLToken $token)
    {
        switch ($token->getType()) {
            case HTMLToken::DOCTYPE:
                $html = $token->getHtmlOrigin();
                break;
            case HTMLToken::StartTag:
                $selfClosing = $token->selfClosing() ? ' /' : '';
                $attributes = $this->buildAttributes($token);
                $beforeAttributeSpace = '';
                if ($attributes) {
                    $beforeAttributeSpace = ' ';
                }
                $html = sprintf('<%s%s%s%s>', $token->getTagName(), $beforeAttributeSpace, $attributes, $selfClosing);
                break;
            case HTMLToken::EndTag:
                $html = sprintf('</%s>', $token->getTagName());
                break;
            default:
                $html = $token->getData();
                break;
        }
        return $html;
    }

    /**
     * @param HTMLToken $token
     * @return string
     */
    public function buildAttributes(HTMLToken $token)
    {
        $attr = array();
        $format = '%s=%s%s%s';
        foreach ($token->getAttributes() as $attribute) {
            $name = $attribute['name'];
            $value = $attribute['value'];
            switch ($attribute['quoted']) {
                case HTMLToken::DoubleQuoted:
                    $quoted = '"';
                    break;
                case HTMLToken::SingleQuoted:
                    $quoted = '\'';
                    break;
                default:
                    $quoted = '';
                    break;
            }
            if ($quoted === '' && $value === '') {
                $attr[] = $name;
                continue;
            }
            $attr[] = sprintf($format, $name, $quoted, $value, $quoted);
        }
        return join(' ', $attr);
    }

    /**
     * Returns content between two strings
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     *
     * @param string $start String that bounds the start
     * @param string $end   String that bounds the end
     * @param string $html  larger body of content to search
     * @param bool $inclusive If the $start and $end bounds should be replaced
     *
     * @return string
     */
    public function strBetween($start, $end, $html, $inclusive = false)
    {
        $strposBetween = $this->strposBetween($start, $end, $html, $inclusive);

        if (!$strposBetween) {
            return false;
        }

        return substr($html, $strposBetween[0], $strposBetween[1]);
    }

    /**
     * Returns an array with offset 0 set to the position of $start
     * and offset 1 set to the length from $start to $end
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     *
     * @param string $start String that bounds the start
     * @param string $end   String that bounds the end
     * @param string $html  larger body of content to search
     * @param bool $inclusive If the $start and $end bounds should be replaced
     *
     * @return array
     */
    public function strposBetween($start, $end, $html, $inclusive = false)
    {
        $posStart = strpos($html, $start);

        if ($posStart === false) {
            return false;
        }

        if (!$inclusive) {
            $posStart += $lenStart;
        }

        $lenStart = strlen($start);
        $lenEnd = strlen($end);
        $posEnd = strpos($html, $end, $posStart);

        if ($posEnd === false) {
            return false;
        }

        if ($inclusive) {
            $posEnd += $lenEnd;
        }

        if ($posEnd <= $posStart) {
            return false;
        }

        return array($posStart, $posEnd - $posStart);
    }

    /**
     * Returns a string with the substring between $start and $end replaced with
     * the passed $replacement.
     *
     * @SuppressWarnings(PHPMD.BooleanArgumentFlag)
     *
     * @param string $start String that bounds the start
     * @param string $end   String that bounds the end
     * @param string $replacement The replacement string
     * @param string $html  the input string
     * @param bool $inclusive If the $start and $end bounds should be replaced
     *
     * @return string
     */
    public function strReplaceBetween($start, $end, $replacement, $html, $inclusive = false, &$count = 0)
    {
        $count = 0;
        $strposBetween = $this->strposBetween($start, $end, $html, $inclusive);

        if (!$strposBetween) {
            return $html;
        }

        $count = 1;
        return substr_replace($html, $replacement, $strposBetween[0], $strposBetween[1]);
    }
}
