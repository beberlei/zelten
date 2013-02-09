<?php

namespace Zelten\Tests\Config;

use Zelten\Config\YamlConfigLoader;

class YamlConfigLoaderTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $default = <<<DEF
params: 1
param2: ~
DEF;

        $values = <<<VAL
params: 2
param2: 2
VAL;

        $loader = new YamlConfigLoader($default, $values);
        $config = $loader->create();

        $this->assertEquals(2, $config->get('params'));
        $this->assertEquals(2, $config->get('param2'));
    }

    public function testCreateNullDefaultsAreMissing()
    {
        $default = "params: ~";

        $loader = new YamlConfigLoader($default, "param2: ~");
        $config = $loader->create();

        $this->assertNull($config->get('params'));
        $this->assertEquals(array('params'), $config->getUnconfiguredVariables());
    }
}
