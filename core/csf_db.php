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
 * CodeScape Framework database module class
 *
 * Extend PDO to provide some useful extra convenience features.  All 
 * database-specific modules should extend from this, but it can also be used
 * as-is.  May pseudo-inherit from CSF_Module in future (by calling 
 * CSF_Module::__construct()), but not necessary for now.
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
        // PDO constructor
        parent::__construct($dsn, $user, $pass);

        // Most informative error level
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // Use custom statement class
        $this->setAttribute(PDO::ATTR_STATEMENT_CLASS, 
            array('CSF_DB_Statement'));
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
