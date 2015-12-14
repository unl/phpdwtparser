<?php

namespace UNL\DWT;

class GeneratorTest extends \PHPUnit_Framework_TestCase
{
    public function testGenerator()
    {
        $example_dir = __DIR__ . '/../docs/examples/basic';
        $test_dir = __DIR__ . '/data/generator_test';
        $file_tpl = 'Template_style1.tpl';
        $file_php = 'TemplateStyle1.php';
        
        //test
        @mkdir($test_dir);

        AbstractDwt::$options = array(
            'dwt_location' => $example_dir, 
            'class_location' => $test_dir,
            'tpl_location' => $test_dir,
        );

        $generator = new Generator;
        $generator->start();
        
        $this->assertEquals(file_get_contents($example_dir.'/'.$file_tpl), file_get_contents($test_dir.'/'.$file_tpl));
        $this->assertEquals(file_get_contents($example_dir.'/'.$file_php), file_get_contents($test_dir.'/'.$file_php));
    }
}
