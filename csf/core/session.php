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
 * Persistent session data module
 *
 * Provide transparent access to persistent data
 */
class Session extends CSF_Module // implements CSF_ISession
{
    protected $_depends = array(
        array('name' => 'config', 'interface' => 'CSF_IConfig'),
    );

    /*
     * Constructor
     *
     * Make sure there is a connection to a session database, load session data.
     */
    public function __construct()
    {
        $sessname = 'foobar';
        parent::__construct();

        // Create a database connection if there isn't one
        if ( !$this->CSF->module_exists('session_db') )
        {
            // Path to an SQLite session database
            $sessdbpath = CSF_BASEDIR . DIRECTORY_SEPARATOR . 'session.db';
            
            if ( !file_exists($sessdbpath) )
            {
                $db =& $this->CSF->load_module('CSF_DB', 
                    array("sqlite:$sessdbpath"), 'session_db');
                // Create the table
                //  - use ROWID as the ID
                //  - last_activity is stored in UNIX timestamp format
                $db->query("
                    CREATE TABLE session_$sessname (
                        ip_address      VARCHAR(15) NOT NULL,
                        user_agent      VARCHAR(64) NOT NULL,
                        last_activity   BIGINT NOT NULL,
                        data            TEXT
                    )");
            }
            else
            {
                // Just open the database
                $db =& $this->CSF->load_module('CSF_DB', 
                    array("sqlite:$sessdbpath"), 'session_db');
            }
        }
    }
}
?>
