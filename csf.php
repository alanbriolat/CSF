<?php
/**
 * CodeScape Framework - A simple, flexible PHP framework
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008-2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 * @link        http://codescape.net/csf/doc/core/
 */

/** Path to CSF directory */
define('CSF_PATH', dirname(__FILE__));


/**
 * The core CSF class
 *
 * The CSF class is implemented using only static methods and properties to
 * "fake" a namespace.
 *
 * Modules are assumed to be objects, and therefore assumed not to be copied 
 * by the assignment operator.
 *
 * @link    http://codescape.net/csf/doc/core/#csf
 */
abstract class CSF
{
    /** @var    array   Configuration array */
    protected static $_config = array();

    /** @var    array   Library paths */
    protected static $_library_paths = array();
    
    /** @var    array   Module paths */
    protected static $_module_paths = array();
    
    /** @var    array   Loaded libraries */
    protected static $_libraries = array();
    
    /** @var    array   Loaded modules */
    protected static $_modules = array();


    /**
     * Constructor
     *
     * Private constructor to prevent creation of CSF objects
     */
    private function __construct()
    {
    }


    /**
     * Initialise
     *
     * Store configuration, add paths from configuration, perform autoload,
     * and anything else that needs to be done before using the framework.
     * Calling this function before using CSF is preferable, but not
     * essential.  An example where you might not is if you need to load
     * a core library (e.g. 'spyc', the YAML library) to get the configuration.
     *
     * @param   array   $config     Configuration array
     */
    public static function init($config)
    {
        // Store configuration
        self::$_config = $config;

        // Use pretty exception handler?
        if (self::config('core.use_html_exception_handler', false))
            set_exception_handler('CSF_html_exception_handler');

        // Register an autoload function?
        if (self::config('core.register_library_autoload', false))
            spl_autoload_register('CSF_library_autoload');

        // Store library/module paths
        foreach (self::config('core.library_paths', array()) as $path)
            self::add_library_path($path);
        foreach (self::config('core.module_paths', array()) as $path)
            self::add_module_path($path);

        // Autoload libraries
        foreach (self::config('autoload.libraries', array()) as $lib)
            self::load_library($lib);

        // Autoload modules
        foreach (self::config('autoload.modules', array()) as $mod)
            self::load_module($mod);
    }


    /**
     * Get configuration
     *
     * Get the value of the configuration item at the specified dot-separated
     * path.  The path is used to traverse the configuration array, and the
     * item at that particular point in the array is returned without any
     * further processing.  If the path could not be found, $default is 
     * returned if supplied, otherwise a CSF_ConfigNotFound is thrown.
     *
     * @param   string  $path       Dot-separated path to configuration item
     * @param   mixed   $default    Value to return if not found
     * 
     * @return  mixed   Value at $path, or $default if not found
     *
     * @throws  CSF_ConfigNotFound
     */
    public static function config($path, $default = null)
    {
        // Start at the root
        $item = self::$_config;

        // Traverse the array
        foreach (explode('.', $path) as $p)
        {
            if (is_array($item) && array_key_exists($p, $item))
            {
                $item = $item[$p];
            }
            elseif (func_num_args() < 2)
            {
                throw new CSF_ConfigNotFound($path);
            }
            else
            {
                return $default;
            }
        }

        return $item;
    }


    /**
     * Add a library path
     * 
     * @param   string  $path       The path to add
     */
    public static function add_library_path($path)
    {
        self::$_library_paths[] = $path;
    }


    /**
     * Add a module path
     *
     * @param   string  $path       The path to add
     */
    public static function add_module_path($path)
    {
        self::$_module_paths[] = $path;
    }


    /**
     * Load a library
     *
     * Load a library from within one of the library paths.  Paths are tried
     * in reverse order so that the most recently added paths come first.  The
     * library name is used both as the filename and to make sure that the
     * library isn't loaded a second time.
     *
     * It is possible to use paths to modules, such as 'foo/bar' (which would
     * search for 'foo/bar.php' under the library paths), since nothing
     * particularly gets in the way of this.
     *
     * @param   string  $name       Library name
     *
     * @throws  CSF_LibraryNotFound
     */
    public static function load_library($name)
    {
        // Stop if the library has already been loaded
        if (in_array($name, self::$_libraries))
            return;

        // Try and find the library
        foreach (array_reverse(self::$_library_paths) as $path)
        {
            $filepath = $path.DIRECTORY_SEPARATOR.$name.'.php';
            if (file_exists($filepath))
            {
                self::$_libraries[] = $name;
                require_once $filepath;
                return;
            }
        }

        // If we got this far, we failed
        throw new CSF_LibraryNotFound($name, self::$_library_paths);
    }


    /**
     * Register a module
     *
     * Store an object, making it accessible via the CSF module access methods.
     * Throws CSF_ModuleConflict if $name is already in use.
     *
     * @param   string  $name       Module name/alias
     * @param   mixed   $module     The module object
     * 
     * @throws  CSF_ModuleConflict
     */
    public static function register($name, $module)
    {
        // See if the name conflicts
        if (array_key_exists($name, self::$_modules))
            throw new CSF_ModuleConflict($name);

        // Register the module to the name
        self::$_modules[$name] = $module;
    }


    /**
     * Alias a module under another name
     *
     * @param   string  $name       Module name
     * @param   string  $alias      New module alias
     *
     * @throws  CSF_ModuleNotRegistered
     * @throws  CSF_ModuleConflict
     */
    public static function alias($name, $alias)
    {
        self::register($alias, self::get($name));
    }


    /**
     * See if a module exists/is loaded
     *
     * @param   string  $name       Module name
     * 
     * @return  bool    True if the module is loaded, otherwise false
     */
    public static function exists($name)
    {
        return array_key_exists($name, self::$_modules);
    }


    /**
     * Get a registered module
     *
     * Return the module registered with the specified name.  If the module
     * doesn't exist, throws CSF_ModuleNotRegistered.  If you don't
     * want the exception, call CSF::exists($name) first to see if the module
     * is registered.
     * 
     * @param   string  $name       Module name
     *
     * @return  mixed   The requested module
     *
     * @throws  CSF_ModuleNotRegistered
     */
    public static function get($name)
    {
        if (!self::exists($name))
            throw new CSF_ModuleNotRegistered($name);

        return self::$_modules[$name];
    }


    /**
     * Load a module
     *
     * CSF figures out which file to include by looking for "$module.php" inside
     * one of the module paths (searched in reverse order, in the same way as 
     * for loading libraries).  Once this has been done, CSF takes a very
     * "hands-off" approach - it exposes the module name as $MODULE_NAME, and 
     * the configuration (either as supplied or loaded from 
     * CSF::config("modules.$MODULE_NAME")) as $MODULE_CONF.
     *
     * It is the responsibility of the module file itself to create and register
     * object(s).  Other issues such as multiple declarations (if one module is
     * loaded under multiple aliases), module name conflicts, etc. must also be
     * handled by the module file.  An example module file:
     *
     * <code>
     * <?php
     * CSF::load_library('csf_dispatch');
     * CSF::register($MODULE_NAME, new CSF_Dispatch($MODULE_CONF));
     * ?>
     * </code>
     *
     * Throws CSF_ModuleNotFound if no matching file could be found.
     *
     * @param   mixed   $name       Either the string name of the module to
     *                              load, or a pair of (alias, module) to load
     *                              a module under another name.
     * @param   mixed   $conf       Module configuration object - if set to 
     *                              null, will attempt to load from global
     *                              configuration, defaulting to an empty array
     *
     * @throws  CSF_ModuleNotFound
     */
    public static function load_module($name, $conf = null)
    {
        // Normalise the $name argument
        if (!is_array($name))
            $name = array($name, $name);
        elseif (count($name) < 2)
            $name = array($name[0], $name[0]);

        // Extract both the module name and the actual module to load
        list($MODULE_NAME, $module) = $name;

        // Get the module configuration if it hasn't been supplied
        if (is_null($conf))
            $MODULE_CONF = self::config("modules.$MODULE_NAME", array());
        else
            $MODULE_CONF = $conf;

        // Find the path to the module file
        $MODULE_PATH = null;
        foreach (array_reverse(self::$_module_paths) as $path)
        {
            if (file_exists($path.DIRECTORY_SEPARATOR.$module.'.php'))
            {
                $MODULE_PATH = $path.DIRECTORY_SEPARATOR.$module.'.php';
                break;
            }
        }

        // Bail out if the file couldn't be found
        if (is_null($MODULE_PATH))
            throw new CSF_ModuleNotFound($module, self::$_module_paths);

        // "Load" the module - it's now the module's job to register itself
        require $MODULE_PATH;
    }
}


/**
 * Convenience function for accessing CSF modules
 *
 * If a module name is supplied, this returns the specified module, otherwise
 * it returns a basic CSF_Module which will allow access to modules via the
 * "property access" syntax, but nothing more.
 *
 * @param   string  $name       Module name
 * @return  mixed
 */
function CSF($name = null)
{
    if (is_null($name))
        return new CSF_Module();
    else
        return CSF::get($name);
}


/**
 * CSF module base class
 *
 * This base class provides some convenience methods for accessing modules
 * via other modules.  Inheriting from this class is not a prerequisite for
 * registering a module with CSF - PHP's lack of multiple inheritance support
 * would severely restrict the framework if it was compulsory.
 *
 * Just creating an instance of this class will let you access all modules via
 * the single object, e.g.:
 *
 * <code>
 * $csf = new CSF_Module();
 * $csf->foo->bar();
 * $csf->bar->baz();
 * </code>
 */
class CSF_Module
{
    /**
     * Get a module registered with CSF
     *
     * This function actually wraps CSF::get($name).  It is split out from
     * __get() so that other classes can override __get() in their own way
     * but still be able to access this functionality.
     *
     * @param   string  $name       The module name
     * @return  mixed
     */
    public function __get_csf_module($name)
    {
        return CSF::get($name);
    }


    /**
     * Allow $module->othermodule syntax
     *
     * Overrides the __get() method so that modules can be accessed using
     * $this->themodule.  If you want to override __get(), you can still
     * get this behaviour by calling $this->__get_csf_module($name).
     *
     * @param   string  $name       The module name
     * @return  mixed
     */
    public function __get($name)
    {
        return $this->__get_csf_module($name);
    }
}


/** Configuration item not found */
class CSF_ConfigNotFound extends Exception
{
    public function __construct($path)
    {
        parent::__construct("Configuration item at '$path' not found, and no ".
            'default value supplied');
    }
}

/** Library doesn't exist */
class CSF_LibraryNotFound extends Exception
{
    public function __construct($name, $paths)
    {
        parent::__construct("Library '$name' could not be loaded (PATH=".
            implode(PATH_SEPARATOR, $paths).')');
    }
}

/** Module already registered */
class CSF_ModuleConflict extends Exception
{
    public function __construct($name)
    {
        parent::__construct("Module '$name' already registered");
    }
}

/** Module not registered */
class CSF_ModuleNotRegistered extends Exception
{
    public function __construct($name)
    {
        parent::__construct("Module '$name' not registered");
    }
}

/** Module doesn't exist */
class CSF_ModuleNotFound extends Exception
{
    public function __construct($name, $paths)
    {
        parent::__construct("Module '$name' could not be loaded (PATH=".
            implode(PATH_SEPARATOR, $paths).')');
    }
}


/**
 * Exception handler
 *
 * Outputs some nice HTML instead of PHP's default whitespace-formatted output
 * to make exceptions readable.
 *
 * @param   Exception   $e      The exception
 */
function CSF_html_exception_handler($e)
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


/**
 * Library autoloader
 *
 * Uses spl_autoload_register to add an autoload function which will attempt
 * to load classes by calling CSF::load_library using the lowercase of the
 * class name.
 *
 * @param   string  $name   Class name
 * @return  boolean         TRUE on success, FALSE on failure
 */
function CSF_library_autoload($name)
{
    try
    {
        CSF::load_library(strtolower($name));
    }
    catch (CSF_LibraryNotFound $e)
    {
        return false;
    }
    return true;
}


/********************************************************************
 * CSF setup - extra bits of initialisation that MUST be done
 *******************************************************************/

// Add the default library and module paths
CSF::add_library_path(CSF_PATH.DIRECTORY_SEPARATOR.'libraries');
CSF::add_module_path(CSF_PATH.DIRECTORY_SEPARATOR.'modules');
