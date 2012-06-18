<?php
/**
 * This file is part of the Tribe project for PHP.
 *
 * @license http://opensource.org/licenses/bsd-license.php BSD
 */
namespace Tribe\Librarian;

/**
 * An SPL autoloader adhering to [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md)
 * and <https://wiki.php.net/rfc/splclassloader>.
 *
 * @package Tribe\Librarian
 */
class Loader
{
    /**
     * Operational mode where no exceptions are thrown under error conditions.
     *
     * @const
     */
    const MODE_SILENT = 0;

    /**
     * Operatinal mode where an exception is thrown when a class file is not
     * found.
     *
     * @const
     */
    const MODE_NORMAL = 1;

    /**
     * Operatinal mode where an exception is thrown when a class file is not
     * found, or if after loading the file the class is still not declared.
     *
     * @const
     */
    const MODE_DEBUG = 2;

    /**
     * @var int The operational mode.
     */
    protected $mode = Loader::MODE_NORMAL;

    /**
     * Classes and interfaces loaded by the autoloader.
     * The key is the class name and the value is the file name.
     *
     * @var array
     */
    protected $loaded = array();

    /**
     * A map of class name prefixes to directory paths.
     *
     * @var array
     */
    protected $paths = array();

    /**
     * A list of general directory paths to look in.
     *
     * @var array
     */
    protected $fallbacks = array();

    /**
     * A map of the classes.
     *
     * @var array
     */
    protected $map = array();

    /**
     * A log of paths that have been tried during load(), for debug use.
     *
     * @var array
     */
    protected $tried_paths = array();

    /**
     * Loader options including namespace_separator, file_extension and path_lookup.
     *
     * @var array
     */
    protected $options = array(
        'namespace_separator' => '\\',
        'file_extension' => '.php',
        'path_lookup' => false,
    );

    /**
     * Registers this autoloader with SPL.
     *
     * @param bool $prepend True to prepend to the autoload stack.
     */
    public function register($prepend = false)
    {
        spl_autoload_register(array($this, 'load'), true, (bool) $prepend);
    }

    /**
     * Unregisters this autoloader from SPL.
     */
    public function unregister()
    {
        spl_autoload_unregister(array($this, 'load'));
    }

    /**
     * Sets or gets the loader options including namespace_separator, file_extension and path_lookup.
     *
     * @param array $options An array of the options to overwrite.
     *
     * @return Loader|array
     */
    public function options(array $options = null)
    {
        // Return the current options if no parameter
        if ($options === null) {
            return $this->options;
        }

        // Overwrite the new options
        $this->options = array_merge($this->options, $options);

        // Return $this to make it chainable
        return $this;
    }

    /**
     * Sets or gets the operational mode.
     *
     * @param int $mode Mode value that can be MODE_SILENT, MODE_NORMAL or MODE_DEBUG constants.
     *
     * @return Loader|array
     */
    public function mode($mode = null)
    {
        // Return the current mode if no parameter
        if ($mode === null) {
            return $this->mode;
        }

        // Set up the load mode
        $this->mode = $mode;

        // Return $this to make it chainable
        return $this;
    }

    /**
     * Adds a directory path for a class name prefix.
     *
     * @param string $prefix The class name prefix, e.g. 'Tribe\Framework\\' or
     * 'Zend_'.
     *
     * @param array|string $paths The directory path leading to the classes
     * with that prefix, e.g. `'/path/to/system/package/Tribe.Framework-dev/src'`.
     * Note that the classes must thereafter be in subdirectories of their
     * own, e.g. `'/Tribe/Framework/'.
     */
    public function add($prefix, $paths)
    {
        // Add everything that is not a string as a fallback
        if (!is_string($prefix)) {
            foreach ((array) $paths as $path) {
                $this->fallbacks[] = $path;
            }

            return;
        }

        // Parse the paths
        $parsed = array();
        foreach ((array) $paths as $path) {
            $parsed[] = rtrim($path, DIRECTORY_SEPARATOR);
        }

        // Merge the prefixes with the new paths
        $this->paths[$prefix] = (isset($this->paths[$prefix]))
            ? array_merge($this->paths[$prefix], $parsed)
            : $parsed;
    }

    /**
     * Sets or gets all class name prefixes and their paths. Setting this overwrites the
     * existing mappings.
     *
     * Paths can a string or an array. For example:
     *
     * $loader->paths(array(
     *      'Zend_'=> '/path/to/zend/library',
     *      'Tribe\\' => array(
     *          '/path/to/project/Tribe.Router/src/',
     *          '/path/to/project/Tribe.Framework/src/'
     *      ),
     *      'Vendor\\' => array(
     *          '/path/to/project/Vendor.Package/src/',
     *      ),
     *      'Symfony\Component' => 'path/to/Symfony/Component',
     * ));
     *
     * @param array $paths An associative array of class names and paths.
     *
     * @return Loader|array
     */
    public function paths(array $paths = null)
    {
        if ($paths === null) {
            return $this->paths;
        }

        // This is a request to set up the paths
        foreach ($paths as $key => $val) {
            $this->add($key, $val);
        }

        // Return $this to make it chainable
        return $this;
    }

    /**
     * Sets or gets all file paths for all class names. Setting this overwrites all previous
     * exact mappings.
     *
     * @param array $map An array of class-to-file mappings where the key
     * is the class name and the value is the file path.
     *
     * @return Loader|array
     */
    public function map(array $map = null)
    {
        // This is a request to get the map
        if ($map === null) {
            return $this->map;
        }

        // This is a request to set up the map
        $this->map = array_merge($this->map, $map);

        // Return $this to make it chainable
        return $this;
    }

    /**
     * Returns the list of classes and interfaces loaded by the autoloader.
     *
     * @return array An array of key-value pairs where the key is the class
     * or interface name and the value is the file name.
     */
    public function loaded()
    {
        return $this->loaded;
    }

    /**
     * Loads a class or interface using the class name prefix and path,
     * falling back to the include-path if not found.
     *
     * @param string $name The class or interface to load.
     *
     * @return void
     *
     * @throws Exception::NOT_READABLE when the file for the class or interface is not found.
     * @throws Exception::ALREADY_LOADED when the file for the class or interface is already loaded.
     * @throws Exception::NOT_DECLARED when the file is found but the class or interface is not declared.
     */
    public function load($name)
    {
        // Check if the class is already loaded
        if ($this->declared($name)) {

            // Return an exception in MODE_DEBUG
            if ($this->mode === static::MODE_DEBUG) {
                throw new Exception('Class, interface or trait is already loaded.', Exception::ALREADY_LOADED);
            }

            return;
        }

        // Find the class file
        $file = $this->find($name);

        // Check if we have a file
        if (!$file) {

            // Return an exception, except in MODE_SILENT
            if ($this->mode !== static::MODE_SILENT) {

                // Set up the exception message
                $message = "Cannot find class, instance or trait `{$name}` in the following paths:"
                    . PHP_EOL . implode(PHP_EOL, $this->tried_paths);

                throw new Exception($message, Exception::NOT_READABLE);
            }

            return;
        }

        // load the file
        require $file;

        // Check if the class was loaded properly
        if (!$this->declared($name)) {

            // Return an exception in MODE_DEBUG
            if ($this->mode === static::MODE_DEBUG) {
                throw new Exception('Cannot declare class, interface or trait.', Exception::NOT_DECLARED);
            }

            return;
        }

        // Add the file path to the loaded classes array
        $this->loaded[$name] = $file;
    }

    /**
     * Finds the path to a class or interface using the class prefix paths and include-path.
     *
     * @param string $name The class or interface to find.
     *
     * @return The absolute path to the class or interface.
     */
    public function find($name)
    {
        // Trim the separator from the begining
        $name = ltrim($name, $this->options['namespace_separator']);

        // Check if the path is explicitly declared
        if (isset($this->map[$name])) {
            return $this->map[$name];
        }

        // Reset the tried paths
        $this->tried_paths = array();

        // Check if we have a namespace in the path
        if (($pos = strripos($name, $this->options['namespace_separator'])) !== false) {

            // Load the namespace
            $namespace = substr($name, 0, $pos);

            $namespace = str_replace($this->options['namespace_separator'], DIRECTORY_SEPARATOR, $namespace) . DIRECTORY_SEPARATOR;

            // Get the class name
            $class = substr($name, $pos + 1);

        } else {
            $namespace = '';
        }

        // Return the file by prepending the namespace and converting the underscores in the class name
        $file = $namespace . str_replace('_', DIRECTORY_SEPARATOR, $class) . $this->options['file_extension'];

        // Search through each of the path prefixes
        foreach ($this->paths as $prefix => $paths) {

            // Only look for the files in the selected namespace
            if (stripos($name, $prefix) !== 0) {
                continue;
            }

            // Return the first matched path to the file
            if ($found = $this->lookup($file, (array) $paths)) {
                return $found;
            }
        }

        // Search through fallback paths
        foreach ($this->fallbacks as $paths) {

            // Return the first matched path to the file
            if ($found = $this->lookup($file, (array) $paths)) {
                return $found;
            }
        }

        // Check the include path if there is a setting for that
        if ($this->options['path_lookup']) {

            // Track the include path also
            $this->tried_paths[] = get_include_path();

            // Fallback for not finding the path
            return stream_resolve_include_path($file);
        }

        // Cannot find any file
        return false;
    }

    /**
     * Searches for a file in the selected files paths.
     *
     * @param string $file The class, interface or trait.
     * @param string $paths The paths to search the file in.
     *
     * @return bool
     */
    protected function lookup($file, array $paths)
    {
        // Return the first matched path to the file
        foreach ((array) $paths as $path) {

            // Track which paths we have tried
            $this->tried_paths[] = $path;

            // Prepend the path to the file
            $found = $path . DIRECTORY_SEPARATOR . $file;

            // Check if the file exists and is readable
            if (is_file($found) && is_readable($found)) {
                return $found;
            }
        }

        return false;
    }

    /**
     * Tells if a class, interface or trait exists.
     *
     * @param string $name The class, interface or trait.
     *
     * @return bool
     */
    protected function declared($name)
    {
        return class_exists($name, false)
            || interface_exists($name, false)
            || (function_exists('trait_exists') && trait_exists($name, false));
    }
}