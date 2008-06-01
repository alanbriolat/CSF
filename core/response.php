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
 * Response module
 *
 * Handle redirects, sending data back to the client, response mime type, etc.
 */
class Response extends CSF_Module
{
    // Mime type to send content as
    protected $mimetype = 'text/html';

    /*
     * Send data
     */
    public function send($data, $mimetype = null)
    {
        $mimetype = $mimetype ? $mimetype : $this->mimetype;
        header('Content-Type: '.$mimetype);
        echo $data;
    }

    /*
     * Set mime tye
     */
    public function set_mimetype($mimetype)
    {
        $this->mimetype = $mimetype;
    }
}
?>
