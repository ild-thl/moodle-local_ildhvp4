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

namespace ildhvp4;

function setgrade($contextid, $score, $maxscore) {
    global $DB, $USER, $CFG;
    require($CFG->dirroot . '/mod/hvp/lib.php');

    $cm = get_coursemodule_from_instance('hvp', $contextid);
    if (!$cm) {
        return false;
    }

    // Check permission.
    $context = \context_module::instance($cm->id);
    if (!has_capability('mod/hvp:saveresults', $context)) {
        return false;
    }

    // Get hvp data from content.
    $hvp = $DB->get_record('hvp', array('id' => $cm->instance));
    if (!$hvp) {
        return false;
    }

    // Create grade object and set grades.
    $grade = (object)array(
        'userid' => $USER->id
    );

    /* oncampus mod - start */
    require_once($CFG->libdir . '/gradelib.php');
    $grading_info = \grade_get_grades($cm->course, 'mod', 'hvp', $cm->instance, $USER->id);
    $grading_info = (object)$grading_info;
    if (!empty($grading_info->items)) {
        $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;
    } else {
        $user_grade = 0;
    }

    if ($score > $user_grade) {
        // Set grade using Gradebook API.
        $hvp->cmidnumber = $cm->idnumber;
        $hvp->name = $cm->name;
        $hvp->rawgrade = $score;
        $hvp->rawgrademax = $maxscore;
        hvp_grade_item_update($hvp, $grade);

        // Get content info for log.
        $content = $DB->get_record_sql(
            "SELECT c.name AS title, l.machine_name AS name, l.major_version, l.minor_version
					   FROM {hvp} c
					   JOIN {hvp_libraries} l ON l.id = c.main_library_id
					  WHERE c.id = ?",
            array($hvp->id)
        );

        // Log results set event.
        new \mod_hvp\event(
            'results', 'set',
            $hvp->id, $content->title,
            $content->name, $content->major_version . '.' . $content->minor_version
        );

        // $progress = get_progress($cm->course, $cm->section);
        $progress = get_section_progress($cm->course, $cm->section, $USER->id);
        /* <script>;
            var divId = String('mooin4ection' + $_POST['sectionid']); // Mooin4ection-progress
            var textDivId = String('mooin4ection-text-' + $_POST['sectionid']); // Mooin4ection-progress-text-

            var percentageInt = String($_POST['percentage'] + '%');
            var percentageText = String($_POST['percentage'] + '% der Lektion bearbeitet');

            $('#' + divId, window.parent.document).css('width', percentageInt);
            $('#' + textDivId, window.parent.document).html(percentageText);
        </script>; */

        return $progress;
    }
    return false;
}

function get_section_progress($courseid, $sectionid, $userid) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/gradelib.php');

    $percentage = 0;

    // no activities in this section?
    $coursemodules = $DB->get_records('course_modules', array('course' => $courseid,
                                                                   'deletioninprogress' => 0,
                                                                   'section' => $sectionid));

    $activities = 0;

    foreach ($coursemodules as $coursemodule) {
        // cm has completion activated?
        if ($coursemodule->completion == 2) {
            $activities++;

            $modulename = '';
            if ($module = $DB->get_record('modules', array('id' => $coursemodule->module))) {
                $modulename = $module->name;
            }

            // activity is hvp, we use the grades to get the individual progress
            if ($modulename == 'hvp') {
                $grading_info = grade_get_grades($courseid, 'mod', 'hvp', $coursemodule->instance, $userid);
                $grade = $grading_info->items[0]->grades[$userid]->grade;
                $grademax = $grading_info->items[0]->grademax;
                if (isset($grade) && $grade != 0) {
                    $percentage += 100 / ($grademax / $grade);
                }
            }
            else {
                // if completed, add to percentage
                $sql = 'SELECT *
                          FROM {course_modules_completion}
                         WHERE coursemoduleid = :coursemoduleid
                           AND userid = :userid
                           AND completionstate != 0 ';
                $params = array('coursemoduleid' => $coursemodule->id,
                                'userid' => $userid);
                if ($DB->get_record_sql($sql, $params)) {
                    $percentage += 100;
                }
            }
        }
    }

    // no activities with completion activated?
    if ($activities == 0) {
        if (get_user_preferences('format_mooin_section_completed_'.$sectionid, 0, $userid) == 1) {
            return 100;
        }
        else {
            return 0;
        }
    }
    $progress = array('sectionid' => $sectionid, 'percentage' => round($percentage / $activities));
    return $progress;// round($percentage / $activities);
}

function get_progress($courseId, $sectionId) {
    global $DB, $CFG, $USER, $SESSION;
    require_once($CFG->libdir . '/gradelib.php');

    if (!$module = $DB->get_record('modules', array('name' => 'hvp'))) {
        return false;
    }

    $cm = $DB->get_records('course_modules', array('section' => $sectionId, 'course' => $courseId, 'module' => $module->id));

    if (count($cm) == 0) {
        return false;
    }

    $percentage = 0;
    $mods_counter = 0;

    if(isset($SESSION->lang)) {
        $user_lang = $SESSION->lang;
    } else {
        $user_lang = $USER->lang;
    }

    foreach ($cm as $mod) {
        if ($mod->visible == 1) {
            $skip = false;

            if (isset($mod->availability)) {
                $availability = json_decode($mod->availability);
                foreach ($availability->c as $criteria) {
                    if ($criteria->type == 'language' && ($criteria->id != $user_lang)) {
                        $skip = true;
                    }
                }
            }

            if ($mod->completion == 0) {
                $skip = true;
            }

            if (!$skip) {
                $grading_info = \grade_get_grades($mod->course, 'mod', 'hvp', $mod->instance, $USER->id);
                // $grading_info = (object)$grading_info;
                $user_grade = $grading_info->items[0]->grades[$USER->id]->grade;

                $percentage += $user_grade;
                $mods_counter++;
            }
        }
    }

    $progress = array('sectionid' => $sectionId, 'percentage' => $percentage / $mods_counter);

    return $progress;
}