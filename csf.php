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
    // Configuration
    protected static $_config = array();

    /*
     * Constructor
     *
     * Constructor is protected to prevent initialisation via any method other
     * than CSF::get() get_instance().
     */
    protected function __construct()
    {
    }

    /*
     * Singleton instance getter
     */
    public static function &CSF()
    {
        if ( !self::$_instance )
            trigger_error('CSF: Cannot get instance before calling init()',
                E_USER_ERROR);
        return self::$_instance;
    }

    /*
     * Initialiser
     *
     * Create CSF instance, store configuration information, return the
     * singleton instance.
     */
    public static function init($config)
    {
        // Error check - has CSF already been initialised?
        if ( self::$_instance )
            trigger_error('CSF: init() has already been called', 
                E_USER_ERROR);
        self::$_config = $config;
        self::$_instance = new CSF();
        return self::$_instance;
    }

    /*
     * Initialiser (using a YAML configuration file)
     */
    public static function init_YAML($file)
    {
        require_once(CSF_BASEDIR.DS.'lib'.DS.'spyc.php');
        self::init(Spyc::YAMLLoad($file));
    }

    /*
     * Get configuration item
     *
     * Get the config item at the specified path.  If it doesn't exist, return
     * the value of $default, but if $default is null then raise an error 
     * instead.
     */
    public static function config($path, $default = null)
    {
        // Start at the root
        $item = self::$_config;

        // Traverse the multi-dimensional array
        foreach ( explode('.', $path) as $p )
        {
            if ( !isset($item[$p]) )
            {
                if ( is_null($default) )
                    trigger_error("CSF: Config item '$path' does not exist",
                        E_USER_ERROR);
                else
                    return $default;
            }
            else
            {
                $item = $item[$p];
            }
        }

        return $item;
    }

    /*
     * Singleton instance getter
     *
     * Get the framework object, creating it if necessary.  This means the same 
     * method is used everywhere, regardless of whether or not it has been used
     * before.  Called as:
     *
     *      $CSF =& CSF::get_instance();
     */
    public static function &get_instance()
    {
        if ( is_null(self::$_instance) )
            self::$_instance = new CSF();
        return self::$_instance;
    }

    /*
     * Get module as property of framework object
     *
     * Overload __get() so that $F->foobar is the module that was loaded with
     * name 'foobar'.  To assign to a variable, use:
     *      
     *      $mod =& CSF::$foobar;
     * or
     *      $mod =& $CSF->foobar;
     */
    public static function __get($module)
    {
        // Give a meaningful error if the module isn't loaded
        if ( !array_key_exists($module, self::$_modules) )
        {
            trigger_error("Module '$module' not loaded!", E_USER_WARNING);
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
     * $class
     *      The class to load.
     *
     * $paths (default: array())
     *      An array of possible directories to try.
     */
    public static function load_class($class, $paths = array())
    {
        $class = strtolower($class);

        // Try all possible paths while the class doesn't exist
        while ( !class_exists($class) && ($path = array_shift($paths)) )
            if ( file_exists($path.DS."$class.php") )
                include_once($path.DS."$class.php");

        // Throw an error if the class still doesn't exist
        if ( !class_exists($class) )
            trigger_error("Class '$class' could not be loaded - please check "
                . "that it is defined or exists at '$class.php' in a supplied"
                . " path.", E_USER_ERROR);
    }

    /*
     * Make a module
     *
     * Create an instance of a module and return it, without plugging it into 
     * the framework object.
     *
     * $module
     *      The name of the module to load.  This can be in the format 
     *      `MyModule`, `subdir/MyModule` etc.  The "module name" part will
     *      be converted to lowercase, and the class loaded from 
     *      `<basedir>/$module.php`  (e.g. `.../subdir/mymodule.php`)
     *
     * $args (default: array())
     *      Arguments to pass to the constructor of the module.
     *
     * $basedir (default: `CSF_BASEDIR/modules`, fallback to `CSF_BASEDIR/core`)
     *      The base directory to load the module from.  The path obtained from
     *      $module is added to this to get the full path to the module.
     */
    public static function make_module($module, 
                                       $args = array(), 
                                       $basedir = null)
    {
        // Split the module name
        $class = basename($module);
        $dir = ($class == $module) ? '' : dirname($module);

        // Decide the paths based on whether or not one was supplied
        if ( $basedir )
        {
            // If a path is supplied, use it
            $paths = array($basedir.DS.$dir);
        }
        else
        {
            // Paths if none supplied - CSF modules dir, with fallback to 
            // core - should allow core modules to be overridden without 
            // removing them
            $paths = array(
                CSF_BASEDIR.DS.'modules'.DS.$dir,
                CSF_BASEDIR.DS.'core'.DS.$dir,
            );
        }

        // Make sure the class is loaded
        self::load_class($class, $paths);

        // Instantiate the class
        $ref = new ReflectionClass($class);
        return $ref->newInstanceArgs($args);
    }

    /*
     * Load a module
     *
     * $module
     *      The module to load - see CSF::make_module().
     *
     * $args (default: array())
     *      Arguments to pass to the constructor of the module.
     *
     * $alias (default: class name from $module)
     *      Name to give the module, for accessing it via `$csf->mymodule` or
     *      `CSF::$mymodule`.  Useful for things like database classes where 
     *      more than one instance may be needed.
     *
     * $basedir
     *      The base directory to load the module from - see CSF::make_module()
     */
    public static function load_module($module,
                                       $args = array(), 
                                       $alias = null,
                                       $basedir = null)
    {
        // Get the access name for the module
        $name = empty($alias) ? basename($module) : $alias;

        // Check if the module is already loaded
        if ( !array_key_exists($name, self::$_modules) )
        {
            // Make the module
            self::$_modules[$name] =& self::make_module($module, $args, $basedir);
        }

        // Return the module - it should be loaded by now!
        return self::$_modules[$name];
    }

    /*
     * Link an existing module under a new name
     */
    public static function link_module($name, $newname)
    {
        if ( !array_key_exists($name, self::$_modules) )
            trigger_error(
                sprintf("Cannot link module '%s' to '%s': '%s' not loaded", 
                    $name, $newname, $name), E_USER_ERROR);

        if ( array_key_exists($newname, self::$_modules) )
            trigger_error(
                sprintf("Cannot link module '%s' to '%s': '%s' already exists", 
                    $name, $newname, $newname), E_USER_ERROR);

        self::$_modules[$newname] =& self::$_modules[$name];
    }

    /*
     * Check if a module is loaded
     */
    public static function module_exists($name)
    {
        return array_key_exists($name, self::$_modules);
    }
}


/*
 * CodeScape Framework module class
 *
 * All modules should derive from this class.  Provides $this->CSF->module as a
 * convenient way of accessing all other loaded modules.  May include more stuff
 * at a later date.
 *
 * Don't forget to call parent::__construct() in subclass constructors!
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
     */
    public function __construct()
    {
        $this->CSF =& CSF::get_instance();

        foreach  ( $this->_depends as $dep )
        {
            $obj =& $this->CSF->__get($dep['name']);
            if ( !$obj )
                trigger_error("Dependency '{$dep['name']}' with interface "
                    . "'{$dep['interface']}' not loaded", E_USER_ERROR);

            if ( !($obj instanceof $dep['interface']) )
                trigger_error("Dependency '{$dep['name']}' does not implement "
                    . "interface '{$dep['interface']}'", E_USER_ERROR);
        }
    }
}


/*
 * CodeScape Framework database module class
 *
 * Extend PDO to provide some useful extra convenience features.  All 
 * database-specific modules should extend from this, but it can also be used
 * as-is.  Pseudo-inherits from CSF_Module.
 */
class CSF_DB extends PDO
{
    /*
     * Constructor
     *
     * Get the framework object reference, set up the PDO stuff.
     */
    public function __construct($dsn, $user = null, $pass = null)
    {
        // Why does this work?  Probably because of PHP's *amazing* OOP 
        // implementation.  (Or more likely because of the fact PHP5's OOP is 
        // a big hack.)  Who cares - it's useful!  Inheritance without 
        // inheritance.
        //CSF_Module::__construct();

        // PDO constructor
        parent::__construct($dsn, $user, $pass);

        // Most informative error level
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_WARNING);
        // Use custom statement class
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('CSF_DB_Statement'));
    }

    /*
     * Execute query
     *
     * Run the supplied query (first argument) as a prepared statement, 
     * executing it once with the rest of the arguments supplied.  Using this
     * should remove any need to EVER do anything unsafe like:
     *
     *      $db->query("SELECT * FROM foo WHERE id = $unsafe_var");
     *
     * No escaping needs to be done on the arguments passed to this method.  If
     * the second argument is the last and is an array, the query is executed 
     * using the contents of this array, otherwise the rest of the arguments to
     * this method are used.
     */
    public function query()
    {
        $argv = func_get_args();
        $query = array_shift($argv);
        $stmt = $this->prepare($query);

        if ( count($argv) == 1 && is_array($argv[0]) )
            $stmt->execute($argv[0]);
        else
            $stmt->execute($argv);
        return $stmt;
    }

    /*
     * Provide access to PDO's query() method
     */
    public function pdo_query()
    {
        $argv = func_get_args();
        return call_user_func_array(array('PDO', 'query'), $argv);
    }
}


/*
 * CodeScape Framework database statement class
 * 
 * Used in place of PDOStatement for CSF_DB modules - extends PDOStatement, 
 * adding some useful convenience features.
 */
class CSF_DB_Statement extends PDOStatement
{
    /*
     * Fetch all rows, including row count
     *
     * Convenience method returning an associative array with 2 elements:
     *
     *      'data'      => array of rows as associative arrays
     *      'rowcount'  => number of rows in the result set
     */
    public function fetchAllRows()
    {
        $ret = array();
        $ret['data'] = $this->fetchAll(PDO::FETCH_ASSOC);
        $ret['rowcount'] = count($ret['data']);
        return $ret;
    }

    /*
     * Execute statement
     *
     * Override PDOStatement::execute() with a version which allows both single
     * and variable argument count calls, e.g.
     *
     *      $stmt->execute(array('foo' => 'bar'));
     * or
     *      $stmt->execute(array('bar', 'baz'));
     * or
     *      $stmt->execute('bar', 'baz');
     */
    public function execute()
    {
        $argv = func_get_args();
        if ( count($argv) == 1 && is_array($argv[0]) )
            return parent::execute($argv[0]);
        else
            return parent::execute($argv);
    }
}
?>
