<?php

namespace UNLTest\DWT;

use UNL\DWT\AbstractDwt;
use UNL\DWT\Generator;

class GeneratorTest extends \PHPUnit\Framework\TestCase
{
    protected function getTestDir()
    {
        return __DIR__ . '/data/generator_test';
    }

    public function tearDown(): void
    {
        $this->deleteDirectory($this->getTestDir());
    }

    protected function deleteDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public function testGenerator()
    {
        $example_dir = __DIR__ . '/../docs/examples/basic';
        $test_dir = $this->getTestDir();
        $file_tpl = 'Template_style1.tpl';
        $file_php = 'TemplateStyle1.php';

        AbstractDwt::$options = [
            'dwt_location' => $example_dir,
            'class_location' => $test_dir,
            'tpl_location' => $test_dir,
        ];

        $generator = new Generator;
        ob_start();
        $generator->start();
        ob_end_clean();

        $this->assertEquals(file_get_contents($example_dir.'/'.$file_tpl), file_get_contents($test_dir.'/'.$file_tpl));
        $this->assertEquals(file_get_contents($example_dir.'/'.$file_php), file_get_contents($test_dir.'/'.$file_php));
    }
}
