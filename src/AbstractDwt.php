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
 * Base class which understands Dreamweaver Templates
 */
abstract class AbstractDwt
{
    /**
     * @var string The template file name
     */
    protected $template;

    /**
     * @var Region[] Assoc array of template region names.
     */
    protected $regions = [];

    /**
     * @var Param[] Assoc array of template region names.
     */
    protected $params = [];

    /**
     * Run-time configuration options
     *
     * @var array
     */
    public static $options = array(
        'debug' => 0,
    );

    /**
     * Create a new object for the specified layout type
     *
     * @param string $type     the template type (eg "fixed")
     * @param array  $coptions an associative array of option names and values
     * @return object
     * @throws Exception\InvalidArgumentException If the given $type cannot be found
     */
    public static function factory($type)
    {
        $prefix = isset(static::$options['class_prefix']) ? static::$options['class_prefix'] : '';
        $classname = $prefix . $type;

        if (!class_exists($classname)) {
            throw new Exception\InvalidArgumentException("Unable to find the $classname class");
        }

        return new $classname;
    }

    /**
     * Sets options.
     *
     * @param string $option Option to set
     * @param mixed  $value
     */
    public static function setOption($option, $value)
    {
        static::$options[$option] = $value;
    }

    /**
     * output debugging information.
     *
     * @SuppressWarnings(PHPMD.DevelopmentCodeFragment)
     *
     * @param string $message message to output
     * @param string $logtype bold at start
     * @param string $level   output level
     */
    public static function debug($message, $logtype = 0, $level = 1)
    {
        $debugLevel = static::debugLevel();
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

        $colorize = ($logtype == 'ERROR') ? '<span style="color:red">' : '<span>';
        echo "<code>{$colorize}<strong>$class: $logtype:</strong> " .
            nl2br(htmlspecialchars($message)) .
            "</span></code><br />\n";
        flush();
    }

    /**
     * sets and returns debug level
     *
     * @param int $level
     */
    public static function debugLevel($level = null)
    {
        $previosLevel = isset(static::$options['debug']) ? static::$options['debug'] : 0;

        if (null !== $level) {
            static::$options['debug'] = $level;
        }

        return $previosLevel;
    }

    /**
     * Default DWT constructor to load state objects
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    public function __construct()
    {
        $regions = [];
        $params = [];

        foreach ($this->regions as $name => $possibleRegion) {
            if (!$possibleRegion instanceof Region) {
                $possibleRegion = Region::fromArray([
                    'name' => $name,
                    'value' => $possibleRegion
                ]);
            }

            $regions[$name] = $possibleRegion;
        }

        $this->regions = $regions;

        foreach ($this->params as $name => $possibleParam) {
            if (!$possibleParam instanceof Param) {
                $possibleParam = Param::fromArray($possibleParam);
            }

            $params[$possibleParam->getName()] = $possibleParam;
        }

        $this->params = $params;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * Returns a string that contains the template file.
     *
     * @return string
     */
    public function getTemplateFile()
    {
        $tplLocation = isset(static::$options['tpl_location']) ? static::$options['tpl_location'] : '.';
        $tplLocation = rtrim($tplLocation, DIRECTORY_SEPARATOR);

        if (!isset($this->template) || empty($tplLocation)) {
            return '';
        }

        return file_get_contents($tplLocation . DIRECTORY_SEPARATOR . $this->template);
    }

    /**
     * returns array of all the regions found
     *
     * @return Region[]
     */
    public function getRegions()
    {
        return $this->regions;
    }

    /**
     * returns the region object
     *
     * @param string $region
     * @return Region
     */
    public function getRegion($region)
    {
        if (isset($this->regions[$region])) {
            return $this->regions[$region];
        }
        return null;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function setParam($key, $value)
    {
        if (!isset($this->params[$key])) {
            return $this;
        }

        $param = $this->params[$key];
        $param->setValue($value);
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
        $html = $this->replaceRegions($html);
        $html = $this->replaceParams($html);

        return $html;
    }

    /**
     * DWT String Utilities lazy factory
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     *
     * @return StringUtils
     */
    public function getStringUtils()
    {
        return StringUtils::getInstance();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toHtml();
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
     * @throws Exception\BadMethodCallException
     */
    public function __get($regionName)
    {
        $region = $this->getRegion($regionName);

        if ($region instanceof Region) {
            return $region->getValue();
        }

        throw new Exception\BadMethodCallException('Cannot find named region requested');
    }

    /**
     * sets the value of the named region
     *
     * @param string $regionName
     * @param string $value
     * @return self
     * @throws Exception\BadMethodCallException
     */
    public function __set($regionName, $value)
    {
        $region = $this->getRegion($regionName);

        if (!$region instanceof Region) {
            throw new Exception\BadMethodCallException('Cannot find named region requested');
        }

        $region->setValue($value);
        return $this;
    }

    /**
     * Replaces region tags within a template file wth their contents.
     *
     * @param string $html
     * @return string
     */
    protected function replaceRegions($html)
    {
        static::debug('Replacing regions.', 'replaceRegions', 5);
        $stringUtils = $this->getStringUtils();
        $count = 0;
        $regions = $this->getRegions();

        foreach ($regions as $region) {
            $html = $this->replaceRegionByType($html, $region, $stringUtils::TEMPLATE_TOKEN, $count);

            if (!$count) {
                $html = $this->replaceRegionByType($html, $region, $stringUtils::INSTANCE_TOKEN, $count);
            }

            static::debug(
                $count
                ? "{$region->getName()} is replaced with {$region->getValue()}."
                : "Counld not find region {$region->getName()}!",
                $count ? 5 : 3
            );
        }

        return $html;
    }

    protected function replaceRegionByType($html, $region, $type, &$count)
    {
        $stringUtils = $this->getStringUtils();
        $startMarker = $stringUtils->getRegionBeginMarker($type, $region->getName());
        $endMarker = $stringUtils->getRegionEndMarker($type);

        return $stringUtils->strReplaceBetween(
            $startMarker,
            $endMarker,
            $startMarker . $region->getValue() . $endMarker,
            $html,
            true,
            $count
        );
    }

    protected function replaceParams($html)
    {
        static::debug('Replacing params.', 'replaceParams', 5);
        $stringUtils = $this->getStringUtils();
        $params = $this->getParams();

        foreach ($params as $param) {
            $html = preg_replace(
                $stringUtils->getParamReplacePattern($param->getName()),
                $stringUtils->getParamDefMarker('$1', $param->getName(), '$2', $param->getValue()),
                $html,
                1,
                $count
            );

            if ($count) {
                $html = str_replace($stringUtils->getParamNeedle($param->getName()), $param->getValue(), $html);
            }
        }

        return $html;
    }
}
