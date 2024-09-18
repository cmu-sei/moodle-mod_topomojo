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

/**
 * Admin settings class for the topomojo review options.
 *
 * @package   mod_topomojo
 * @copyright 2008 Tim Hunt
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Admin settings class for the topomojo review options.
 *
 * @copyright  2008 Tim Hunt
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_topomojo_admin_review_setting extends admin_setting {
    /**
     * @var integer should match the constants defined in
     * {@link topomojo_display_options}. Copied for performance reasons.
     */
    const DURING            = 0x10000;

    /**
     * @var integer should match the constants defined in
     * {@link topomojo_display_options}. Copied for performance reasons.
     */
    const IMMEDIATELY_AFTER = 0x01000;

    /**
     * @var integer should match the constants defined in
     * {@link topomojo_display_options}. Copied for performance reasons.
     */
    const LATER_WHILE_OPEN  = 0x00100;

    /**
     * @var integer should match the constants defined in
     * {@link topomojo_display_options}. Copied for performance reasons.
     */
    const AFTER_CLOSE       = 0x00010;

    /**
     * @var bool|null forced checked / disabled attributes for the during time.
     */
    protected $duringstate;

    /**
     * This should match {@link mod_topomojo_mod_form::$reviewfields} but copied
     * here because generating the admin tree needs to be fast.
     * @return array
     */
    public static function fields() {
        return [
            'attempt'          => get_string('theattempt', 'topomojo'),
            'correctness'      => get_string('whethercorrect', 'question'),
            'marks'            => get_string('marks', 'question'),
            'specificfeedback' => get_string('specificfeedback', 'question'),
            'generalfeedback'  => get_string('generalfeedback', 'question'),
            'rightanswer'      => get_string('rightanswer', 'question'),
            'overallfeedback'  => get_string('overallfeedback', 'topomojo'),
            'manualcomment'    => get_string('manualcomment', 'topomojo'),
        ];
    }

    /**
     * Constructor.
     *
     * @param string $name unique ascii name, either 'mysetting' for settings that in config,
     *                     or 'myplugin/mysetting' for ones in config_plugins.
     * @param string $visiblename localised name
     * @param string $description localised long description
     * @param mixed $defaultsetting string or array depending on implementation
     * @param bool|null $duringstate
     */
    public function __construct($name, $visiblename, $description,
            $defaultsetting, $duringstate = null) {
        $this->duringstate = $duringstate;
        parent::__construct($name, $visiblename, $description, $defaultsetting);
    }

    /**
     * Return the combination that means all times.
     * @return int all times.
     */
    public static function all_on() {
        return self::DURING | self::IMMEDIATELY_AFTER | self::LATER_WHILE_OPEN |
                self::AFTER_CLOSE;
    }

    /**
     * Get an array of the names of all the possible times.
     * @return array an array of time constant => lang string.
     */
    protected static function times() {
        return [
            self::DURING            => get_string('reviewduring', 'topomojo'),
            self::IMMEDIATELY_AFTER => get_string('reviewimmediately', 'topomojo'),
            self::LATER_WHILE_OPEN  => get_string('reviewopen', 'topomojo'),
            self::AFTER_CLOSE       => get_string('reviewclosed', 'topomojo'),
        ];
    }

    /**
     * Normalizes the given data based on predefined time masks.
     *
     * This method iterates over predefined time masks and checks if each time mask is present
     * in the input data. It also considers a special state (`duringstate`) when the time mask
     * is equal to `DURING`. It calculates a cumulative value based on the presence of these
     * time masks and the `duringstate` flag.
     *
     * @param array $data An associative array where keys represent time masks and values represent data related to those masks.
     * @return int The cumulative value based on the presence of time masks in the input data and the `duringstate` flag.
     */
    protected function normalise_data($data) {
        $times = self::times();
        $value = 0;
        foreach ($times as $timemask => $name) {
            if ($timemask == self::DURING && !is_null($this->duringstate)) {
                if ($this->duringstate) {
                    $value += $timemask;
                }
            } else if (!empty($data[$timemask])) {
                $value += $timemask;
            }
        }
        return $value;
    }

    /**
     * Retrieves the configuration setting for the current object.
     *
     * This method accesses the configuration system to read and return the value
     * associated with the object's name. It leverages the `config_read` method
     * to fetch the setting.
     *
     * @return mixed The configuration value associated with the object's name.
     */
    public function get_setting() {
        return $this->config_read($this->name);
    }

    /**
     * Writes a configuration setting for the current object.
     *
     * This method normalizes the input data if it is an array or empty, then
     * writes the normalized data to the configuration system using the `config_write` method.
     *
     * @param mixed $data The data to be written to the configuration. It will be normalized if it's an array or empty.
     * @return string An empty string indicating successful completion.
     */
    public function write_setting($data) {
        if (is_array($data) || empty($data)) {
            $data = $this->normalise_data($data);
        }
        $this->config_write($this->name, $data);
        return '';
    }

    /**
     * Generates HTML output for the configuration setting.
     *
     * This method normalizes the input data if it is an array or empty, then generates
     * an HTML snippet that represents a group of checkboxes based on the provided data.
     * It also handles the "DURING" state and constructs the HTML for each checkbox.
     *
     * @param mixed $data The data to be represented by checkboxes. If it's an array or empty, it will be normalized.
     * @param string $query Optional query string to be included in the output.
     * @return string The generated HTML output for the configuration setting.
     */
    public function output_html($data, $query = '') {
        if (is_array($data) || empty($data)) {
            $data = $this->normalise_data($data);
        }

        $return = '<div class="group"><input type="hidden" name="' .
                    $this->get_full_name() . '[' . self::DURING . ']" value="0" />';
        foreach (self::times() as $timemask => $namestring) {
            $id = $this->get_id(). '_' . $timemask;
            $state = '';
            if ($data & $timemask) {
                $state = 'checked="checked" ';
            }
            if ($timemask == self::DURING && !is_null($this->duringstate)) {
                $state = 'disabled="disabled" ';
                if ($this->duringstate) {
                    $state .= 'checked="checked" ';
                }
            }
            $return .= '<span><input type="checkbox" name="' .
                    $this->get_full_name() . '[' . $timemask . ']" value="1" id="' . $id .
                    '" ' . $state . '/> <label for="' . $id . '">' .
                    $namestring . "</label></span>\n";
        }
        $return .= "</div>\n";

        return format_admin_setting($this, $this->visiblename, $return,
                $this->description, true, '', get_string('everythingon', 'topomojo'), $query);
    }
}
