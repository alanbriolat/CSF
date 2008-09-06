<?php
/**
 * CodeScape Framework - Common function library
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Generate a type 1 UUID (see RFC4122)
 * 
 * This will only work on systems that have e2fsprogs/libuuid installed, and
 * therefore almost all UNIX-like systems.  Possibly not the most efficient way 
 * to get a UUID...
 *
 * @return  string
 * @todo    Include PHP UUID library in CSF?
 */
function uuid1()
{
    return trim(exec('uuidgen -t'));
}

/**
 * Create a SHA256 hash of a string
 * 
 * Uses the PHP mhash library to create SHA256 hashes.  It's better than MD5 
 * and SHA1, and for small amounts of data isn't much slower, so use it!
 *
 * @param   string  $string     String to hash
 * @return  string  Hexadecimal representation of SHA256 hash
 */
function sha256($string)
{
    if (!function_exists('mhash')) {
        trigger_error('mhash not supported by this PHP installation');
    }

    return bin2hex(mhash(MHASH_SHA256, $string));
}
?>
