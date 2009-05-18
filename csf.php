<?php
/**
 * CodeScape Framework - A simple, flexible PHP framework
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008-2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/** Shorthand alias for DIRECTORY_SEPARATOR */
define('DIRSEP', DIRECTORY_SEPARATOR);

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
        if (self::config('core.use_html_exception_handler', true))
            set_exception_handler('csf_html_exception_handler');

        // TODO: Store library/module paths
        // TODO: Autoload libraries
        // TODO: Autoload modules
    }


    /**
     * Get configuration
     *
     * Get the value of the configuration item at the specified dot-separated
     * path.  The path is used to traverse the configuration array, and the
     * item at that particular point in the array is returned without any
     * further processing.  If the path could not be found, $default is 
     * returned if supplied, otherwise a csfConfigNotFoundException is thrown.
     *
     * @param   string  $path       Dot-separated path to configuration item
     * @param   mixed   $default    Value to return if not found
     * 
     * @return  mixed   Value at $path, or $default if not found
     *
     * @throws  csfConfigNotFoundException
     */
    public static function config($path, $default = null)
    {
        // Start at the root
        $item = self::$_config;

        // Traverse the array
        foreach (explode('.', $path) as $p)
        {
            if (!isset($item[$p]))
            {
                if (func_num_args() < 2)
                {
                    throw new csfConfigNotFoundException($path);
                }
                else
                {
                    return $default;
                }
            }
            else
            {
                $item = $item[$p];
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
     * @throws  csfLibraryNotFoundException
     */
    public static function load_library($name)
    {
        // Stop if the library has already been loaded
        if (in_array($name, self::$_libraries))
            return;

        // Try and find the library
        foreach (array_reverse(self::$_library_paths) as $path)
        {
            $filepath = $path.DIRSEP.$name.'.php';
            if (file_exists($filepath))
            {
                require_once $filepath;
                self::$_libraries[] = $name;
                return;
            }
        }

        // If we got this far, we failed
        throw new csfLibraryNotFoundException($name, self::$_library_paths);
    }


    /**
     * Register a module
     *
     * Store an object, making it accessible via the CSF module access methods.
     * Throws csfModuleConflictException if $name is already in use.
     *
     * @param   string  $name       Module name/alias
     * @param   mixed   $module     The module object
     * 
     * @throws  csfModuleConflictException
     */
    public static function register($name, $module)
    {
        // See if the name conflicts
        if (array_key_exists($name, self::$_modules))
            throw new csfModuleConflictException($name);

        // Register the module to the name
        self::$_modules[$name] = $module;
    }


    /**
     * Alias a module under another name
     *
     * @param   string  $name       Module name
     * @param   string  $alias      New module alias
     *
     * @throws  csfModuleNotRegisteredException
     * @throws  csfModuleConflictException
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
     * doesn't exist, throws csfModuleNotRegisteredException.  If you don't
     * want the exception, call CSF::exists($name) first to see if the module
     * is registered.
     * 
     * @param   string  $name       Module name
     *
     * @return  mixed   The requested module
     *
     * @throws  csfModuleNotRegisteredException
     */
    public static function get($name)
    {
        if (!self::exists($name))
            throw new csfModuleNotRegisteredException($name);

        return self::$_modules[$name];
    }


    /**
     * Load a module
     *
     * CSF figures out which file to include by looking for "$name.php" inside 
     * one of the module paths (searched in reverse order, in the same way as 
     * for loading libraries).  Once this has been done, CSF takes a very
     * "hands-off" approach - it exposes the module name (either $name, or 
     * $alias if supplied) as $MODULE_NAME, and the configuration (either as 
     * supplied or loaded from CSF::config("modules.$MODULE_NAME")) as
     * $MODULE_CONF.
     *
     * It is the responsibility of the module file itself to create and register
     * object(s).  Other issues such as multiple declarations (if one module is
     * loaded under multiple aliases), module name conflicts, etc. must also be
     * handled by the module file.
     *
     * Throws csfModuleNotFoundException if no matching file could be found.
     *
     * @param   string  $name       The name of the module to load
     * @param   mixed   $conf       Module configuration object - if set to 
     *                              null, will attempt to load from global
     *                              configuration, defaulting to an empty array
     * @param   string  $alias      Name to use for the loaded module, 
     *                              overriding $name if not null
     *
     * @throws  csfModuleNotFoundException
     */
    public static function load_module($name, $conf = null, $alias = null)
    {
        // Get the name the module should be registered as
        $MODULE_NAME = is_null($alias) ? $name : $alias;

        // Get the module configuration if it hasn't been supplied
        $MODULE_CONF = is_null($conf) 
                        ? self::config("modules.$MODULE_NAME", array()) 
                        : $conf;

        // Find the path to the module file
        $MODULE_PATH = null;
        foreach (array_reverse(self::$_module_paths) as $path)
        {
            if (file_exists($path.DIRSEP.$name.'.php'))
            {
                $MODULE_PATH = $path.DIRSEP.$name.'.php';
                break;
            }
        }

        // Bail out if the file couldn't be found
        if (is_null($MODULE_PATH))
            throw new csfModuleNotFoundException($name, self::$_module_paths);

        // "Load" the module - it's now the module's job to register itself
        require $MODULE_PATH;
    }
}


/**
 * Convenience function for accessing CSF modules
 *
 * @param   string  $name       Module name
 */
function CSF($name)
{
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
 *      $csf = new csfModule();
 *      $csf->foo->bar();
 *      $csf->bar->baz();
 */
class csfModule
{
    /**
     * Get a module registered with CSF
     *
     * This function actually wraps CSF::get($name).  It is split out from
     * __get() so that other classes can override __get() in their own way
     * but still be able to access this functionality.
     *
     * @param   string  $name       The module name
     */
    public function __get_csf_module($name)
    {
        return CSF::get($name);
    }


    /**
     * Override the __get() magic method
     *
     * Overrides the __get() method so that modules can be accessed using
     * $this->themodule.  If you want to override __get(), you can still
     * get this behaviour by calling $this->__get_csf_module($name).
     *
     * @param   string  $name       The module name
     */
    public function __get($name)
    {
        return $this->__get_csf_module($name);
    }
}


/** Configuration item not found */
class csfConfigNotFoundException extends Exception
{
    public function __construct($path)
    {
        parent::__construct("Configuration item at '$path' not found, and no ".
            'default value supplied');
    }
}

/** Library doesn't exist */
class csfLibraryNotFoundException extends Exception
{
    public function __construct($name, $paths)
    {
        parent::__construct("Library '$name' could not be loaded (PATH=".
            implode(PATH_SEPARATOR, $paths).')');
    }
}

/** Module already registered */
class csfModuleConflictException extends Exception
{
    public function __construct($name)
    {
        parent::__construct("Module '$name' already registered");
    }
}

/** Module not registered */
class csfModuleNotRegisteredException extends Exception
{
    public function __construct($name)
    {
        parent::__construct("Module '$name' not registered");
    }
}

/** Module doesn't exist */
class csfModuleNotFoundException extends Exception
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
 * Outputs some nice HTML instead of PHP's default 
 * all-smashed-together-on-one-line output.
 *
 * @param   Exception   $e      The exception
 */
function csf_html_exception_handler($e)
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


/********************************************************************
 * CSF setup - extra bits of initialisation that MUST be done
 *******************************************************************/
CSF::add_library_path(CSF_PATH.DIRSEP.'libraries');
CSF::add_module_path(CSF_PATH.DIRSEP.'modules');

