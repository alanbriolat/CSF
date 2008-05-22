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
 * Common useful functions
 */

/*
 * Generate a type 1 UUID (see RFC4122)
 * 
 * This will only work on systems that have e2fsprogs/libuuid installed, and
 * therefore almost all UNIX-like systems.
 */
function uuid1()
{
    return trim(exec('uuidgen -t'));
}

/* 
 * Create a SHA256 hash of a string
 * 
 * It's better than MD5 and SHA1, so use it!
 */
function sha256($string)
{
	if (!function_exists('mhash')) {
		trigger_error('mhash not supported by this PHP installation');
	}
	
	return bin2hex(mhash(MHASH_SHA256, $string));
}
?>
