<?php

class KohaAuthoritySuggest_AutocompleteController extends Omeka_Controller_AbstractActionController
{
    /**
     * jQUeryUI autocomplete callback.
     *
     * Returns possible completions in a JSON array.
     */
    public function autocompleteAction()
    {
        $term = $this->getRequest()->getParam('term');
        $element_id = $this->_getParam('element_id');

        $suggestions = koha_authority_suggest_suggestions($element_id, $term, 10);
        $response = array();
        if ($suggestions) {
            foreach ($suggestions as $suggestion) {
                $response[] = array(
                    'label' => $suggestion['value'],
                    'value' => $suggestion['value'],
                    'id' => $suggestion['id'],
                );
            }
        }

        $this->_helper->json($response);
    }
}
