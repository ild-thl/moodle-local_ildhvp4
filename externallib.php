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
 * @package    local_ildhvp4
 * @copyright  2018 ILD, Technische Hochschule Lübeck (https://www.th-luebeck.de/ild)
 * @author     Eugen Ebel (eugen.ebel@th-luebeck.de)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->libdir . "/externallib.php");
require_once($CFG->dirroot . "/local/ildhvp4/locallib.php");

class local_ildhvp4_external extends external_api {
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function setgrade_parameters() {
        return new external_function_parameters(array(
            'contentid' => new external_value(PARAM_INT, 'H5P content id'),
            'score' => new external_value(PARAM_FLOAT, 'H5P score'),
            'maxscore' => new external_value(PARAM_FLOAT, 'H5P max score')
        ));
    }

    /**
     * Returns status
     * @return array user data
     */
    public static function setgrade($contentid, $score, $maxscore) {
        global $SESSION;
        //Parameter validation
        //REQUIRED
        $params = self::validate_parameters(self::setgrade_parameters(),
            array(
                'contentid' => $contentid,
                'score' => $score,
                'maxscore' => $maxscore
            ));

        //Context validation
        //OPTIONAL but in most web service it should present
        $context = \context_system::instance();
        self::validate_context($context);

        $progress = \ildhvp4\setgrade($contentid, $score, $maxscore);

        return array(
            'sectionid' => $progress['sectionid'],
            'percentage' => $progress['percentage']
        );
    }

    /**
     * Returns description of method result value
     * @return external_single_structure
     */
    public static function setgrade_returns() {
        return new \external_single_structure(array(
            'sectionid' => new external_value(PARAM_INT, 'Section ID'),
            'percentage' => new external_value(PARAM_FLOAT, 'Percentage of section progress'),
        ), 'Section progress');
    }
}