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

        $element_type = get_db()
            ->getTable('ElementType')
            ->findByElementId($element_id);
        $element_type_options = json_decode($element_type->element_type_options, TRUE);

        $pqf_prefix = $element_type_options['pqf_prefix'];
        $host = $element_type_options['host'];

        $response = array();

        $res = yaz_connect($host);
        yaz_syntax($res, 'xml');
        yaz_range($res, 1, 10);

        $query = "@attr 5=3 @attr 4=6 \"$term\"";
        if ($pqf_prefix) {
            $query = "$pqf_prefix $query";
        }

        yaz_search($res, 'rpn', $query);
        yaz_wait();
        $error = yaz_error($res);
        if (empty($error)) {
            for ($i = 1; $i <= 10; $i++) {
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
                $response[] = array(
                    'label' => $value,
                    'value' => $value,
                    'id' => $id,
                );
            }
        } else {
            error_log("YAZ error: $error");
        }

        $this->_helper->json($response);
    }
}
