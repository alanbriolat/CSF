<?php
/**
 * CodeScape Framework - Database library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * DB class
 *
 * The csfDB class extends the PDO database class, providing some useful extra
 * convenience functions.
 */
class csfDB extends PDO
{
    /** @var    array   Options array */
    protected $_options = array(
        // Data Source Name (DSN) compatible with PDO
        'dsn' => 'sqlite::memory:',
        // Database login credentials
        'user' => null,
        'pass' => null,
        // PDO configuration options
        'error_mode' => PDO::ERRMODE_EXCEPTION,
    );


    /**
     * Constructor
     *
     * Call the PDO constructor, generally configure PDO.
     */
    public function __construct($options = array())
    {
        // Use supplied options
        $this->_options = array_merge($this->_options, $options);

        // PDO constructor
        parent::__construct($this->_options['dsn'],
                            $this->_options['user'],
                            $this->_options['pass']);

        // Set error method
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Use custom statement class
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, array('csfDBStatement'));
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
     * @return  csfDBStatement
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
     * Provide un-mangled access to PDO's query() function
     *
     * @return  PDOStatement
     */
    public function query_()
    {
        return call_user_func_array(array('PDO', 'query'), func_get_args());
    }
}


/**
 * Statement class
 *
 * This class extends the PDOStatement class, adding some useful convenience
 * functions.
 */
class csfDBStatement extends PDOStatement
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
     * @return  csfDBStatement
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
