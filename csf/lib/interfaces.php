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
 * Request module interface
 */
interface CSF_IRequest
{
    // Constructor
    public function __construct($fix_magic_quotes = false);

    // Request variables
    public function get($vars);
    public function post($vars);
    public function request($vars);
    public function cookie($vars);

    // Other request info
    public function ip_address();
    public function is_method($method);
}

/*
 * Session module interface
 */
interface CSF_ISession
{
    // Constructor - retrieve session data
    public function __construct();

    // Manipulate variables
    public function get($name);
    public function set($name, $value);
    
    // Override builtins
    public function __get($name);
    public function __set($name, $value);
    public function __isset($name);
    public function __unset($name);
    
    // Save session data
    public function save();
}
?>
