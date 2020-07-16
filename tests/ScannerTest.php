<?php

namespace UNLTest\DWT;

use UNL\DWT\Scanner;

class ScannerTest extends \PHPUnit\Framework\TestCase
{
    public function testRegions()
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
        $this->assertFalse(isset($scanner->foobar));
        $this->assertNull($scanner->getRegion('foobar'));

        //Assert the content of regions
        $this->assertEquals("\n<title>Sample Template Style 1</title>\n", $regions['doctitle']->getValue());
    }

    public function testParams()
    {
        $scanner = $this->getExampleScanner();

        $params = $scanner->getParams();

        //Assert that all regions are present
        $this->assertArrayHasKey('class', $params);

        //Assert the content, type, etc of params
        $this->assertEquals("text", $params['class']->getType());
        $this->assertEquals("class", $params['class']->getName());
        $this->assertEquals("test", $params['class']->getValue());

        $scanner->setParam('class', 'other-test');
        $this->assertEquals('other-test', $params['class']->getValue());
    }

    public function testToHtml()
    {
        $scanner = $this->getExampleScanner();

        $expected = file_get_contents(__DIR__ . '/data/expected_output.html');

        // Modify the scanned content
        $scanner->content .= '<h3>Scanned content from the left nav:</h3>';

        // Also, access the content that was scanned in
        $scanner->content .= '<pre>'.$scanner->leftnav.'</pre>';

        $this->assertEquals($expected, (string) $scanner);
    }

    /**
     * @return Scanner
     */
    protected function getExampleScanner()
    {
        $file = file_get_contents(__DIR__ . '/../docs/examples/basic/template_style1.dwt');
        Scanner::debugLevel(0);

        return new Scanner($file);
    }
}
