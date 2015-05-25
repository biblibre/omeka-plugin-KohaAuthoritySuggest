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
        $request = Zend_Controller_Front::getInstance()->getRequest();

        $module = $request->getModuleName();
        if (is_null($module)) {
            $module = 'default';
        }
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        if ($module === 'default'
            && $controller === 'items'
            && in_array($action, array('add', 'edit')))
        {
            queue_js_file('koha_authority_suggest');
        }
    }

    public function filterElementTypesInfo($types) {
        $types['koha-authority'] = array(
            'label' => __('Koha authority'),
            'filters' => array(
                'ElementInput' => array($this, 'filterElementInput'),
                'Flatten' => array($this, 'filterFlatten'),
                'Display' => array($this, 'filterDisplay'),
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
