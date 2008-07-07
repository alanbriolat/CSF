<?php
/*
 * CodeScape Framework - A simple, flexible PHP framework
 * Copyright (C) 2008, Alan Briolat <alan@codescape.net>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/*
 * Constant definitions
 */
define('DS', DIRECTORY_SEPARATOR);          // Make some code shorter...
define('CSF_BASEDIR', dirname(__FILE__));

/*
 * Exception handler - outputs some nice HTML instead of PHP's default 
 * all-smashed-together-on-one-line output.
 */
function CSF_pretty_exception_handler($e)
{
    echo "<p>"
        . "<strong>Fatal error:</strong> Uncaught exception '".get_class($e)."'"
        . "</p>\n"
        . "<dl>\n"
        . "<dd></dd>\n"
        . "<dt>Message:</dt>\n"
        . "<dd>".($e->getMessage() ? $e->getMessage() : '(none)')."</dd>\n"
        . "<dt>Location:</dt>\n"
        . "<dd>".$e->getFile().", Line ".$e->getLine()."</dd>\n"
        . "<dt>Stack trace:</dt>\n"
        . "<dd><pre>".$e->getTraceAsString()."</pre></dd>\n"
        . "</dl>\n";
}
set_exception_handler('CSF_pretty_exception_handler');

/*
 * Exceptions thrown by classes in this file
 */
class CSF_CoreException extends Exception {}
class CSF_LoadError extends Exception {}
class CSF_ConfigItemNotFound extends Exception
{
    public function __construct($path)
    {
        $this->message = "'$path' does not exist";
    }
}
class CSF_ModuleExists extends Exception
{
    public function __construct($module)
    {
        $this->message = "Module name '$module' already in use";
    }
}
class CSF_ModuleNotFound extends Exception
{
    public function __construct($module)
    {
        $this->message = "Module name '$module' not in use";
    }
}
class CSF_DependencyError extends Exception {}

/*
 * Load core libraries
 */
require_once(CSF_BASEDIR.DS.'lib'.DS.'common.php');
require_once(CSF_BASEDIR.DS.'lib'.DS.'interfaces.php');

/*
 * The main CodeScape Framework class, implemented as a singleton.
 */
class CSF
{
    // Singleton instance
    protected static $_instance = null;
    // Loaded modules
    protected static $_modules = array();
    // Loaded libraries
    protected static $_libraries = array();
    // Loaded helper libraries
    protected static $_helpers = array();
    // Configuration
    protected static $_config = array();

    /*
     * Constructor
     *
     * Constructor is protected to prevent initialisation via any method other
     * than CSF::init().
     */
    protected function __construct()
    {
        CSF::load_library('spyc');
    }

    /*
     * Singleton instance getter
     *
     * @return  CSF
     */
    public static function &CSF()
    {
        if (!self::$_instance)
            throw new CSF_CoreException(
                'Cannot get instance before calling init()');
        return self::$_instance;
    }

    /*
     * Initialiser
     *
     * Create CSF instance, store configuration information, return the
     * singleton instance.
     *
     * @param   $config     array       Configuration data
     * @return  CSF
     */
    public static function &init($config)
    {
        // Error check - has CSF already been initialised?
        if ( self::$_instance )
            throw new CSF_CoreException('init() has already been called');
        self::$_config = $config;
        self::$_instance = new CSF();
        return self::$_instance;
    }

    /*
     * Initialiser (using a YAML configuration file)
     * 
     * Load configuration from YAML file, then call the normal init() method.
     *
     * @param   $file   string      Path to configuration file
     * @return  CSF
     */
    public static function init_YAML($file)
    {
        self::load_library('spyc');
        return self::init(Spyc::YAMLLoad($file));
    }

    /*
     * Get configuration item
     *
     * Get the value of the configuration item at the specified path.  If the 
     * item does not exist, return the value of $default.  If no $default is 
     * supplied, throw CSF_ConfigItemNotFound.
     *
     * @param   $path       string      Configuration item to get
     * @param   $default    mixed       Value to return if not found
     * 
     * @return  mixed       Value at $path, or value of $default
     *
     * @throws  CSF_ConfigItemNotFound
     */
    public static function config($path, $default = null)
    {
        // Start at the root
        $item = self::$_config;

        // Traverse the multi-dimensional array
        foreach ( explode('.', $path) as $p )
            if ( !isset($item[$p]) )
                if ( func_num_args() < 2 )
                    throw new CSF_ConfigItemNotFound($path);
                else
                    return $default;
            else
                $item = $item[$p];

        return $item;
    }

    /*
     * Get module as property of framework object
     *
     * Overload __get() so that $csf->foobar is the module that was loaded with
     * name 'foobar'.  To assign to a variable, use:
     *      
     *      $mod =& CSF::$foobar;
     * or
     *      $mod =& $csf->foobar;
     *
     * @param   $module     string      Module name
     * @return  CSF_Module
     */
    public static function __get($module)
    {
        // Give a meaningful error if the module isn't loaded
        if ( !array_key_exists($module, self::$_modules) )
        {
            trigger_error("Module '$module' not loaded, returning null", 
                E_USER_WARNING);
            return null;
        }

        return self::$_modules[$module];
    }

    /*
     * Load a class
     *
     * Helper function for loading classes.  The class name is converted to a
     * lowercase filename with a '.php' extension, and searched for in all 
     * directories in $paths until found.  An error occurs if the class failed 
     * to be loaded.
     *
     * @param   $class  string      Name of class to load
     * @param   $paths  array       Directories possibly containing the class
     *
     * @throws  CSF_LoadError
     */
    public static function load_class($class, $paths = array())
    {
        $class = strtolower($class);
        // Preserve paths for error message
        $paths_orig = $paths;

        // Try all possible paths while the class doesn't exist
        while ( !class_exists($class) && ($path = array_shift($paths)) )
            if ( file_exists($path.DS."$class.php") )
                include_once($path.DS."$class.php");

        // Throw an error if the class still doesn't exist
        if ( !class_exists($class) )
            throw new CSF_LoadError("Class '$class' could not be loaded: check "
                . "that it's defined in the file '$class.php' in a supplied "
                . "path (tried: ".implode(', ', $paths_orig).")");
    }

    /*
     * Make a module
     *
     * Create an instance of a module and return it, without plugging it into 
     * the framework object.  Module class names are converted to lowercase by
     * CSF::load_class() when used as part of a filename.  For example, the 
     * module class 'FooBar' would be loaded from a file 'foobar.php'.
     *
     * By default, the config item 'csf.core.module_dir', followed by 
     * 'CSF_BASEDIR/core', is tried as a path to the specified module.  This
     * can be overridden using the $basedir argument.
     * 
     * @param   $module     string      Name of module to load
     * @param   $args       array       Arguments to the module's constructor
     * @param   $basedir    string      Override module path
     * @return  CSF_Module
     */
    public static function make_module($module, 
                                       $args = array(),
                                       $basedir = null)
    {
        // Decide the paths based on whether or not one was supplied
        if ( $basedir )
        {
            // If a path is supplied, use it
            $paths = array($basedir);
        }
        else
        {
            // If no path is supplied, use the default ones.  This whole 
            // construct should allow modules to be transparently overridden.
            try
            {
                $paths = array(
                    CSF::config('csf.core.module_dir'),
                    CSF_BASEDIR.DS.'core');
            }
            catch (CSF_ConfigItemNotFound $e)
            {
                $paths = array(CSF_BASEDIR.DS.'core');
            }
        }

        // Make sure the class is loaded
        self::load_class($module, $paths);

        // Instantiate the class and return the object
        $ref = new ReflectionClass($module);
        return $ref->newInstanceArgs($args);
    }

    /*
     * Load a module
     *
     * Load the specified module and plug it into the framework under the 
     * module's name, or if it's non-null, $alias (useful for modules that may
     * have multiple instances).  Throws CSF_ModuleExists if the module name is
     * already in use - if this is a problem, check with CSF::module_exists() 
     * first.
     *
     * @param   $module     string      The module to load
     * @param   $args       array       Arguments to the module's constructor
     * @param   $alias      string      Name to use, rather than the class name
     * @param   $basedir    string      Override module path
     * @return  CSF_Module
     *
     * @throws  CSF_ModuleExists
     */
    public static function load_module($module,
                                       $args = array(), 
                                       $alias = null,
                                       $basedir = null)
    {
        // Get the access name for the module
        $name = empty($alias) ? $module : $alias;

        // Check if the module is already loaded
        if (self::module_exists($name))
            throw new CSF_ModuleExists($name);

        // Make and return the module
        self::$_modules[$name] =& self::make_module($module, $args, $basedir);
        return self::$_modules[$name];
    }

    /*
     * Link an existing module under a new name
     * 
     * You might want to do this if you have a module loaded which conforms to
     * an interface another module depends on, but under an unexpected name.
     * The result of this method is that $name and $newname point to the same
     * module.  If $newname is already in use, CSF_ModuleExists is thrown.  If
     * $name does not exist, CSF_ModuleNotFound is thrown.
     *
     * @param   $source     string      Module to link
     * @param   $target     string      New alias for the module
     *
     * @throws  CSF_ModuleExists
     * @throws  CSF_ModuleNotFound
     */
    public static function link_module($source, $target)
    {
        if (!self::module_exists($source))
            throw new CSF_ModuleNotFound($source);
        if (self::module_exists($target))
            throw new CSF_ModuleExists($target);

        self::$_modules[$target] =& self::$_modules[$source];
    }

    /*
     * Check if a module is loaded
     *
     * @param   $name   string      Module name to check for
     * @return  boolean
     */
    public static function module_exists($name)
    {
        return array_key_exists($name, self::$_modules);
    }

    /*
     * Load a library
     *
     * Include the file $lib.php from a library directory.  If $paths is not 
     * supplied, the path set at config item 'csf.core.library_dir' is tried, 
     * followed by 'CSF_BASEDIR/lib'.  If $paths is supplied, the directories
     * are tried in turn until the file is found.  If the library cannot be 
     * loaded, a CSF_LoadError exception is thrown.  If the library has already
     * been loaded, the request is ignored.
     *
     * @param   $lib    string      Library to load
     * @param   $paths  array       Override paths to search for the library
     * 
     * @throws  CSF_LoadError
     */
    public static function load_library($lib, $paths = null)
    {
        // Stop if the library is already loaded
        if (in_array($lib, self::$_libraries)) return;

        // Use default paths if none supplied
        if (is_null($paths))
        {
            try
            {
                $paths = array(
                    CSF::config('csf.core.library_dir'), 
                    CSF_BASEDIR.DS.'lib');
            }
            catch (CSF_ConfigItemNotFound $e)
            {
                $paths = array(CSF_BASEDIR.DS.'lib');
            }
        }

        // Try every path until the library is loaded
        foreach ($paths as $path)
        {
            $filepath = rtrim($path, '/\\').DS."$lib.php";
            if (file_exists($filepath))
            {
                // If the file has been found, load it and stop
                include_once($filepath);
                self::$_libraries[] = $lib;
                return;
            }
        }

        // If we get this far, the library couldn't be loaded
        throw new CSF_LoadError("Could not load library '$lib' from any of the "
            . "supplied paths (tried: ".implode(', ', $paths).")");
    }

    /*
     * Load a helper
     *
     * This works just like for libraries, with the only difference being that
     * the default directories are config item 'csf.core.helper_dir' and 
     * 'CSF_BASEDIR/helpers'.
     *
     * @param   $helper     string      Helper to load
     * @param   $paths      array       Override search paths
     *
     * @throws  CSF_LoadError
     */
    public static function load_helper($helper, $paths = null)
    {
        // Stop if the helper is already loaded
        if (in_array($helper, self::$_helpers)) return;

        // Use default paths if none supplied
        if (is_null($paths))
        {
            try
            {
                $paths = array(
                    CSF::config('csf.core.helper_dir'), 
                    CSF_BASEDIR.DS.'helper');
            }
            catch (CSF_ConfigItemNotFound $e)
            {
                $paths = array(CSF_BASEDIR.DS.'helper');
            }
        }

        // Try every path until the helper is loaded
        foreach ($paths as $path)
        {
            $filepath = rtrim($path, '/\\').DS."$helper.php";
            if (file_exists($filepath))
            {
                // If the file has been found, load it and stop
                include_once($filepath);
                self::$_helpers[] = $helper;
                return;
            }
        }

        // If we get this far, the helper couldn't be loaded
        throw new CSF_LoadError("Could not load helper '$helper' from any of the "
            . "supplied paths (tried: ".implode(', ', $paths).")");
    }
}


/*
 * CodeScape Framework module class
 *
 * All modules should derive from this class.  Provides $this->CSF->module as a
 * convenient way of accessing all other loaded modules.  May include more stuff
 * at a later date.
 *
 * Don't forget to call parent::__construct() in subclass constructors if you 
 * want these features in your own modules.
 */
abstract class CSF_Module
{
    // The framework object
    protected $CSF = null;

    // Dependencies
    protected $_depends = array(
    // Example:
    //    array(
    //        'name'      => 'request',
    //        'interface' => 'CSF_IRequest',
    //    ),
    );

    /*
     * Constructor
     *
     * Get the CSF object, check for dependencies.
     *
     * @throws  CSF_DependencyError
     */
    public function __construct()
    {
        $this->CSF =& CSF::CSF();

        foreach ($this->_depends as $dep)
        {
            if (!CSF::module_exists($dep['name']))
                throw new CSF_DependencyError("Expected module '{$dep['name']}'"
                    . " with interface '{$dep['interface']}' not loaded");

            if (!(CSF::__get($dep['name']) instanceof $dep['interface']))
                throw new CSF_DependencyError("Dependency '{$dep['name']}' does"
                    . " not implement interface '{$dep['interface']}'");
        }
    }
}
?>
