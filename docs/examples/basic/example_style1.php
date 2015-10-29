<?php

ini_set('display_errors',true);
error_reporting(E_ALL|E_STRICT);

require __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/Template_style1.php';

$page = UNL\DWT\AbstractDwt::factory('Template_style1');
$page->header  = "Example Using Template Style 1";
$page->leftnav = "<ul><li><a href='http://wdn.unl.edu/'>UNL WDN</a></li></ul>";
$page->content = "<p>This example demonstrates the usefulness of the DWT object generator for Dreamweaver Templates.</p>";
$page->content .= "<p>Included with the DWT package is a Dreamweaver template with 4 editable regions [template_style1.dwt]. This page is rendered using the DWT class created from that template.</p>";
$page->content .= "<p>To create classes for your Templates, create a .ini file with the location of your Dreamweaver templates (dwt's) and then run the createTemplates.php script to generate objects for each of your template files.</p>";
$page->footer  = "<a href='mailto:brett.bieber@gmail.com'>Brett Bieber</a>";
echo $page->toHtml();
