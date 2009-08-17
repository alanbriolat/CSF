<?php
/**
 * CodeScape Framework - URL functions
 *
 * @package     CSF
 * @author      Alan Briolat <alan@codescape.net>
 * @copyright   (c) 2008-2009, Alan Briolat
 * @license     http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Convert URI to a full URL
 *
 * Convert a "site URL" to a full URL, using the values defined at
 * url_functions.base_url and url_functions.secure_base_url in the 
 * configuration.  If base_url is not set, it defaults to "/" - relative to the 
 * root of the host.  If secure_base_url is not set, base_url with the protocol
 * replaced with "https" is used.
 *
 * The <var>$secure</var> argument determines which base URL is used.  TRUE and
 * FALSE (boolean, not strings) force secure and normal, respectively.  If 
 * 'auto' is specified (default), then the secure URL is used if the current page
 * was served over HTTPS.
 *
 * @param   string  $uri
 * @param   mixed   $secure     Whether or not to use the secure base URL
 * @return  string
 */
function site_url($uri, $secure = 'auto')
{
    $uri = ltrim($uri, '/');

    // Check if this should use the secure URL
    $use_ssl = ($secure === TRUE) || 
        ($secure === 'auto' && isset($_SERVER['HTTPS']));

    if ($use_ssl)
    {
        return CSF::config('url_functions.secure_base_url', 
            preg_replace('#\w{1,10}://#A', 'https://',
                CSF::config('url_functions.base_url', '/'))).$uri;
    }
    else
    {
        return CSF::config('url_functions.base_url', '/').$uri;
    }
}

/**
 * "External" URI redirect
 *
 * Instruct the client to go to a different URI using a "HTTP/1.1 302 Found" 
 * status line followed by a "Location" header.  This automatically passes the 
 * URI through site_url() to create a URL.
 *
 * @param   string  $uri    The URI to redirect to
 * @see     site_url
 */
function redirect($uri)
{
    // Send the status code
    header('HTTP/1.1 302 Found');
    // Send the new location
    header('Location: ' . site_url($uri));
    // And for the clients that are too old/broken to understand...
    echo 'Redirecting to <a href="'.site_url($uri).'">'.site_url($uri).'</a> ...';
}

/**
 * Create HTML anchor
 *
 * A convenience function wrapping {@link site_url} to create an anchor tag
 * linking to a particular site URI.
 *
 * @param   string  $uri
 * @param   string  $text       Anchor text, NULL means use <var>$uri</var>
 * @param   mixed   $secure     See {@link site_url}
 * @return  string
 * @see     site_url
 */
function anchor($uri, $text = NULL, $secure = 'auto')
{
    return '<a href="' . site_url($uri, $secure) . '">' 
        . ($text ? $text : $uri) . '</a>'; 
}

?>
