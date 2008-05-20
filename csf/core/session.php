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
 * Session module interface
 *
 * TODO: Move this
 */
interface CSF_ISession
{
    // Constructor - retrieve session data
    public function __construct();

    // Manipulate variables
    public function get($name);
    public function set($name, $value);
    public function isset($name);
    public function unset($name);
    
    // Override builtins
    public function __get($name);
    public function __set($name, $value);
    public function __isset($name);
    public function __unset($name);
    
    // Save session data
    public function save();
}

/*
 * Persistent session data module
 *
 * Provide transparent access to persistent data
 */
class Session extends CSF_Module implements CSF_ISession
{
}
?>
