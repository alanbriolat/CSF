<?php
/**
 * CodeScape Framework - Database module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * CodeScape Framework database module
 *
 * Extend PDO to provide some useful extra convenience features.  All 
 * database-specific modules should extend from this, but it can also be used
 * as-is.  May pseudo-inherit from CSF_Module in future (by calling 
 * CSF_Module::__construct()), but not necessary for now.
 */
class CSF_DB extends PDO
{
    /**
     * Constructor
     *
     * Get the framework object reference, call PDO constructor, and generally
     * configure PDO.
     * 
     * @param   string  $dsn        Database source name
     * @param   string  $user       Database username (if required)
     * @param   string  $pass       Database password (if required)
     */
    public function __construct($dsn, $user = null, $pass = null)
    {
        // PDO constructor
        parent::__construct($dsn, $user, $pass);

        // Most informative error level
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Use custom statement class
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, 
            array('CSF_DB_Statement'));
    }

    /**
     * Execute query
     *
     * Run the supplied query (first argument) as a prepared statement, 
     * executing it once with the rest of the arguments supplied.  Using this
     * should remove any need to EVER do anything unsafe like:
     *
     * <code>
     * $db->query("SELECT * FROM foo WHERE id = $unsafe_var");
     * </code>
     *
     * No escaping needs to be done on the arguments passed to this method.  If
     * the second argument is the last and is an array, the query is executed 
     * using the contents of this array, otherwise the rest of the arguments to
     * this method are used.
     *
     * <code>
     * $db->query('SELECT foo FROM bar WHERE id = ? AND baz < ?', 12, 15);
     * // ... is equivalent to ...
     * $db->query('SELECT foo FROM bar WHERE id = ? AND baz < ?', array(12, 15));
     * </code>
     *
     * @param   string  $query      The query to execute
     * @return  CSF_DB_Statement
     */
    public function query($query)
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

    /**
     * PDO query
     *
     * Provide access to PDO's query() method in case it's needed.
     * 
     * @return  PDOStatement
     */
    public function pdo_query()
    {
        $argv = func_get_args();
        return call_user_func_array(array('PDO', 'query'), $argv);
    }
}


/**
 * CodeScape Framework database statement
 * 
 * Used in place of PDOStatement for CSF_DB modules - extends PDOStatement, 
 * adding some useful convenience features.
 */
class CSF_DB_Statement extends PDOStatement
{
    /**
     * Fetch all rows and row count
     *
     * Convenience method returning an associative array with 2 elements:
     * <ul>
     *  <li><var>data</var> - array of rows as associative arrays</li>
     *  <li><var>rowcount</var> - number of rows in <var>data</var>
     * </ul>
     *
     * @return  array
     */
    public function fetchAllRows()
    {
        $ret = array();
        $ret['data'] = $this->fetchAll(PDO::FETCH_ASSOC);
        $ret['rowcount'] = count($ret['data']);
        return $ret;
    }

    /**
     * Execute statement
     *
     * Override PDOStatement::execute() with a version which allows both single
     * and variable argument count calls, e.g.
     *
     * <code>
     * $stmt->execute(array('foo' => 'bar'));
     * $stmt->execute(array('bar', 'baz'));
     * $stmt->execute('bar', 'baz');
     * </code>
     *
     * @return  CSF_DB_Statement
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
