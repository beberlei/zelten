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
    public function get($variable)
    {
        throw new ConfigException(sprintf(
            "Unknown configuration variable %s accessed.", $variable
        ));
    }
}
