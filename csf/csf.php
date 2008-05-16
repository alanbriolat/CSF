<?php
/*
 * CodeScape PHP Framework - A simple PHP web framework
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
define('CSF_BASEDIR', dirname(__FILE__));

/*
 * The main CodeScape Framework class, implemented as a singleton.
 */
class CSF
{
    // Singleton instance
    private static $_instance = null;

    // Loaded modules
    private static $_modules = array();

    /*
     * Constructor
     *
     * Constructor is protected to prevent initialisation via any method other
     * than CSF::get_instance().
     */
    protected function __construct()
    {
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

    /*
     * Constructor
     *
     * Get the CSF object
     */
    public function __construct()
    {
        $this->CSF =& CSF::get_instance();
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
        CSF_Module::__construct();

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
    public function query_exec()
    {
        $argv = func_get_args();
        $query = array_shift($argv);
        $stmt = $this->prepare($query);

        if ( !empty($argv) && is_array($argv[0]) )
            $stmt->execute($argv[0]);
        else
            $stmt->execute($argv);
        return $stmt;
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
     * Returns an associative array with 2 elements:
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
     * Convenience method giving a variable-arguments version of
     * PDOStatement::execute($array).
     *
     * Call as $stmt->exec($arg1, $arg2, $arg3).
     *
     * Use $stmt->execute() if you want to use named arguments in
     */
    public function exec()
    {
        return $this->execute(func_get_args());
    }
}
?>
