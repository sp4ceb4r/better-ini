<?php

require_once __DIR__ . '/../vendor/autoload.php';

use BetterIni\Ini;


/**
 * Class IniTest
 */
class IniTest extends PHPUnit_Framework_TestCase
{
    public function test_parse_quotes()
    {
        $config = new Ini(__DIR__.'/../resources/quotes.ini');

        $this->assertEquals('quoted"text', $config->get('escaped.doubles'));
        $this->assertEquals("quoted'text", $config->get('escaped.singles'));
        $this->assertEquals('quoted text', $config->get('single.quotes'));
        $this->assertEquals('quoted text', $config->get('double.quotes'));
        $this->assertEquals('quoted text', $config->get('no.quotes'));
        $this->assertEquals(['quoted', 'text'], explode("\n", $config->get('multiline')));
    }

    public function test_parse_types()
    {
        $config = new Ini(__DIR__.'/../resources/types.ini');

        $this->assertTrue(is_int($config->get('integer')));
        $this->assertTrue(is_string($config->get('string1')));
        $this->assertTrue(is_string($config->get('string2')));
        $this->assertTrue(is_string($config->get('string3')));
        $this->assertTrue(is_bool($config->get('boolean.true')));
        $this->assertTrue(is_bool($config->get('boolean.false')));
        $this->assertTrue(is_float($config->get('float')));
        $this->assertTrue(is_string($config->get('string.numeric')));
        $this->assertTrue(is_string($config->get('string.boolean')));

        $arr = $config->get('array');
        $this->assertTrue(is_int($arr[0]));
        $this->assertTrue(is_string($arr[1]));
        $this->assertTrue(is_float($arr[2]));
    }

    public function test_parse_with_nesting()
    {
        $expected = [
            'key' => 'value',
            'section' => [
                'key' => 'value',
                'subsection' => [
                    'key' => 'value',
                ],
            ],
            'section2' => [
                'key' => 'value',
            ],
        ];

        $config = new Ini(__DIR__.'/../resources/nested.ini');
        $this->assertEquals($expected, $config->get());
    }

    public function test_get_dot_expansion()
    {
        $config = new Ini(__DIR__.'/../resources/nested.ini');
        $this->assertEquals('value', $config->get('key'));
        $this->assertEquals('value', $config->get('section.key'));
        $this->assertEquals('value', $config->get('section.subsection.key'));
        $this->assertEquals('value', $config->get('section2.key'));
    }

    public function test_parse_arrays()
    {
        $config = new Ini(__DIR__.'/../resources/arrays.ini');
        $this->assertEquals([1,2,3], $config->get('array'));
        $this->assertEquals([1,2,3], $config->get('section.array'));
        $this->assertEquals(['abc'], $config->get('section.sub.array'));
        $this->assertEquals('b', $config->get('section2.array'));
        $this->assertEquals([
            'array' => [
                'key1' => 'one',
                'key2' => 'two',
            ],
        ], $config->get('assoc'));
        $this->assertEquals('one', $config->get('assoc.array.key1'));
        $this->assertEquals('two', $config->get('assoc.array.key2'));
    }

    public function test_global_and_section_shared()
    {
        $config = new Ini(__DIR__.'/../resources/globalandsection.ini');

        $this->assertEquals('abc1', $config->get('section.key'));
        $this->assertEquals('def2', $config->get('section.another'));
    }

    public function test_hyphenated_sections()
    {
        $config = new Ini(__DIR__ . '/../resources/hyphens.ini');

        $this->assertCount(2, $config->get());
        $this->assertEquals(['key' => 'this'], $config->get('section'));
        $this->assertEquals(['key' => 'that'], $config->get('section-two'));
        $this->assertEquals('this', $config->get('section.key'));
        $this->assertEquals('that', $config->get('section-two.key'));
    }
}