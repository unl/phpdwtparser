<?php

namespace UNL\DWT;

class ScannerTest extends \PHPUnit_Framework_TestCase
{
    public function testGetOrders()
    {
        $scanner = $this->getExampleScanner();
        
        $regions = $scanner->getRegions();
        
        //Assert that all regions are present
        $this->assertArrayHasKey('doctitle', $regions);
        $this->assertArrayHasKey('head', $regions);
        $this->assertArrayHasKey('header', $regions);
        $this->assertArrayHasKey('leftnav', $regions);
        $this->assertArrayHasKey('content', $regions);
        $this->assertArrayHasKey('footer', $regions);
        
        //Assert the content of regions
        $this->assertEquals("\n<title>Sample Template Style 1</title>\n", $regions['doctitle']->getValue());
    }

    /**
     * @return Scanner
     */
    protected function getExampleScanner()
    {
        $file = file_get_contents(__DIR__ . '/../docs/examples/basic/template_style1.dwt');
        
        return new Scanner($file);
    }
}
