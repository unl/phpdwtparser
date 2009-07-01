<?php
ini_set('display_errors',true);
require_once 'PEAR/PackageFileManager2.php';
require_once 'PEAR/PackageFileManager/File.php';
require_once 'PEAR/Task/Postinstallscript/rw.php';
require_once 'PEAR/Config.php';
require_once 'PEAR/Frontend.php';

/**
 * @var PEAR_PackageFileManager
 */
PEAR::setErrorHandling(PEAR_ERROR_DIE);
chdir(dirname(__FILE__));
$pfm = PEAR_PackageFileManager2::importOptions('package.xml', array(
//$pfm = new PEAR_PackageFileManager2();
//$pfm->setOptions(array(
	'packagedirectory' => dirname(__FILE__),
	'baseinstalldir' => '/',
	'filelistgenerator' => 'file',
	'ignore' => array(	'package.xml',
						'package2.xml',
						'.project',
						'*.tgz',
						'makepackage.php',
						'*CVS/*',
						'.cache',
                        'example.test.ini',
						'*/_notes/*'),
	'simpleoutput' => true,
	'roles'=>array('php'=>'php'	),
	'exceptions'=>array()
));
$pfm->setPackage('UNL_DWT');
$pfm->setPackageType('php'); // this is a PEAR-style php script package
$pfm->setSummary('This package generates php class files (objects) from Dreamweaver template files.');
$pfm->setDescription('This package generates php class files (objects) from Dreamweaver template files.');
$pfm->setChannel('pear.unl.edu');
$pfm->setAPIStability('beta');
$pfm->setReleaseStability('beta');
$pfm->setAPIVersion('0.7.1');
$pfm->setReleaseVersion('0.7.1');
$pfm->setNotes('
Declare debug method correctly as static.
');

//$pfm->addMaintainer('lead','saltybeagle','Brett Bieber','brett.bieber@gmail.com');
$pfm->setLicense('BSD', 'http://www1.unl.edu/wdn/wiki/Software_License');
$pfm->clearCompatible();
$pfm->clearDeps();
$pfm->addConflictingPackageDepWithChannel('UNL_Templates', 'pear.unl.edu', false, false, '0.5.2');
$pfm->setPhpDep('5.0.0');
$pfm->setPearinstallerDep('1.4.3');
foreach (array('UNL/DWT.php','docs/examples/example.ini','docs/examples/example_style1.php') as $file) {
	$pfm->addReplacement($file, 'pear-config', '@PHP_BIN@', 'php_bin');
	$pfm->addReplacement($file, 'pear-config', '@PHP_DIR@', 'php_dir');
	$pfm->addReplacement($file, 'pear-config', '@DATA_DIR@', 'data_dir');
	$pfm->addReplacement($file, 'pear-config', '@DOC_DIR@', 'doc_dir');
}

$pfm->generateContents();
if (isset($_SERVER['argv']) && $_SERVER['argv'][1] == 'make') {
    $pfm->writePackageFile();
} else {
    $pfm->debugPackageFile();
}
?>