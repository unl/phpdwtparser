<?php
/**
 * The generator parses actual .dwt Dreamweaver Template files to create object relationship
 * files which have member variables for editable regions within the dreamweaver templates.
 *
 * @category  Templates
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
 * @link      https://github.com/unl/phpdwtparser
 */

class UNL_DWT_Generator extends UNL_DWT
{
    /**
     * Array of template names.
     */
    protected $templates;

    /**
     * Current template being output
     */
    protected $template;

    /**
     * Assoc array of template region names.
     * $regions[$template] = array();
     */
    protected $regions;

    /**
     * Assoc array of template params
     * $params[$template] = array();
     */
    protected $params;

    /**
     * class being extended (can be overridden by
     * [UNL_DWT_Generator] extends=xxxx
     *
     * @var    string
     */
    protected $extends = 'UNL_DWT';

    /**
     * begins generation of template files
     *
     * @return void
     */
    public function start()
    {
        $this->debugLevel(3);
        $this->createTemplateList();
        $this->generateTemplates();
        $this->generateClasses();
    }

    /**
     * Generates .tpl files from .dwt
     *
     * @return void
     */
    public function generateTemplates()
    {
        $dwt_location = UNL_DWT::$options['dwt_location'];
        $tpl_location = UNL_DWT::$options['tpl_location'];

        if (!file_exists($dwt_location)) {
            mkdir($dwt_location, 0777, true);
        }

        if (!file_exists($tpl_location)) {
            mkdir($tpl_location, 0777, true);
        }

        foreach ($this->templates as $this->template) {
            $dwt = file_get_contents($dwt_location . $this->template);
            $dwt = $this->scanRegions($dwt);

            $sanitizedName = $this->sanitizeTemplateName($this->template);
            $outfilename = "{$tpl_location}/{$sanitizedName}.tpl";

            $this->debug("Writing {$sanitizedName} to {$outfilename}", 'generateTemplates');
            file_put_contents($outfilename, $dwt);
        }
    }

    /**
     * Create a list of dwts
     *
     * @return void
     */
    public function createTemplateList()
    {
        $this->templates = array();

        $dwt_location = UNL_DWT::$options['dwt_location'];

        if (is_dir($dwt_location)) {
            $handle = opendir($dwt_location);

            while (false !== ($file = readdir($handle))) {
                if (isset(UNL_DWT::$options['generator_include_regex']) &&
                    !preg_match(UNL_DWT::$options['generator_include_regex'], $file)
                ) {
                    continue;
                } elseif (isset(UNL_DWT::$options['generator_exclude_regex']) &&
                    preg_match(UNL_DWT::$options['generator_exclude_regex'], $file)
                ) {
                    continue;
                }

                if (substr($file, strlen($file) - 4) === '.dwt') {
                    $this->debug("Adding {$file} to the list of templates.", 'createTemplateList');
                    $this->templates[] = $file;
                }
            }
        } else {
            throw new UNL_DWT_Exception("dwt_location is incorrect");
        }
    }

    /**
     * Generate the classes for templates in $this->templates
     *
     * @return void
     */
    public function generateClasses()
    {
        if (isset(UNL_DWT::$options['extends'])) {
            $this->extends = UNL_DWT::$options['extends'];
        }

        foreach ($this->templates as $this->template) {
            $this->classname = $this->generateClassName($this->template);
            $outfilename = UNL_DWT::$options['class_location'] . "/" .
                $this->sanitizeTemplateName($this->template) . ".php";
            $oldcontents = '';

            if (file_exists($outfilename)) {
                // file_get_contents???
                $oldcontents = file_get_contents($outfilename);
            }

            $out = $this->generateClassTemplate($oldcontents);

            $this->debug("Writing {$this->classname} to {$outfilename}", 'generateClasses');
            file_put_contents($outfilename, $out);
        }
    }

    /**
     * Generates the class name from a filename.
     *
     * @param string $filename The filename of the template.
     *
     * @return string Sanitized filename prefixed with the class_prefix
     *                defined in the ini.
     */
    public function generateClassName($filename)
    {
        $class_prefix = '';
        if (isset(UNL_DWT::$options['class_prefix'])) {
            $class_prefix = UNL_DWT::$options['class_prefix'];
        }

        return $class_prefix . $this->sanitizeTemplateName($filename);
    }

    /**
     * Cleans the template filename.
     *
     * @param string $filename Filename of the template
     *
     * @return string Sanitized template name
     */
    public function sanitizeTemplateName($filename)
    {
        return preg_replace('/[^A-Z0-9]/i', '_', ucfirst(str_replace('.dwt', '', $filename)));
    }

    /**
     * Scans the .dwt for regions - all found are loaded into assoc array
     * $this->regions[$template].
     *
     * @param string $dwt Dreamweaver template file to scan.
     *
     * @return string derived template file.
     */
    public function scanRegions($dwt)
    {
        $this->regions[$this->template] = array();
        $this->params[$this->template] = array();

        $dwt = str_replace("\r", "\n", $dwt);
        $dwt = preg_replace("/(\<\!-- InstanceBeginEditable name=\"([A-Za-z0-9]*)\" -->)/i", "\n\\0\n", $dwt);
        $dwt = preg_replace("/(\<\!-- TemplateBeginEditable name=\"([A-Za-z0-9]*)\" -->)/i", "\n\\0\n", $dwt);
        $dwt = preg_replace("/\<\!-- InstanceEndEditable -->/", "\n\\0\n", $dwt);
        $dwt = preg_replace("/\<\!-- TemplateEndEditable -->/", "\n\\0\n", $dwt);
        $dwt = explode("\n", $dwt);

        $newRegion = false;
        $region    = new UNL_DWT_Region();
        $this->debug("Checking {$this->template}", 'scanRegions', 0);

        foreach ($dwt as $key => $fileregion) {
            $matches = array();
            if (preg_match("/\<\!-- InstanceBeginEditable name=\"([A-Za-z0-9]*)\" -->/i", $fileregion, $matches) ||
                preg_match("/\<\!-- TemplateBeginEditable name=\"([A-Za-z0-9]*)\" -->/i", $fileregion, $matches)
            ) {
                if ($newRegion == true) {
                    // Found a new nested region.
                    // Remove the previous one.
                    $dwt[$region->line] = str_replace(
                        "<!--"." InstanceBeginEditable name=\"{$region->name}\" -->",
                        '',
                        $dwt[$region->line]
                    );
                }
                $newRegion     = true;
                $region        = new UNL_DWT_Region();
                $region->name  = $matches[1];
                $region->line  = $key;
                $region->value = "";
            } elseif (preg_match("/\<\!-- InstanceEndEditable -->/i", $fileregion, $matches) ||
                preg_match("/\<\!-- TemplateEndEditable -->/", $fileregion, $matches)
            ) {
                // Region is closing.
                if ($newRegion===true) {
                    $region->value = trim($region->value);
                    if (strpos($region->value, "@@(\" \")@@") === false) {
                        $this->regions[$this->template][] = $region;
                    } else {
                        // Editable Region tags must be removed within .tpl
                        unset($dwt[$region->line], $dwt[$key]);
                    }
                    $newRegion = false;
                } else {
                    // Remove the nested region closing tag.
                    $dwt[$key] = str_replace("<!--"." InstanceEndEditable -->", '', $fileregion);
                }
            } else {
                if ($newRegion===true) {
                    // Add the value of this region.
                    $region->value .= trim($fileregion)." ";
                }
            }
        }
        $dwt = implode("\n", $dwt);

        preg_match_all(
            "/<!-- (?:Instance|Template)Param name=\"([^\"]*)\" type=\"([^\"]*)\" value=\"([^\"]*)\" -->/",
            $dwt,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            if (!empty($match[1])) {
                $this->params[$this->template][$match[1]] = array(
                    'name'  => $match[1],
                    'type'  => $match[2],
                    'value' => $match[3]
                );
            }
        }

        $dwt = str_replace(
            array(
                "<!--"." TemplateBeginEditable ",
                "<!--"." TemplateEndEditable -->",
                "<!-- TemplateParam ",
                "\n\n"
            ),
            array(
                "<!--"." InstanceBeginEditable ",
                "<!--"." InstanceEndEditable -->",
                "<!-- InstanceParam ",
                "\n"
            ),
            $dwt
        );

        $instanceBeginPattern = "<!-- InstanceBegin template=\"([\/\w\d\.]+)\" codeOutsideHTMLIsLocked=\"([\w]+)\" -->";
        if (preg_match($instanceBeginPattern, $dwt)) {
            $dwt = preg_replace(
                $instanceBeginPattern,
                "<!--"." InstanceBegin template=\"/Templates/{$this->template}\" codeOutsideHTMLIsLocked=\"\\2\" -->",
                $dwt
            );
        } else {
            $pos = strpos($dwt, ">", strripos($dwt, "<html") + 5) + 1;
            $dwt = substr($dwt, 0, $pos) .
                "<!-- InstanceBegin template=\"/Templates/{$this->template}\" codeOutsideHTMLIsLocked=\"false\" -->" .
                substr($dwt, $pos);
        }
        $dwt = str_replace('@@(" ")@@', '', $dwt);
        return $dwt;
    }

    /**
     * The template class geneation part - single file.
     *
     * @param string $input file to generate a class for.
     *
     * @return  updated .php file
     */
    protected function generateClassTemplate($input = '')
    {

        $foot = "";
        $head = "<?php\n/**\n * Template Definition for {$this->template}\n*/\n";
        $head .= "class {$this->classname} extends {$this->extends} \n{";

        $body  =  "\n    ###START_AUTOCODE\n";
        $body .= "    /* the code below is auto generated do not remove the above tag */\n\n";

        $var   = (substr(phpversion(), 0, 1) > 4) ? 'public' : 'var';
        $body .= "    {$var} \$__template = '".$this->sanitizeTemplateName($this->template).".tpl'; // template name\n";

        $regions = $this->regions[$this->template];

        foreach ($regions as $t) {
            if (!strlen(trim($t->name))) {
                continue;
            }

            $body .="    {$var} \${$t->name} = \"".addslashes($t->value)."\"; // {$t->type}({$t->len}){$t->flags}\n";
        }

        $body .= "\n";
        $body .= "    {$var} \$_params = " . var_export($this->params[$this->template], true) . ";\n";

        // generate getter and setter methods
        $body .= $this->generateGetters($input);
        $body .= $this->generateSetters($input);

        $body .= "\n    /* the code above is auto generated do not remove the tag below */";
        $body .= "\n    ###END_AUTOCODE\n";

        $foot .= "}\n";
        $full  = $head . $body . $foot;

        if (!$input) {
            return $full;
        }
        if (!preg_match('/(\n|\r\n)\s*###START_AUTOCODE(\n|\r\n)/s', $input)) {
            return $full;
        }
        if (!preg_match('/(\n|\r\n)\s*###END_AUTOCODE(\n|\r\n)/s', $input)) {
            return $full;
        }

        $class_rewrite = 'UNL_DWT';
        if (!($class_rewrite = @UNL_DWT::$options['generator_class_rewrite'])) {
            $class_rewrite = 'UNL_DWT';
        }
        if ($class_rewrite == 'ANY') {
            $class_rewrite = '[a-z_]+';
        }

        $input = preg_replace(
            '/(\n|\r\n)class\s*[a-z0-9_]+\s*extends\s*' .$class_rewrite . '\s*\{(\n|\r\n)/si',
            "\nclass {$this->classname} extends {$this->extends} \n{\n",
            $input
        );

        return preg_replace(
            '/(\n|\r\n)\s*###START_AUTOCODE(\n|\r\n).*(\n|\r\n)\s*###END_AUTOCODE(\n|\r\n)/s',
            $body,
            $input
        );

    }

    /**
    * Generate getter methods for class definition
    *
    * @param string $input Existing class contents
    *
    * @return string
    */
    protected function generateGetters($input)
    {
        $getters = '';

        // only generate if option is set to true
        if (empty(UNL_DWT::$options['generate_getters'])) {
            return '';
        }

        // remove auto-generated code from input to be able to check if the method exists outside of the auto-code
        $input = preg_replace(
            '/(\n|\r\n)\s*###START_AUTOCODE(\n|\r\n).*(\n|\r\n)\s*###END_AUTOCODE(\n|\r\n)/s',
            '',
            $input
        );

        $getters .= "\n\n";
        $regions = $this->regions[$this->table];

        // loop through properties and create getter methods
        foreach ($regions as $t) {
            // build mehtod name
            $methodName = 'get' . ucfirst($t->name);

            if (!strlen(trim($t->name)) || preg_match("/function[\s]+[&]?$methodName\(/i", $input)) {
                continue;
            }

            $getters .= "   /**\n";
            $getters .= "    * Getter for \${$t->name}\n";
            $getters .= "    *\n";
            $getters .= (stristr($t->flags, 'multiple_key')) ? "    * @return   object\n"
                                                             : "    * @return   {$t->type}\n";
            $getters .= "    */\n";
            $getters .= "    public function $methodName() {\n";
            $getters .= "        return \$this->{$t->name};\n";
            $getters .= "    }\n\n";
        }

        return $getters;
    }

    /**
     * Generate setter methods for class definition
     *
     * @param string $input Existing class contents
     *
     * @return string
     */
    protected function generateSetters($input)
    {
        $setters = '';

        // only generate if option is set to true
        if (empty(UNL_DWT::$options['generate_setters'])) {
            return '';
        }

        // remove auto-generated code from input to be able to check if the method exists outside of the auto-code
        $input = preg_replace(
            '/(\n|\r\n)\s*###START_AUTOCODE(\n|\r\n).*(\n|\r\n)\s*###END_AUTOCODE(\n|\r\n)/s',
            '',
            $input
        );

        $setters .= "\n";
        $regions  = $this->regions[$this->table];

        // loop through properties and create setter methods
        foreach ($regions = $regions as $t) {
            // build mehtod name
            $methodName = 'set' . ucfirst($t->name);

            if (!strlen(trim($t->name)) || preg_match("/function[\s]+[&]?$methodName\(/i", $input)) {
                continue;
            }

            $setters .= "   /**\n";
            $setters .= "    * Setter for \${$t->name}\n";
            $setters .= "    *\n";
            $setters .= "    * @param    mixed   input value\n";
            $setters .= "    */\n";
            $setters .= "    public function $methodName(\$value) {\n";
            $setters .= "        \$this->{$t->name} = \$value;\n";
            $setters .= "    }\n\n";
        }

        return $setters;
    }
}
