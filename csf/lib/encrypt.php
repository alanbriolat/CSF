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
 * Mcrypt wrapper class
 *
 * This class provides a simple interface to commonly-used encryption methods 
 * using the mcrypt library, and also mhash for creating adequate key lengths.
 * Each instance ofth e class acts as an encryption interface for a particular
 * cipher/mode/key combination.
 *
 * e.g.
 *      $enc = new Encrypt('secret key', MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
 *      echo $enc->decrypt($enc->encrypt('some secret text'));
 *      // Will output 'some secret text'
 *
 * encrypt/decrypt:
 *      Does exactly what it says on the tin
 * encode/decode:
 *      Encrypt/decrypt with base64 encoding
 * encode_var/decode_var:
 *      Encode/decode with variable serialisation
 */
class Encrypt
{
    // Encryption properties
    private $module, $iv_size, $key;

    /*
     * Constructor
     *
     * Load the necessary encryption module and convert the key to the correct 
     * length.
     */
    public function __construct($key, 
                                $cipher = MCRYPT_RIJNDAEL_256, 
                                $mode = MCRYPT_MODE_CBC)
    {
        //  Open the module
        $this->module = mcrypt_module_open($cipher, '', $mode, '');
        if ( $this->module === FALSE )

            throw new Exception("Encrypt: Could not load cipher '$cipher'".
               " in mode '$mode'");

        //  Make the correct length key
        $this->key = substr(mhash(MHASH_SHA256, $key), 0, 
                            mcrypt_enc_get_key_size($this->module));

        //  Store the IV size
        $this->iv_size = mcrypt_enc_get_iv_size($this->module);
    }

    public function encrypt($data)
    {   
        $iv = mcrypt_create_iv($this->iv_size, MCRYPT_DEV_URANDOM);
        mcrypt_generic_init($this->module, $this->key, $iv);
        $retval = $iv . mcrypt_generic($this->module, $data);
        mcrypt_generic_deinit($this->module);
        return $retval;
    }

    public function decrypt($data)
    {
        $iv = substr($data, 0, $this->iv_size);
        mcrypt_generic_init($this->module, $this->key, $iv);
        $retval = rtrim(
            mdecrypt_generic($this->module, substr($data, $this->iv_size)),
            "\0");
        mcrypt_generic_deinit($this->module);
        return $retval;
    }

    public function encode($data)
    {
        return base64_encode($this->encrypt($data));
    }

    public function decode($data)
    {
        return $this->decrypt(base64_decode($data));
    }

    public function encode_var($data)
    {
        return base64_encode($this->encrypt(serialize($data)));
    }

    public function decode_var($data)
    {
        return unserialize($this->decrypt(base64_decode($data)));
    }
}
