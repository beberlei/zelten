<?php
namespace Zelten\Config;

use Symfony\Component\Yaml\Yaml;

/**
 * Uses Symfony YAML component to parse defaults and values files and create a config instance
 */
class YamlConfigLoader
{
    /**
     * @var string
     */
    private $defaults;
    /**
     * @var string
     */
    private $values;

    /**
     * @param string $defaults YAML File or YAML String
     * @param string $values YAML File or YAML String
     */
    public function __construct($defaults, $values)
    {
        $this->defaults = $defaults;
        $this->values = $values;
    }

    /**
     * @return \Zelten\Config\Config
     */
    public function create()
    {
        $defaults = Yaml::parse($this->defaults);
        $values = Yaml::parse($this->values);

        return new Config($defaults, $values);
    }
}

