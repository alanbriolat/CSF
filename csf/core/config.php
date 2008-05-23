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
 * Simple configuration object
 */
class Config extends CSF_Module implements CSF_IConfig
{
    // Configuration data being wrapped
    protected $_config = array();

    /*
     * Constructor
     *
     * Store the config data
     */
    public function __construct($config)
    {
        $this->_config = $config;
    }

    /*
     * Get config data
     *
     * Return the data at the path specified, traversing arrays by keys.  
     * Assumes that exists($path) == true.
     */
    public function get($path)
    {
        // Root config item
        $item = $this->_config;

        // Traverse array
        foreach ( explode('.', $path) as $p )
            $item = $item[$p];
        
        return $item;
    }

    /*
     * Check if a config item exists
     *
     * Traverse the multi-dimensional array and return true if the item 
     * specified by $path exists.
     */
    public function exists($path)
    {
        // Root of the config
        $item = $this->_config;

        // Traverse multi-dimensional array
        foreach ( explode('.', $path) as $p )
            if ( array_key_exists($p, $item) )
                $item = $item[$p];
            else
                return false;
        return true;
    }
}
?>
