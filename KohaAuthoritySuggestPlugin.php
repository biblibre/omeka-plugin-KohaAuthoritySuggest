<?php

/**
 * @file
 * Koha Authority Suggest plugin main file.
 */

/**
 * Koha Authority Suggest plugin main class.
 */
class KohaAuthoritySuggestPlugin extends Omeka_Plugin_AbstractPlugin
{
    protected $_hooks = array(
        'initialize',
        'admin_head',
    );

    protected $_filters = array(
        'element_types_info',
    );

    /**
     * Set up plugins, translations, and filters
     */
    public function hookInitialize()
    {
        add_translation_source(dirname(__FILE__) . '/languages');
    }

    public function hookAdminHead() {
        queue_js_file('koha_authority_suggest');
    }

    public function filterElementTypesInfo($types) {
        $types['koha-authority'] = array(
            'label' => __('Koha authority'),
            'filters' => array(
                'ElementInput' => array($this, 'filterElementInput'),
                'Flatten' => array($this, 'filterFlatten'),
                'Display' => array($this, 'filterDisplay'),
                'Format' => array($this, 'filterFormat'),
            ),
            'hooks' => array(
                'OptionsForm' => array($this, 'hookOptionsForm'),
            ),
        );
        return $types;
    }

    /**
     * Transform the default textarea into a text input with attributes needed
     * for autocompletion.
     */
    public function filterElementInput($components, $args)
    {
        $view = get_view();
        $db = get_db();

        $element = $args['element'];
        $element_id = $element->id;
        $index = $args['index'];
        $value_object = json_decode($args['value']);

        // The text input
        $name = "Elements[$element_id][$index][text]";
        $value = $value_object ? $value_object->value : '';
        $components['input'] = $view->formText($name, $value, array(
            'class' => 'koha-authority-suggest',
            'data-element-id' => $element_id,
        ));

        // A hidden input that will hold the JSON string that will be stored
        // (contains the display value and the authority id)
        $name = "Elements[$element_id][$index][json]";
        $components['input'] .= $view->formHidden($name, $args['value']);

        // Remove "Use HTML" checkbox
        $components['html_checkbox'] = null;

        return $components;
    }

    /**
     * Transform the POST array into a single string.
     */
    public function filterFlatten($flatText, $args)
    {
        return $args['post_array']['json'];
    }

    /**
     * Transform the JSON string into a display value.
     */
    public function filterDisplay($text, $args)
    {
        $element = json_decode(html_entity_decode($text));
        if ($element === NULL) {
            return '';
        }

        return $element->value;
    }

    public function filterFormat($value, $args)
    {
        $suggestions = koha_authority_suggest_suggestions($args['element']->id,
            $value, 1);
        return $suggestions ? json_encode($suggestions[0]) : null;
    }

    public function hookOptionsForm($args) {
        $view = get_view();
        $options = $args['element_type']['element_type_options'];
        print '<div>';
        print $view->formLabel('host', __('Z39.50 server URL')) . ' ';
        print $view->formText('host', isset($options['host']) ? $options['host'] : '');
        print '</div>';
        print '<div>';
        print $view->formLabel('pqf_prefix', __('PQF Prefix')) . ' ';
        print $view->formText('pqf_prefix', isset($options['pqf_prefix']) ? $options['pqf_prefix'] : '');
        print '</div>';
    }
}

function koha_authority_suggest_suggestions($element_id, $term, $count)
{
    $element_type = get_db()
        ->getTable('ElementType')
        ->findByElementId($element_id);
    $element_type_options = json_decode($element_type->element_type_options, TRUE);

    $pqf_prefix = $element_type_options['pqf_prefix'];
    $host = $element_type_options['host'];

    $res = yaz_connect($host);
    yaz_syntax($res, 'xml');
    yaz_range($res, 1, $count);

    $query = "@attr 5=3 @attr 4=6 \"$term\"";
    if ($pqf_prefix) {
        $query = "$pqf_prefix $query";
    }

    yaz_search($res, 'rpn', $query);
    yaz_wait();

    $error = yaz_error($res);
    if ($error) {
        error_log("YAZ error: $error");
        return null;
    }

    $suggestions = array();
    for ($i = 1; $i <= min($count, yaz_hits($res)); $i++) {
        $xml = yaz_record($res, $i, 'xml');
        if (empty($xml)) {
            continue;
        }

        $record = simplexml_load_string($xml);
        if ($record === FALSE) {
            error_log('Failed to parse XML');
            continue;
        }

        $id = null;
        foreach ($record->controlfield as $controlfield) {
            if ($controlfield['tag'] == '001') {
                $id = (string)$controlfield;
                break;
            }
        }

        $main_entry = null;
        $secondary_entry = null;
        foreach ($record->datafield as $datafield) {
            if (substr($datafield['tag'], 0, 1) == '2') {
                foreach ($datafield->subfield as $subfield) {
                    if ($subfield['code'] == 'a') {
                        $main_entry = (string)$subfield;
                    } elseif ($subfield['code'] == 'b') {
                        $secondary_entry = (string)$subfield;
                    }
                }
                if ($main_entry) {
                    break;
                }
            }
        }

        $value = $main_entry;
        if (isset($secondary_entry)) {
            $value .= ", $secondary_entry";
        }

        $suggestions []= array(
            'id' => $id,
            'value' => $value,
        );
    }

    return $suggestions;
}
