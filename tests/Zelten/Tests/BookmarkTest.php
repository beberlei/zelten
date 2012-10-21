<?php

namespace Zelten\Tests;

use Zelten\Bookmark;

class BookmarkTest extends \PHPUnit_Framework_TestCase
{
    public function testCreate()
    {
        $bookmark = new Bookmark("https://www.beberlei.de");
        $this->assertEquals("https://www.beberlei.de", $bookmark->getUrl());
    }
}

