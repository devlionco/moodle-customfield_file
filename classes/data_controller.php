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
 * File plugin data controller
 *
 * @package customfield_file
 * @author Evgeniy Voevodin
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2020 Devlion.co
 */

namespace customfield_file;

defined('MOODLE_INTERNAL') || die;

use core_customfield\field_controller;
/**
 * Class data
 *
 * @package customfield_file
 * @author Evgeniy Voevodin
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright 2020 Devlion.co
 */
class data_controller extends \core_customfield\data_controller {

    var $options = [];

    /**
     * data_controller constructor.
     *
     * @param int $id
     * @param \stdClass|null $record
     */
    public function __construct(int $id, \stdClass $record) {
        parent::__construct($id, $record);
        $field = field_controller::create($record->fieldid);
        $config = $field->get('configdata');

        $this->options = [
            'maxbytes' => $config['maxbytes'],
            'accepted_types' => $config['allowedtypes'],
            'subdirs' => 0,
            'maxfiles' => $config['maxfiles']
        ];
    }

    /**
     * Return the name of the field where the information is stored
     * @return string
     */
    public function datafield() : string {
        return 'charvalue';
    }

    /**
     * Returns the default value as it would be stored in the database (not in human-readable format).
     *
     * @return mixed
     */
    public function get_default_value() {
        return '';
    }

    /**
     * Add fields for editing a textarea field.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform) {

        $elementname = $this->get_form_element_name();
        $elementnameprepare = $this->get_form_element_name_prepare();

        $contextid = $this->get_context()->id;

        $data = new \stdClass();

        file_prepare_standard_filemanager($data, $elementnameprepare, $this->options, $this->get_context(), 'customfield_file', $elementnameprepare, $contextid);

        $mform->addElement('filemanager', $elementnameprepare, $this->get_field()->get_formatted_name(), null, $this->options);

        $mform->setDefault($elementnameprepare, $data->$elementname);
    }

    /**
     * Saves the data coming from form
     *
     * @param \stdClass $datanew data coming from the form
     */
    public function instance_form_save(\stdClass $datanew) {

        $elementname = $this->get_form_element_name_prepare();
        if (!property_exists($datanew, $elementname)) {
            return;
        }

        $value = $datanew->$elementname;

        $fs = get_file_storage();
        $contextid = $this->get_context()->id;

        $draftlinks = file_save_draft_area_files($value, $contextid, 'customfield_file', $elementname, $contextid, $this->options);

        $files = $fs->get_area_files($contextid, 'customfield_file', $elementname, $contextid, 'itemid, filepath, filename', false);
        $data = [];
        foreach ($files as $pathnamehash => $file) {
            $data[] = $file->get_id();
        }

        $data = serialize($data);
        $this->data->set($this->datafield(), $data);
        $this->data->set('value', $data);
        $this->save();
    }

    /**
     * Returns the value as it is stored in the database or default value if data record is not present
     *
     * @return mixed
     */
    public function get_value() {
        if (!$this->get('id')) {
            return [$this->get_default_value()];
        }

        $fs = get_file_storage();
        $files = [];

        $value = unserialize($this->get($this->datafield()));

        foreach ($value as $fileid) {
            $files[] = $fs->get_file_by_id($fileid);
        }

        return $files;
    }

    /**
     * Returns value in a human-readable format
     *
     * @return mixed|null value or null if empty
     */
    public function export_value() {
        global $OUTPUT;

        $value = $this->get_value();

        if ($this->is_empty($value)) {
            return null;
        }

        $output = [];

        foreach ($value as $file) {
            $link = (\moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                '/',
                $file->get_filename())->out()
            );
            $image = \html_writer::img($OUTPUT->image_url(file_file_icon($file, 24))->out(false), $file->get_filename());
            $output[] = \html_writer::link($link, $image . $file->get_filename());
        }

        return implode(' ', $output);
    }

    /**
     * Returns the name of the field to be used on HTML forms.
     *
     * @return string
     */
    protected function get_form_element_name() : string {
        return $this->get_form_element_name_prepare(). '_filemanager';
    }

    /**
     * Returns the name of the field to be used on HTML forms without filemanager.
     *
     * @return string
     */
    protected function get_form_element_name_prepare() : string {
        return 'customfield_' . $this->get_field()->get('shortname');
    }

    /**
     * Checks if the value is empty
     *
     * @param mixed $value
     * @return bool
     */
    protected function is_empty($value) : bool {
        return empty($value);
    }
}
