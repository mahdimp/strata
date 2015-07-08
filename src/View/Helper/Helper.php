<?php
namespace Strata\View\Helper;

use Strata\Strata;
use Strata\Utility\Inflector;
use Exception;

/**
 * A base class for ViewHelper objects
 */
class Helper {

    /**
     *
     * @param  string $name The class name of the helper
     * @param   mixed $config Optional helper configuration
     * @return mixed       A controller
     */
    public static function factory($name, $config = array())
    {
        $classpath = self::generateClassPath($name);
        if (class_exists($classpath)) {
            return new $classpath($config);
        }

        throw new Exception("Strata : No file matched the view helper '$classpath'.");
    }

    /**
     * Generates a possible namespace and classname combination of a
     * Strata view helper. Mainly used to avoid hardcoding the '\\View\\Helper\\'
     * string everywhere.
     * @param  string $name The class name of the helper
     * @return string       A fully namespaced helper name
     */
    public static function generateClassPath($name)
    {
        if (!preg_match("/Helper$/", $name)) {
            $name .= "Helper";
        }
        $name = str_replace("-", "_", $name);
        return Strata::getNamespace() . "\\View\\Helper\\" . Inflector::classify($name);
    }

    public function getShortName()
    {
        $rc = new \ReflectionClass($this);
        return $rc->getShortName();
    }

}