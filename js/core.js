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

/**
 * @package   mod_topomojo
 * @author    John Hoopes <moodle@madisoncreativeweb.com>
 * @copyright 2014 University of Wisconsin - Madison
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// assign top level namespace
var topomojo = topomojo || {};
topomojo.vars = topomojo.vars || {};

/**
 * Adds a variable to the topomojo object
 *
 * @param name the name of the property to set
 * @param value the value of the property to set
 * @returns {topomojo}
 */
topomojo.set = function (name, value) {
    this.vars[name] = value;
    return this;
};

/**
 * Gets a variable from the topomojo object
 *
 * @param name
 * @returns {*}
 */
topomojo.get = function (name) {
    if (typeof this.vars[name] === 'undefined') {
        return 'undefined';
    }

    return this.vars[name];
};

/**
 * Defines ajax functions in its namespace
 *
 *
 * @type {{httpRequest: {}, init: init, create_request: create_request}}
 */
topomojo.ajax = {

    httpRequest: {},

    init: function () {

    },
    /**
     * Create and send a request
     * @param url the path to the file you are calling, note this is only for local requests as siteroot will be added to the front of the url
     * @param params the parameters you'd like to add.  This should be an object like the following example
     *
     *          params = { 'id' : 1, 'questionid': 56, 'answer': 'testing' }
     *
     *                  will convert to these post parameters
     *
     *          'id=1&questionid=56&answer=testing'
     *
     * @param callback callable function to be the callback onreadystatechange, must accept httpstatus and the response
     */
    create_request: function (url, params, callback) {

        // re-init a new request ( so we don't have things running into each other)
        if (window.XMLHttpRequest) { // Mozilla, Safari, ...
            var httpRequest = new XMLHttpRequest();
            if (httpRequest.overrideMimeType) {
                httpRequest.overrideMimeType('text/xml');
            }
        } else if (window.ActiveXObject) { // IE
            try {
                var httpRequest = new ActiveXObject("Msxml2.XMLHTTP");
            }
            catch (e) {
                try {
                    httpRequest = new ActiveXObject("Microsoft.XMLHTTP");
                }
                catch (e) {
                    alert(window.M.utils.get_string('httprequestfail', 'topomojo'));
                }
            }
        }

        httpRequest.onreadystatechange = function () {
            if (this.readyState == 4) {

                var status = this.status;
                var response = '';
                if (status == 500) {
                    try {
                        response = JSON.parse(this.responseText);
                    } catch (Error) {
                        response = '';
                    }
                } else {
                    try {
                        response = JSON.parse(this.responseText);
                    } catch (Error) {
                        response = this.responseText;
                    }

                }
                callback(status, response); // call the callback with the status and response
            }
        };
        httpRequest.open('POST', topomojo.get('siteroot') + url, true);

        var parameters = '';
        if (params instanceof FormData) {
            parameters = params;  // already valid to send with xmlHttpRequest
        } else { // separate it out
            httpRequest.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');

            for (var param in params) {
                if (params.hasOwnProperty(param)) {
                    if (parameters.length > 0) {
                        parameters += '&';
                    }
                    parameters += param + '=' + encodeURI(params[param]);
                }
            }
        }

        httpRequest.send(parameters);

    }
};
