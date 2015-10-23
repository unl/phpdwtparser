#!/usr/bin/php -q
<?php
/**
 * Tool to generate objects for dreamweaver template files.
 *
 * PHP version 5
 *
 * @package   UNL_DWT
 * @author    Brett Bieber <brett.bieber@gmail.com>
 * @copyright 2015 Regents of the University of Nebraska
 * @license   http://wdn.unl.edu/software-license BSD License
  */

// since this version doesnt use overload,
// and I assume anyone using custom generators should add this..
define('UNL_DWT_NO_OVERLOAD',1);
ini_set('display_errors',true);

require_once __DIR__ . '/../vendor/autoload.php';

if (!@$_SERVER['argv'][1]) {
    throw new Exception("\nERROR: createTemplates.php usage: 'php createTemplates.php example.ini'\n\n");
}

$config = parse_ini_file($_SERVER['argv'][1], true);
foreach($config as $class => $values) {
    if ($class === 'UNL_DWT') {
        UNL_DWT::$options = $values;
    }
}

if (empty(UNL_DWT::$options)) {
    throw new Exception("\nERROR: could not read ini file\n\n");
}

set_time_limit(0);

$generator = new UNL_DWT_Generator;
$generator->start();
