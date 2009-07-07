<?php
/**
 * CodeScape Framework - mcrypt wrapper module
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Mcrypt wrapper module
 *
 * This class provides a simple interface to commonly-used encryption methods 
 * using the mcrypt library, additionally using mhash for creating adequate
 * length keys. Each instance of the class acts as an encryption interface for
 * a particular cipher/mode/key combination.
 *
 * <code>
 * $enc = new Encrypt('secret key', MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC);
 * echo $enc->decrypt($enc->encrypt('some secret text'));
 * // Will output 'some secret text'
 * </code>
 *
 * Provides 3 interfaces:
 * <ul>
 *  <li><b>encrypt/decrypt:</b> Does exactly what it says on the tin - simple 
 *      encryption of values, returning a raw binary string</li>
 *  <li><b>encode/decode:</b> Encrypt/decrypt with base64 encoding</li>
 *  <li><b>encode_var/decode_var:</b> Encode/decode with variable 
 *      serialisation</li>
 * </ul>
 */
class Encrypt extends CSF_Module
{
    /** @var    resource    mcrypt module */
    protected $module;
    /** @var    int         Initialisation vector size */
    protected $iv_size;
    /** @var    string      Encryption key */
    protected $key;

    /**
     * Constructor
     *
     * Load the necessary encryption module and convert the key to the correct 
     * length.
     *
     * @param   string  $key        Encryption key
     * @param   string  $cipher     Encryption cipher
     * @param   string  $mode       Encryption mode
     * @throws  Exception
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

    /**
     * Encrypt value
     *
     * @param   mixed   $data       Simple value to encrypt
     * @return  string  Non-printable binary!
     */
    public function encrypt($data)
    {
        // Suppress warnings because this will fail on Windows!
        $iv = @mcrypt_create_iv($this->iv_size, MCRYPT_DEV_URANDOM);
        // Fallback to MCRYPT_RAND if MCRYPT_DEV_URANDOM fails
        if (empty($iv)) $iv = mcrypt_create_iv($this->iv_size, MCRYPT_RAND);

        mcrypt_generic_init($this->module, $this->key, $iv);
        $retval = $iv . mcrypt_generic($this->module, $data);
        mcrypt_generic_deinit($this->module);
        return $retval;
    }

    /**
     * Decrypt value
     *
     * @param   string  $data       Binary data to decrypt
     * @return  mixed
     */
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

    /**
     * Encrypt and base64 encode
     * @param   mixed   $data       Simple value to encrypt
     * @return  string  Base64-encoded encrypted value
     */
    public function encode($data)
    {
        return base64_encode($this->encrypt($data));
    }

    /**
     * Base64 decode and decrypt
     *
     * @param   string  $data       Base64-encoded encrypted value
     * @return  mixed   Decrypted decoded value
     */
    public function decode($data)
    {
        return $this->decrypt(base64_decode($data));
    }

    /**
     * Serialise, encrypt, encode
     *
     * @param   mixed   $data       Serialisable data to encrypt
     * @return  string  Base64-encoded encrypted data
     */
    public function encode_var($data)
    {
        return base64_encode($this->encrypt(serialize($data)));
    }

    /**
     * Decode, decrypt, unserialise
     *
     * @param   string  $data       Base64-encoded encrypted data
     * @return  mixed   Decrypted decoded data
     */
    public function decode_var($data)
    {
        return @unserialize($this->decrypt(base64_decode($data)));
    }
}
