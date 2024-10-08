<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
TopoMojo Plugin for Moodle

Copyright 2024 Carnegie Mellon University.

NO WARRANTY. THIS CARNEGIE MELLON UNIVERSITY AND SOFTWARE ENGINEERING INSTITUTE MATERIAL IS FURNISHED ON AN "AS-IS" BASIS. 
CARNEGIE MELLON UNIVERSITY MAKES NO WARRANTIES OF ANY KIND, EITHER EXPRESSED OR IMPLIED, AS TO ANY MATTER INCLUDING, BUT NOT LIMITED TO, 
WARRANTY OF FITNESS FOR PURPOSE OR MERCHANTABILITY, EXCLUSIVITY, OR RESULTS OBTAINED FROM USE OF THE MATERIAL. 
CARNEGIE MELLON UNIVERSITY DOES NOT MAKE ANY WARRANTY OF ANY KIND WITH RESPECT TO FREEDOM FROM PATENT, TRADEMARK, OR COPYRIGHT INFRINGEMENT.
Licensed under a GNU GENERAL PUBLIC LICENSE - Version 3, 29 June 2007-style license, please see license.txt or contact permission@sei.cmu.edu for full 
terms.

[DISTRIBUTION STATEMENT A] This material has been approved for public release and unlimited distribution.  
Please see Copyright notice for non-US Government use and distribution.

This Software includes and/or makes use of Third-Party Software each subject to its own license.

DM24-1175
*/

namespace mod_topomojo\utils;

defined('MOODLE_INTERNAL') || die();

 /**
  * Utils class for using json
  *
  * @package     mod_topomojo
  * @copyright   2024 Carnegie Mellon University
  * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class jsonlib {

    /** @var stdClass $response class to hold resonse information */
    protected $response;

    /** @var array $status codes Array to hold different status codes to return with response */
    protected $statuscodes = [
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        426 => 'Upgrade Required',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended',
    ];


    /**
     * Construct the lib and set up response
     *
     */
    public function __construct() {

        $this->response = new \stdClass();
        $this->response->status = "OK";

    }

    /**
     * Set a key/value pair on the response object
     *
     * @param string $key The key to set on the response object
     * @param mixed  $value The value to set for the key provided on the response object
     */
    public function set($key, $value) {
        $this->response->$key = $value;
    }

    /**
     * Sends an error response, also terminates request with an exit();
     *
     * Also dies on json_encode error
     *
     * @param string $message The error message to send
     */
    public function send_error($message) {

        $this->response->status = "error";
        $this->response->message = $message;

        $response = json_encode($this->response);
        if ($response === false) { // Check for error

            $message = $this->get_json_error();
            $this->send_headers(500);
            die($message);
        }
        $this->send_headers(500);
        echo $response;

        exit();
    }

    /**
     * Sends a json response of whatever is in the response class
     *
     * This function automatically terminates script running as we are sending the response
     */
    public function send_response() {

        $response = json_encode($this->response);
        if ($response === false) { // Check for error

            $response = $this->get_json_error(); // Return error as bare text since json_encode isn't working
            $this->send_headers(500);
        }

        // Set up headers for 200 response as well as tell the browser to not cache the content
        $this->send_headers(200);
        echo $response;

        exit();
    }

    /**
     * Checks for the last json error to then return a 500 error with message
     *
     *
     * @return string
     */
    protected function get_json_error() {
        switch (json_last_error()) {
            case JSON_ERROR_NONE:
                $message = 'no error';
                break;
            case JSON_ERROR_DEPTH:
                $message = ' - Maximum stack depth exceeded';
                break;
            case JSON_ERROR_STATE_MISMATCH:
                $message = ' - Underflow or the modes mismatch';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $message = ' - Unexpected control character found';
                break;
            case JSON_ERROR_SYNTAX:
                $message = ' - Syntax error, malformed JSON';
                break;
            case JSON_ERROR_UTF8:
                $message = ' - Malformed UTF-8 characters, possibly incorrectly encoded';
                break;
            default:
                $message = ' - Unknown error';
                break;
        }

        return $message;
    }

    /**
     * Builds and sends headers for the response sending
     * Will only send headers for a valid status code defined above
     *
     * @param int $status The status code to send with the response
     *
     */
    private function send_headers($status) {

        if (!empty($this->statuscodes[$status])) {
            $stringstatus = $status . ' ' . $this->statuscodes[$status];
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $stringstatus, true, $status);
            header('cache-control: private, max-age=0, no-cache');
            header('pragma: no-cache');
            header('Content-Type: application/json; charset=utf-8');
        }

    }

}
