<?php

namespace Zelten\Tests\Config;

use Zelten\Config\Config;

class ConfigTest extends \PHPUnit_Framework_TestCase
{
    public function testGetUnknownVariableThrowsException()
    {
        $config = new Config();

        $this->setExpectedException('Zelten\Config\ConfigException');

        $config->get('unknown');
    }

    public function testGetVariableDefault()
    {
        $config = new Config(array('variable' => 1));

        $this->assertEquals(1, $config->get('variable'), "Variable should return its default value 1.");
    }

    public function testGetLoadedVariableValue()
    {
        $config = new Config(array('variable' => 1), array('variable' => 2));

        $this->assertEquals(2, $config->get('variable'), "Variable should return its loaded value 2.");
    }

    public function testGetUnconfiguredVariables()
    {
        $config = new Config(array('variable' => null));

        $this->assertEquals(array('variable'), $config->getUnconfiguredVariables());
    }

    public function testVariableRequiresDefaultToBeKnown()
    {
        $config = new Config(array(), array('variable' => 2));

        $this->setExpectedException('Zelten\Config\ConfigException');

        $config->get('variable');
    }
}
