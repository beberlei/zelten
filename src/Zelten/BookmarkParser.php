<?php

namespace Zelten;

use DOMDocument;
use DOMXPath;

class BookmarkParser
{
    public function enrich(Bookmark $bookmark, $pageContent)
    {
        $dom = new DOMDocument();
        $dom->loadHtml($pageContent);

        $openGraphProperties = $this->extractOpenGraphProperties($dom);

        if ( ! $bookmark->getTitle()) {
            if (isset($openGraphProperties['og:title'])) {
                $bookmark->setTitle($openGraphProperties['og:title']);
            } else {
                $titles = $dom->getElementsByTagName('title');

                if ($titles->length > 0) {
                    $bookmark->setTitle($titles->item(0)->nodeValue);
                }
            }
        }

        if ( ! $bookmark->getImage()) {
            if (isset($openGraphProperties['og:image'])) {
                $bookmark->setImage($openGraphProperties['og:image']);
            } else {
                $images = $this->extractAllImages($bookmark->getUrl(), $dom);

                if (isset($images[0])) {
                    $bookmark->setImage($images[0]);
                }
            }
        }

        if ( ! $bookmark->getDescription() && isset($openGraphProperties['og:description'])) {
            $bookmark->setDescription($openGraphProperties['og:description']);
        }

        if ( ! $bookmark->getLocale()) {
            if (isset($openGraphProperties['og:locale'])) {
                $bookmark->addLocale($openGraphProperties['og:locale']);
            }

            if (isset($openGraphProperties['og:locale:alternate'])) {
                foreach ($openGraphProperties['og:locale:alternate'] as $locale) {
                    $bookmark->addLocale($locale);
                }
            }
        }

        if ( ! $bookmark->getSiteName() && isset($openGraphProperties['og:site_name'])) {
            $bookmark->setSiteName($openGraphProperties['og:site_name']);
        }
    }

    /**
     * Extract all images from a DOMDocument and make them absolute
     *
     * @param string $pageUrl
     * @param DOMDocument $dom
     * @return array
     */
    public function extractAllImages($pageUrl, DOMDocument $dom)
    {
        $imageElements = $dom->getElementsByTagName('img');
        $images        = array();

        $parts = parse_url($pageUrl);

        if (!isset($parts['path'])) {
            $parts['path'] = '';
        }

        $port  = isset($parts['port']) ? ":" . $parts['port'] : "";
        $hostUrl = $parts['scheme'] . "://" . $parts['host'] . $port;
        $baseUrl = $hostUrl . dirname($parts['path']) . '/';

        $baseElements = $dom->getElementsByTagName('base');

        if ($baseElements->length > 0) {
            $baseUrl = $baseElements->item(0)->getAttribute('href') . '/';
        }

        foreach ($imageElements as $image) {
            $imageUrl = $image->getAttribute('src');
            if (strpos($imageUrl, "http") === false) {
                if (strpos($imageUrl, "/") === 0) {
                    $imageUrl = $hostUrl . $imageUrl;
                } else {
                    $imageUrl = $baseUrl . $imageUrl;
                }
            }

            $images[] = $imageUrl;
        }

        return $images;
    }

    public function extractOpenGraphProperties(DOMDocument $dom)
    {
        $propertyNodes = $dom->getElementsByTagName('meta');
        $properties = array();

        foreach ($propertyNodes as $propertyNode) {
            $propertyName = $propertyNode->getAttribute('property');

            if (strpos($propertyName, 'og:') === 0) {
                $properties[$propertyName][] = $propertyNode->getAttribute('content');
            }
        }

        return array_map(function ($value) {
            if (count($value) == 1) {
                return $value[0];
            }
            return $value;
        }, $properties);
    }
}

