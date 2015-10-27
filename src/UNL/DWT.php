<?php
/**
 * Base class which understands Dreamweaver Templates.
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

class UNL_DWT
{
    public $__template;
    public $__params = array();

    /**
     * Run-time configuration options
     *
     * @var array
     * @see UNL_DWT::setOption()
     */
    public static $options = array(
        'debug' => 0,
    );

    /**
     * Returns a string that contains the template file.
     *
     * @return string
     */
    public function getTemplateFile()
    {
        if (!isset($this->__template) || empty(self::$options['tpl_location'])) {
            return '';
        }

        return file_get_contents(self::$options['tpl_location'].$this->__template);
    }

    public function getRegions()
    {
        $regions = get_object_vars($this);
        foreach (array_keys($regions) as $key) {
            if (strpos($key, '__') === 0) {
                unset($regions[$key]);
            }
        }

        return $regions;
    }

    public function getParams()
    {
        return $this->__params;
    }

    public function setParam($key, $value)
    {
        if (!isset($$this->__params[$key])) {
            return $this;
        }

        $this->__params[$key]['value'] = $value;

        return $this;
    }

    /**
     * Returns the given DWT with all regions replaced with their assigned
     * content.
     *
     * @return string
     */
    public function toHtml()
    {
        $html = $this->getTemplateFile();
        $regions = $this->getRegions();
        $params = $this->getParams();

        $html = $this->replaceRegions($html, $regions);
        $html = $this->replaceParams($html, $params);

        return $html;
    }

    /**
     * DWT String Utilities lazy factory
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return UNL_DWT_StringUtils
     */
    public function getStringUtils()
    {
        return UNL_DWT_StringUtils::getInstance();
    }

    /**
     * @see $this->toHtml
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
    }

    /**
     * Replaces region tags within a template file wth their contents.
     *
     * @param string $html    Page with DW Region tags.
     * @param array  $regions Associative array with content to replace.
     *
     * @return string page with replaced regions
     */
    public function replaceRegions($html, $regions)
    {
        self::debug('Replacing regions.', 'replaceRegions', 5);
        $stringUtils = $this->getStringUtils();
        $count = 0;

        // Replace the region with the replacement text
        foreach ($regions as $region => $value) {
            $html = $this->replaceRegionByType($html, $region, $value, $stringUtils::TEMPLATE_TOKEN, $count);

            if (!$count) {
                $html = $this->replaceRegionByType($html, $region, $value, $stringUtils::INSTANCE_TOKEN, $count);
            }

            self::debug(
                $count ? "$region is replaced with $value." : "Counld not find region $region!",
                $count ? 5 : 3
            );
        }

        return $html;
    }

    protected function replaceRegionByType($html, $region, $content, $type, &$count)
    {
        $stringUtils = $this->getStringUtils();
        $startMarker = $stringUtils->getRegionBeginMarker($type, $region);
        $endMarker = $stringUtils->getRegionEndMarker($type);

        return $stringUtils->strReplaceBetween(
            $startMarker,
            $endMarker,
            $startMarker . $content . $endMarker,
            $html,
            true,
            $count
        );
    }

    public function replaceParams($html, $params)
    {
        self::debug('Replacing params.', 'replaceRegions', 5);
        $stringUtils = $this->getStringUtils();

        foreach ($params as $name => $config) {
            $value = isset($config['value']) ? $config['value'] : '';
            $html = preg_replace(
                $stringUtils->getParamReplacePattern($name),
                $stringUtils->getParamDefMarker('$1', $name, '$2', $value),
                $html,
                1,
                $count
            );

            if ($count) {
                $html = str_replace($stringUtils->getParamNeedle($name), $value, $html);
            }
        }

        return $html;
    }

    /**
     * Create a new UNL_DWT object for the specified layout type
     *
     * @param string $type     the template type (eg "fixed")
     * @param array  $coptions an associative array of option names and values
     *
     * @return object  a new UNL_DWT.  A UNL_DWT_Error object on failure.
     *
     * @see UNL_DWT::setOption()
     */
    public static function factory($type)
    {
        $prefix = isset(self::$options['class_prefix']) ? self::$options['class_prefix'] : '';
        $classname = $prefix . $type;

        if (!class_exists($classname)) {
            throw new UNL_DWT_Exception("Unable to find the $classname class");
        }

        return new $classname;
    }

    /**
     * Sets options.
     *
     * @param string $option Option to set
     * @param mixed  $value
     *
     * @return void
     */
    public static function setOption($option, $value)
    {
        self::$options[$option] = $value;
    }

    /* ----------------------- Debugger ------------------ */

    /**
     * Debugger. - output debugging information.
     *
     * Uses UNL_DWT::debugLevel(x) to turn it on
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     *
     * @param string $message message to output
     * @param string $logtype bold at start
     * @param string $level   output level
     *
     * @return   none
     */
    public static function debug($message, $logtype = 0, $level = 1)
    {
        $debugLevel = self::debugLevel();
        $isDebugCallable = is_callable($debugLevel);

        if (empty($debugLevel) || !$isDebugCallable && $debugLevel < $level) {
            return;
        }

        $class = get_called_class();

        if (!is_string($message)) {
            $message = print_r($message, true);
        }

        if ($isDebugCallable) {
            return call_user_func($debugLevel, $class, $message, $logtype, $level);
        }

        if (!ini_get('html_errors')) {
            echo "$class : $logtype : $message\n";
            flush();
            return;
        }

        $colorize = ($logtype == 'ERROR') ? '<font color="red">' : '<font>';
        echo "<code>{$colorize}<strong>$class: $logtype:</strong> " .
            nl2br(htmlspecialchars($message)) .
            "</font></code><br />\n";
        flush();
    }

    /**
     * sets and returns debug level
     * eg. UNL_DWT::debugLevel(4);
     *
     * @param int $level
     *
     * @return void
     */
    public static function debugLevel($level = null)
    {
        $previosLevel = isset(self::$options['debug']) ? self::$options['debug'] : 0;

        if (null !== $level) {
            self::$options['debug'] = $level;
        }

        return $previosLevel;
    }
}
