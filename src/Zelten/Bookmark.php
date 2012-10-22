<?php
/**
 * Zelten
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace Zelten;

/**
 * A bookmark to foreign content.
 *
 * The bookmark at its heart is just a url. You can
 * save alot of additional metadata or have them
 * parsed from the page (using OpenGraph spec).
 *
 * Tent Post Type Details: http://beberlei.de/tent/bookmark/v0.0.1
 *
 * url          Required    String  Url of the bookmark
 * title        Required    String  Title of the page
 * image        Optional    String  A link to an image found/explaining the url
 * description  Optional    String  A description of the contents to be found on the url.
 * locale       Optional    Array   The locale the content is available in
 * site_name    Optional    String  Name of the Website/Network the content is posted on.
 * tags         Optional    Array   A list of tags associated with this bookmark.
 * content      Optional    String  A copy of the content (as backup or for readbility)
 */
class Bookmark
{
    private $id;
    private $url;
    private $title;
    private $image;
    private $description;
    private $locale = array();
    private $siteName;
    private $tags = array();
    private $content;
    private $privacy;

    public function __construct($url = null)
    {
        $this->setUrl($url);
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUrl($url)
    {
        if (strpos($url, "http") === false) {
            $url = "http://" . $url;
        }

        if (strpos($url, "http") === false) {
            throw new \InvalidArgumentException("Invalid Url");
        }

        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setTitle($title)
    {
        $this->title = $title;
    }

    public function getImage()
    {
        return $this->image;
    }

    public function setImage($image)
    {
        $this->image = $image;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function getLocale()
    {
        return $this->locale;
    }

    public function addLocale($locale)
    {
        $this->locale[] = $locale;
    }

    public function getSiteName()
    {
        return $this->siteName;
    }

    public function setSiteName($siteName)
    {
        $this->siteName = $siteName;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function addTag($tag)
    {
        $this->tags[] = $tag;
    }

    public function getContent()
    {
        return $this->content;
    }

    public function setContent($content)
    {
        $this->content = $content;
    }

    public function setPrivacy($privacy)
    {
        $this->privacy = $privacy;
    }

    public function getPrivacy()
    {
        return $this->privacy;
    }

    public function toArray()
    {
        return array_filter(array(
            'id'          => $this->id,
            'url'         => $this->url,
            'title'       => $this->title,
            'image'       => $this->image,
            'description' => $this->description,
            'locale'      => $this->locale,
            'site_name'   => $this->siteName,
            'tags'        => $this->tags,
            'content'     => $this->content,
        ));
    }
}

