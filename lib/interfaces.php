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
 * Encryption module interface
 */
interface CSF_IEncrypt
{
    // Constructor should at least accept a key
    public function __construct($key);

    // Encrypt/decrypt a value
    public function encrypt($data);
    public function decrypt($data);

    // Encrypt/decrypt with base64 ciphertext encoding
    public function encode($data);
    public function decode($data);

    // Encrypt/decrypt (with base64 encoding) an entire variable, regardless
    // of type etc. (for example, by serialising)
    public function encode_var($data);
    public function decode_var($data);
}

/*
 * Request module interface
 */
interface CSF_IRequest
{
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

/*
 * Controller interface
 */
interface CSF_IController
{
    // URL dispatch
    public function dispatch_url($url);
}

/*
 * View/templating interface
 */
interface CSF_IView
{
    // Manipulate context variables
    public function get($name);
    public function set($name, $value);
    public function __get($name);
    public function __set($name, $value);
    public function __isset($name);
    public function __unset($name);

    // Render a template, optionally overriding the context
    public function render($template, $context = null);
}
?>
