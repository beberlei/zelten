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
                'testing long link: http://f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png',
                'testing long link: <a href="http://f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png">f.cl.ly/items/0S142I071o130u1h2145/Screen%20Shot%202012-11-01%20at%208.08.25%20PM.png</a>'
            ),
            array(
                'https://foo:bar@domain.com/lol%20catz!',
                '<a href="https://foo:bar&#64;domain.com/lol%20catz">domain.com/lol%20catz</a>!',
            ),
            array(
                "testing short link first in line:\nhttp://foo.com",
                "testing short link first in line:\n<a href=\"http://foo.com\">foo.com</a>",
            ),
            array(
                "Still cant login on Zelten: http://poweruser.tent.is/posts/zwyyu3.\nWhere is the problem?",
                "Still cant login on Zelten: <a href=\"http://poweruser.tent.is/posts/zwyyu3\">poweruser.tent.is/posts/zwyyu3</a>.\nWhere is the problem?"
            ),
            array(
                "Bug: https://github.com/tent/tentd/issues/42. It's preve",
                'Bug: <a href="https://github.com/tent/tentd/issues/42">github.com/tent/tentd/issues/42</a>. It\'s preve',
            ),
            array(
                "..purposes, like http://gaming.bitnbang.com and http://design.bitnbang.com",
                '..purposes, like <a href="http://gaming.bitnbang.com">gaming.bitnbang.com</a> and <a href="http://design.bitnbang.com">design.bitnbang.com</a>'
            ),
            array(
                "See this thread (https://github.com/beberlei/zelten/issues/106).",
                'See this thread (<a href="https://github.com/beberlei/zelten/issues/106">github.com/beberlei/zelten/issues/106</a>).'
            )
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

