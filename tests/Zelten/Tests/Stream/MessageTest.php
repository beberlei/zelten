<?php

namespace Zelten\Tests\Stream;

use Zelten\Tests\TestCase;
use Kwi\UrlLinker;

class MessageTest extends TestCase
{
    /**
     * @group GH-62
     */
    public function testUrlLinker()
    {
        $linker = new UrlLinker();
        $response = $linker->parse('http://f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png');
        var_dump($response);
    }
}

