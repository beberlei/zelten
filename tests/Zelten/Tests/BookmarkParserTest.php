<?php

namespace Zelten\Tests;

use Zelten\Bookmark;
use Zelten\BookmarkParser;

class BookmarkParserTest extends \PHPUnit_Framework_TestCase
{
    public function testExtractTitle()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<html><head><title>Benjamin Eberlei</title></head></html>');

        $this->assertEquals('Benjamin Eberlei', $bookmark->getTitle());
    }

    public function testHasTitleNoExtraction()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $bookmark->setTitle('Foo!');
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<html><head><title>Benjamin Eberlei</title></head></html>');

        $this->assertEquals('Foo!', $bookmark->getTitle());
    }

    public function testExtractImage()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<html><body><img src="http://beberlei.de/bild.jpg" /></body></html>');

        $this->assertEquals('http://beberlei.de/bild.jpg', $bookmark->getImage());
    }

    public function testExtractAllImages()
    {
        $dom = new \DOMDocument();
        $dom->loadHtml(<<<HTML
<html>
    <body>
        <img src="http://www.spiegel.de/logo.png">
        <div><img src="/foo.png" /></div>
        <img src="relative.png" />
    </body>
</html>
HTML
        );
        $parser = new BookmarkParser();
        $images = $parser->extractAllImages('https://beberlei.de/foo/bar.html', $dom);

        $this->assertEquals(array(
            'http://www.spiegel.de/logo.png',
            'https://beberlei.de/foo.png',
            'https://beberlei.de/foo/relative.png',
        ), $images);
    }

    public function testExtractAllImagesWithBaseHref()
    {
        $dom = new \DOMDocument();
        $dom->loadHtml(<<<HTML
<html>
    <head>
        <base href="http://www.beberlei.de" />
    </head>
    <body>
        <div><img src="/foo.png" /></div>
        <img src="relative.png" />
    </body>
</html>
HTML
        );

        $parser = new BookmarkParser();
        $images = $parser->extractAllImages('https://beberlei.de/foo/bar.html', $dom);

        $this->assertEquals(array(
            'https://beberlei.de/foo.png',
            'http://www.beberlei.de/relative.png',
        ), $images);
    }

    public function testExtractOpenGraphProperties()
    {
        $dom = new \DOMDocument();
        $dom->loadHtml(<<<HTML
<html>
    <head>
        <meta property="foo" value="bar" />
        <meta property="og:title" content="Foo!" />
        <meta property="og:image" content="http://www.beberlei.de/foo.png" />
    </head>
</html>
HTML
        );

        $parser = new BookmarkParser();
        $openGraph = $parser->extractOpenGraphProperties($dom);

        $this->assertEquals(array(
            'og:title' => 'Foo!',
            'og:image' => 'http://www.beberlei.de/foo.png',
        ), $openGraph);
    }

    public function testExtractgOgDescription()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<meta property="og:description" content="Test!" />');

        $this->assertEquals('Test!', $bookmark->getDescription());
    }

    public function testExtractOgTitle()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<html><meta property="og:title" content="Test!" /><title>Not Test!</title></html>');

        $this->assertEquals('Test!', $bookmark->getTitle());
    }

    public function testExtractLocales()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();

        $parser->enrich($bookmark, '<html><meta property="og:locale" content="de_DE" /><meta property="og:locale:alternate" content="en_UK" /><meta property="og:locale:alternate" content="en_US" /></html>');

        $this->assertEquals(array('de_DE', 'en_UK', 'en_US'), $bookmark->getLocale());
    }

    public function testExtractSiteName()
    {
        $bookmark = new Bookmark("http://www.beberlei.de");
        $parser = new BookmarkParser();
        $parser->enrich($bookmark, '<html><meta property="og:site_name" content="Test!" /></html>');

        $this->assertEquals('Test!', $bookmark->getSiteName());
    }

    public function testReadabililty()
    {
        $bookmark = new Bookmark("https://tent.io/blog/introducing-tent");
        $parser = new BookmarkParser();
        $parser->readablityContent($bookmark, file_get_contents(__DIR__ . "/_files/tentio_introduction.html"));

        $this->assertNotNull($bookmark->getContent());
    }
}

