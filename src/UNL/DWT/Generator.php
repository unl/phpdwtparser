<?php
/**
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

use Zend\Code\Generator\FileGenerator;
use Zend\Code\Generator\ClassGenerator;
use Zend\Code\Generator\DocBlockGenerator;
use Zend\Code\Generator\MethodGenerator;
use Zend\Code\Generator\PropertyGenerator;
use zz\Html\HTMLNames;
use zz\Html\HTMLToken;

/**
 * The generator parses actual .dwt Dreamweaver Template files to create object relationship
 * files which have member variables for editable regions within the dreamweaver templates.
 *
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class UNL_DWT_Generator extends UNL_DWT_Scanner
{
    const DWT_FILE_SUFFIX = '.dwt';
    const PHP_FILE_SUFFIX = '.php';
    const TPL_FILE_SUFFIX = '.tpl';

    /**
     * Array of template names.
     * @var string[]
     */
    protected $templates;

    /**
     * class being extended (can be overridden by
     * [UNL_DWT_Generator] extends=xxxx
     *
     * @var string
     */
    protected $extends = 'UNL_DWT';

    protected $classPrefix = '';

    protected $classLocation;

    protected $dwtLocation;

    protected $tplLocation;

    protected $includeRegex;

    protected $excludeRegex;

    protected $generateGetters = false;

    protected $generateSetters = false;

    protected $optionsMap = array(
        'extends' => 'extends',
        'classPrefix' => 'class_prefix',
        'classLocation' => 'class_location',
        'dwtLocation' => 'dwt_location',
        'tplLocation' => 'tpl_location',
        'includeRegex' => 'generator_include_regex',
        'excludeRegex' => 'generator_exclude_regex',
        'generateGetters' => 'generate_getters',
        'generateSetters' => 'generate_setters',
    );

    protected $stateIgnoring = false;

    public function __construct($options = array())
    {
        if ($options) {
            $this->setOptions($options);
        }
    }

    public function setOptions($options)
    {
        foreach ($this->optionsMap as $var => $option) {
            if (isset($options[$option])) {
                $this->$var = $options[$option];
            }
        }
    }

    public function getOptions()
    {
        $options = array();
        foreach ($this->optionsMap as $var => $option) {
            $options[$option] = $this->$var;
        }
        return $options;
    }

    public function getTemplateFile()
    {
        $dwtLocation = rtrim($this->dwtLocation, DIRECTORY_SEPARATOR);
        return file_get_contents($dwtLocation . DIRECTORY_SEPARATOR . $this->__template);
    }

    /**
     * begins generation of template files
     *
     * @return void
     */
    public function start()
    {
        if ($options = UNL_DWT::$options) {
            $this->setOptions($options);
        }

        self::debugLevel(3);
        $this->createTemplateList();
        $this->generateTemplates();
    }

    /**
     * Generates .tpl files from .dwt
     *
     * @return void
     */
    protected function generateTemplates()
    {
        $dwtLocation = rtrim($this->dwtLocation, DIRECTORY_SEPARATOR);
        $tplLocation = rtrim($this->tplLocation, DIRECTORY_SEPARATOR);

        if (!file_exists($dwtLocation)) {
            mkdir($dwtLocation, 0777, true);
        }

        if (!file_exists($tplLocation)) {
            mkdir($tplLocation, 0777, true);
        }

        foreach ($this->templates as $template) {
            $this->__template = $template;
            $this->parse();
            $dwt = $this->toDwtInstance();
            $sanitizedName = $this->sanitizeTemplateName($template);

            $outfilename = $tplLocation . DIRECTORY_SEPARATOR . $sanitizedName . self::TPL_FILE_SUFFIX;
            self::debug("Writing {$sanitizedName} to {$outfilename}", 'generateTemplates');
            file_put_contents($outfilename, $dwt);

            $this->generateClassTemplate();
        }
    }

    /**
     * Create a list of dwts
     *
     * @return void
     */
    protected function createTemplateList()
    {
        $this->templates = array();
        $dwtLocation = rtrim($this->dwtLocation, DIRECTORY_SEPARATOR);

        if (!is_dir($dwtLocation)) {
            throw new UNL_DWT_Exception("dwt_location is incorrect");
        }

        $handle = opendir($dwtLocation);

        while (false !== ($file = readdir($handle))) {
            if (($this->includeRegex && !preg_match($this->includeRegex, $file)) ||
                ($this->excludeRegex && preg_match($this->excludeRegex, $file))
            ) {
                continue;
            }

            if (substr($file, -4) === self::DWT_FILE_SUFFIX) {
                self::debug("Adding {$file} to the list of templates.", 'createTemplateList');
                $this->templates[] = $file;
            }
        }
    }

    /**
     * Cleans the template filename.
     *
     * @param string $filename Filename of the template
     *
     * @return string Sanitized template name
     */
    protected function sanitizeTemplateName($filename)
    {
        return preg_replace('/[^A-Z0-9]/i', '_', ucfirst(str_replace(self::DWT_FILE_SUFFIX, '', $filename)));
    }

    protected function toDwtInstance()
    {
        $dwt = '';
        $this->stateIgnoring = false;

        foreach ($this->tokens as $token) {
            // todo: iterate over tokens again and dynamically create dwt content
            $type = $token->getType();

            if ($type !== HTMLToken::Comment) {
                if (!$this->stateIgnoring) {
                    $dwt .= $this->getDwtInstanceContent($token);
                }
                continue;
            }

            $comment = $token->getData();

            if ($this->isInstnaceBegin($comment) || $this->isParamDef($comment)) {
                // these markers will be automatically added at the proper tag location
                continue;
            } elseif ($regionBegin = $this->isRegionBegin($comment)) {
                $dwt .= $this->getDwtRegionBeginContent($regionBegin);
                continue;
            } elseif ($this->isRegionEnd($comment)) {
                $dwt .= $this->getDwtRegionEndContent();
                continue;
            }

            // it is a generic comment
            if (!$this->stateIgnoring) {
                $dwt .= $comment;
            }
        }

        return $dwt;
    }

    protected function getDwtInstanceContent(HTMLToken $token)
    {
        $stringUtils = $this->getStringUtils();
        $type = $token->getType();
        $tagName = $token->getTagName();
        $content = '';

        // check for content to add before an end tag
        if ($type === HTMLToken::EndTag && $tagName === HTMLNames::headTag) {
            foreach ($this->getParams() as $paramSpec) {
                $content .= $stringUtils->getParamDefMarker(
                    $stringUtils::INSTANCE_TOKEN,
                    $paramSpec['name'],
                    $paramSpec['type'],
                    $paramSpec['value']
                ) . "\n";
            }
        }

        $content .= $stringUtils->buildElement($token);

        // check for content to add before a start tag
        if ($type === HTMLToken::StartTag && $tagName === HTMLNames::htmlTag) {
            $content .= $stringUtils->getInstanceBeginMarker('/Templates/' . $this->__template);
        }

        return $content;
    }

    protected function getDwtRegionBeginContent($name)
    {
        $stringUtils = $this->getStringUtils();
        $content = '';

        if ($region = $this->getRegion($name)) {
            $this->stateIgnoring = true;
            $content .= $stringUtils->getRegionBeginMarker($stringUtils::INSTANCE_TOKEN, $region->name);
            $content .= str_replace($stringUtils->getNestedRegionLockExpression(), '', $region);
        }

        return $content;
    }

    protected function getDwtRegionEndContent()
    {
        $stringUtils = $this->getStringUtils();
        $content = '';

        if ($this->stateIgnoring) {
            $this->stateIgnoring = false;
            $content .= $stringUtils->getRegionEndMarker($stringUtils::INSTANCE_TOKEN);
        }

        return $content;
    }

    /**
     * The template class geneation part - single file.
     *
     * @SuppressWarnings(PHPMD.StaticAccess)
     */
    protected function generateClassTemplate()
    {
        $sanitizedName = $this->sanitizeTemplateName($this->__template);
        $className = $this->classPrefix . $sanitizedName;
        $classLocation = rtrim($this->classLocation, DIRECTORY_SEPARATOR);
        $inputFile = $classLocation . DIRECTORY_SEPARATOR . $sanitizedName . self::PHP_FILE_SUFFIX;
        $fileGenerator = new FileGenerator();

        // todo: Add support for namespaces and uses

        if (file_exists($inputFile)) {
            $fileGenerator = FileGenerator::fromReflectedFileName($inputFile);
        }

        $fileGenerator->setFilename($inputFile);
        $fileGenerator->setDocBlock(DocBlockGenerator::fromArray([
            'shortDescription' => 'AUTO-GENERATED FILE',
        ]));
        $fileGenerator->setBody('');

        $classGenerator = $fileGenerator->getClass();

        if (!$classGenerator) {
            $classGenerator = ClassGenerator::fromArray([
                'containingFile' => $fileGenerator,
                'name' => $className,
            ]);
            $fileGenerator->setClass($classGenerator);
        }

        $classGenerator->setExtendedClass($this->extends);
        $classGenerator->setDocBlock(DocBlockGenerator::fromArray([
            'shortDescription' => 'Template Definition for ' . $this->__template,
            'longDescription' => 'This class is an auto-generated class. Do not manually edit.',
        ]));
        $classGenerator->setSourceDirty();

        $this->generateClassTemplateMembers($classGenerator);

        self::debug("Writing {$className} to {$inputFile}", 'generateClassTemplate');
        $fileGenerator->write();
    }

    protected function generateClassTemplateMembers(ClassGenerator $classGenerator)
    {
        $sanitizedName = $this->sanitizeTemplateName($this->__template);
        $standardProperties = [
            '__template' => $sanitizedName . self::TPL_FILE_SUFFIX,
            '__params' => $this->getParams(),
        ];

        foreach ($standardProperties as $propertyName => $propertyValue) {
            $property = $classGenerator->getProperty($propertyName);

            if (!$property) {
                $property = new PropertyGenerator($propertyName);
                $classGenerator->addPropertyFromGenerator($property);
            }

            $property->setDefaultValue($propertyValue);
            $property->setSourceDirty();
        }

        $this->generateClassRegionMembers($classGenerator);
    }

    /**
    * Generate getter methods for class definition
    *
    * @param ClassGenerator $classGenerator
    */
    protected function generateClassRegionMembers(ClassGenerator $classGenerator)
    {
        foreach ($this->getRegions() as $region) {
            $regionProperty = $classGenerator->getProperty($region->name);

            if (!$regionProperty) {
                $regionProperty = new PropertyGenerator($region->name);
                $classGenerator->addPropertyFromGenerator($regionProperty);
            }

            $regionProperty->setDefaultValue($region->value);
            $regionProperty->setSourceDirty();

            if ($this->generateGetters) {
                $methodName = 'get' . ucfirst($region->name);

                if (!$classGenerator->hasMethod($methodName)) {
                    $classGenerator->addMethod(
                        $methodName,
                        [],
                        MethodGenerator::FLAG_PUBLIC,
                        'return $this->' . $region->name . ';'
                    );
                }
            }

            if ($this->generateSetters) {
                $methodName = 'set' . ucfirst($region->name);

                if (!$classGenerator->hasMethod($methodName)) {
                    $classGenerator->addMethod(
                        $methodName,
                        ['value'],
                        MethodGenerator::FLAG_PUBLIC,
                        '$this->' . $region->name . ' = $value;' . "\n" . 'return $this;'
                    );
                }
            }
        }
    }
}
