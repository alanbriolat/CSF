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
 * Request helper class
 *
 * Provide more intuitive access to various request variables and properties.
 */
class Request extends CSF_Module implements CSF_IRequest
{
    /*
     * Constructor
     *
     * Populate the object with data.  Fix escaped data in the GET, POST and 
     * COOKIE arrays (and therefore REQUEST too).
     */
    public function __construct($fix_magic_quotes = false)
    {
        parent::__construct();

        // Magic with references - fix the damage that magic_quotes_gpc=On does.
        if ( $fix_magic_quotes && get_magic_quotes_gpc() == 1 )
        {
            $a = array(&$_GET, &$_POST, &$_COOKIE, &$_REQUEST);
            foreach ( $a as &$array )
                foreach ( $array as &$value )
                    $value = stripslashes($value);
        }
    }

    /*
     * Get POST variable(s)
     */
    public function post($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_POST[$key];
            return $ret;
        }
        else
        {
            return $_POST[$vars];
        }
    }

    /*
     * Get GET variable(s)
     */
    public function get($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_GET[$key];
            return $ret;
        }
        else
        {
            return $_GET[$vars];
        }
    }

    /*
     * Get REQUEST variable(s)
     */
    public function request($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_REQUEST[$key];
            return $ret;
        }
        else
        {
            return $_REQUEST[$vars];
        }
    }

    /*
     * Get COOKIE variable(s)
     */
    public function cookie($vars)
    {
        if ( is_array($vars) )
        {
            $ret = array();
            foreach ( $vars as $key )
                $ret[$key] = $_COOKIE[$key];
            return $ret;
        }
        else
        {
            return $_COOKIE[$vars];
        }
    }

    /*
     * Get IP address
     */
    public function ip_address()
    {
        return $_SERVER['REMOTE_ADDR'];
    }

    /*
     * Is this request a particular method?
     */
    public function is_method($method)
    {
        return strtolower($method) == $_SERVER['REQUEST_METHOD'];
    }
}

?>
