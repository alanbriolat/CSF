<?php
/**
 * CodeScape Framework - A simple, flexible PHP framework
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/*
 * Constants
 */
/** An alias to shorten file path code */
define('DS', DIRECTORY_SEPARATOR);
/** Make the CSF base directory globally accessible */
define('CSF_BASEDIR', dirname(__FILE__));

/**
 * Exception handler
 *
 * Outputs some nice HTML instead of PHP's default 
 * all-smashed-together-on-one-line output.
 *
 * @param   Exception   $e      The exception
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

/**
 * General CSF core exception
 */
class CSF_CoreException extends Exception {}

/**
 * Load error
 *
 * Thrown if CSF fails to load a module, library or helper.
 */
class CSF_LoadError extends Exception {}

/**
 * Configuration item not found
 *
 * Thrown if a call to CSF::config() can't find the requested item and no 
 * default value was supplied.
 */
class CSF_ConfigItemNotFound extends Exception
{
    public function __construct($path)
    {
        $this->message = "'$path' does not exist";
    }
}

/**
 * Module already exists
 *
 * Thrown if loading a module would overwrite an already-loaded module.
 */
class CSF_ModuleExists extends Exception
{
    public function __construct($module)
    {
        $this->message = "Module name '$module' already in use";
    }
}

/**
 * Module not found
 *
 * Thrown when aliasing a loaded module fails because the original module hasn't
 * been loaded.
 */
class CSF_ModuleNotFound extends Exception
{
    public function __construct($module)
    {
        $this->message = "Module name '$module' not in use";
    }
}

/**
 * Dependency not found
 *
 * Thrown if a dependency for a new module is missing or does not conform to 
 * the required interface.
 */
class CSF_DependencyError extends Exception {}

/**
 * The core CodeScape Framework class
 *
 * The CodeScape Framework class is implemented using the singleton pattern, 
 * but all its methods are static, so having a reference to the framework object
 * is rarely necessary.
 */
class CSF
{
    /** @var    CSF     Singleton instance */
    protected static $_instance = null;
    /** @var    array   Loaded modules */
    protected static $_modules = array();
    /** @var    array   Loaded libraries */
    protected static $_libraries = array();
    /** @var    array   Loaded helper libraries */
    protected static $_helpers = array();
    /** @var    array   Configuration */
    protected static $_config = array();

    /**
     * Constructor
     *
     * Autoload libraries and helpers specified in 'csf.core.autoload.libraries'
     * and 'csf.core.autoload.helpers' respectively.
     *
     * The constructor is protected to prevent direct initialisation from 
     * outside of the CSF class.
     *
     * @todo    Allow autoloading of modules
     */
    protected function __construct()
    {
        // These core libraries are always loaded
        self::load_libraries(array('common', 'interfaces'));

        self::load_libraries(
            CSF::config('csf.core.autoload.libraries', array()));
        self::load_helpers(
            CSF::config('csf.core.autoload.helpers', array()));
    }

    /**
     * Singleton instance getter
     *
     * @return  CSF
     * @throws  CSF_CoreException
     */
    public static function &CSF()
    {
        if (!self::$_instance)
            throw new CSF_CoreException(
                'Cannot get instance before calling init()');
        return self::$_instance;
    }

    /**
     * Initialiser
     *
     * Create CSF instance, store configuration information, return the
     * singleton instance.
     *
     * @param   array   $config     Configuration data
     * @return  CSF
     * @throws  CSF_CoreException
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

    /**
     * Initialiser (using a YAML configuration file)
     * 
     * Load configuration from YAML file, then call the normal init() method.
     *
     * @param   string  $file       Path to configuration file
     * @return  CSF
     */
    public static function init_YAML($file)
    {
        self::load_library('spyc');
        return self::init(Spyc::YAMLLoad($file));
    }

    /**
     * Get configuration item
     *
     * Get the value of the configuration item at the specified path.  If the 
     * item does not exist, return the value of <var>$default</var>.  If no 
     * <var>$default</var> is supplied, throw CSF_ConfigItemNotFound.
     *
     * @param   string  $path       Configuration item to get
     * @param   mixed   $default    Value to return if not found
     * @return  mixed   Value at <var>$path</var>, or value of <var>$default</var>
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

    /**
     * Get module as property of framework object
     *
     * Overload __get() so that $csf->foobar is the module that was loaded with
     * name 'foobar'.  To assign to a variable, use:
     *      
     * <code>
     * $mod =& CSF::$foobar;
     * // or
     * $mod =& $csf->foobar;
     * </code>
     *
     * @param   string  $module     Name of module
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

    /**
     * Load a class
     *
     * Helper function for loading classes.  The class name is converted to a
     * lowercase filename with a '.php' extension, and searched for in all 
     * directories in <var>$paths</var> until found.  Throws CSF_LoadError if
     * the class fails to be loaded.
     *
     * @param   string  $class      Name of class to load
     * @param   array   $paths      Directories possibly containing the class
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

    /**
     * Make a module
     *
     * Create an instance of a module and return it, without plugging it into 
     * the framework object.  Module class names are converted to lowercase by
     * CSF::load_class() when used as part of a filename.  For example, the 
     * module class 'FooBar' would be loaded from a file 'foobar.php'.
     *
     * By default, the config item 'csf.core.module_dir', followed by 
     * '<var>CSF_BASEDIR</var>/core', is tried as a path to the specified module.
     * This can be overridden using the <var>$basedir</var> argument.
     * 
     * @param   string  $module     Name of module to load
     * @param   array   $args       Argements to the module's constructor
     * @param   string  $basedir    Override module search path
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

    /**
     * Load a module
     *
     * Load the specified module and plug it into the framework under the 
     * module's name, or if it's non-null, <var>$alias</var> (useful for modules
     * that may have multiple instances).  Throws CSF_ModuleExists if the module
     * name is already in use - if this is a problem, check with 
     * CSF::module_exists() first.
     *
     * @param   string  $module     The module to load
     * @param   array   $args       Arguments to the module's constructor
     * @param   string  $alias      Override module name (default: class name)
     * @param   string  $basedir    Override module search path
     * @return  CSF_Module
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

    /**
     * Link an existing module under a new name
     * 
     * You might want to do this if you have a module loaded which conforms to
     * an interface another module depends on, but under an unexpected name.
     * The result of this method is that $name and $newname point to the same
     * module.  If $newname is already in use, CSF_ModuleExists is thrown.  If
     * $name does not exist, CSF_ModuleNotFound is thrown.
     *
     * @param   string  $source     Module to link
     * @param   string  $target     New alias for the module
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

    /**
     * Check if a module is loaded
     *
     * @param   string  $name       Module name to check for
     * @return  boolean
     */
    public static function module_exists($name)
    {
        return array_key_exists($name, self::$_modules);
    }

    /**
     * Load a library
     *
     * Include the file '<var>$lib</var>.php' from a library directory.  If 
     * <var>$basedir</var> is not supplied, the path set at config item 
     * 'csf.core.library_dir' is tried, followed by '<var>CSF_BASEDIR</var>/lib'.
     * If <var>$basedir</var> is supplied, only 
     * '<var>$basedir</var>/<var>$lib</var>.php' is tried.  If the library 
     * cannot be loaded, a CSF_LoadError exception is thrown.  If the library
     * has already been loaded, the request is ignored.
     *
     * @param   string  $lib        Library to load
     * @param   string  $basedir    Override library search path
     * @throws  CSF_LoadError
     */
    public static function load_library($lib, $basedir = null)
    {
        // Stop if the library is already loaded
        if (in_array($lib, self::$_libraries)) return;

        // Use $basedir if supplied
        if ($basedir)
        {
            $paths = array($basedir);
        }
        else
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

    /**
     * Load an array of libraries
     *
     * Uses CSF::load_library on each of the individual library names specified.
     *
     * @param   array   $libs       Libraries to load
     * @param   string  $basedir    Override library search path
     */
    public static function load_libraries($libs, $basedir = null)
    {
        foreach ($libs as $l) CSF::load_library($l, $basedir);
    }

    /**
     * Load a helper
     *
     * This works just like for libraries, with the only difference being that
     * the default directories are config item 'csf.core.helper_dir' and 
     * '<var>CSF_BASEDIR</var>/helpers'.
     *
     * @param   string  $helper     Helper to load
     * @param   string  $basedir    Override helper search path
     * @throws  CSF_LoadError
     */
    public static function load_helper($helper, $basedir = null)
    {
        // Stop if the helper is already loaded
        if (in_array($helper, self::$_helpers)) return;

        // Use $basedir if supplied
        if ($basedir)
        {
            $paths = array($basedir);
        }
        else
        {
            try
            {
                $paths = array(
                    CSF::config('csf.core.helper_dir'), 
                    CSF_BASEDIR.DS.'helpers');
            }
            catch (CSF_ConfigItemNotFound $e)
            {
                $paths = array(CSF_BASEDIR.DS.'helpers');
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

    /**
     * Load an array of helpers
     *
     * Uses CSF::load_helper on each of the individual helper names specified.
     *
     * @param   array   $helpers    Helpers to load
     * @param   string  $basedir    Override search path
     */
    public static function load_helpers($helpers, $basedir = null)
    {
        foreach ($helpers as $l) CSF::load_helper($h, $basedir);
    }
}


/**
 * CodeScape Framework module base class
 *
 * <ul>
 *  <li>Provides $this->CSF->module access to other modules.</li>
 *  <li>Performs dependency checking using <var>$_depends</var>.</li>
 * </ul>
 *
 * To take advantage of dependency checking, set <var>$_depends</var> in the 
 * derived class:
 * <code>
 * protected $_depends = array(
 *     array(
 *         'name'      => 'request',
 *         'interface' => 'CSF_IRequest',
 *     ),
 * );
 * </code>
 *
 * <b>Don't forget to call parent::__construct() in subclass constructors if you 
 * want these features in your own modules.</b>
 */
abstract class CSF_Module
{
    /** @var    CSF     The framework object */
    protected $CSF = null;

    /** @var    array   Dependencies */
    protected $_depends = array();

    /**
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
