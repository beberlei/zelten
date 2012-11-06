<?php

namespace Zelten\Tests\Stream;

use Zelten\Tests\TestCase;
use Kwi\UrlLinker;

class MessageTest extends TestCase
{
    static public function dataUrlLinker()
    {
        return array(
            array(
                'http://f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png',
                '<a href="http://f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png">f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png</a>'
            ),
            array(
                'https://foo:bar@domain.com/lol%20catz!',
                '<a href="https://foo:bar&#64;domain.com/lol%20catz">domain.com/lol%20catz</a>!',
            ),
        );
    }

    /**
     * @group GH-62
     * @dataProvider dataUrlLinker
     */
    public function testUrlLinker($url, $expected)
    {
        $linker = new UrlLinker();
        $response = $linker->parse($url);

        $this->assertEquals($expected, $response);
    }
}

