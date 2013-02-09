<?php

namespace Zelten\Config;

/**
 * Application Configuration for Zelten.
 *
 * Knows all the configuration variables, their values and if they
 * have been set in the application or not.
 */
class Config
{
    private $defaults = array();
    private $values = array();

    public function __construct(array $defaults = array(), array $values = array())
    {
        $this->defaults = $defaults;
        $this->values = $values;
    }

    /**
     * Return configuration value of the given variable.
     *
     * @throws \Zelten\Config\ConfigException
     *
     * @return mixed
     */
    public function get($variable)
    {
        if ( ! array_key_exists($variable, $this->defaults)) {
            throw new ConfigException(sprintf(
                "Unknown configuration variable %s accessed.", $variable
            ));
        }

        if (array_key_exists($variable, $this->values)) {
            return $this->values[$variable];
        }

        return $this->defaults[$variable];
    }

    /**
     * Returns an array of all the variables that the user still needs to configure.
     *
     * @return array
     */
    public function getUnconfiguredVariables()
    {
        return array_keys(
            array_map(
                function ($value) {
                    return $value === null; },
                $this->defaults));
    }
}
