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
}
