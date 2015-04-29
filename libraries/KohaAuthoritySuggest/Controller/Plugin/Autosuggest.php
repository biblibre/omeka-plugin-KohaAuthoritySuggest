<?php

class KohaAuthoritySuggest_Controller_Plugin_Autosuggest extends Zend_Controller_Plugin_Abstract
{
    /**
     * Add Javascript for autocompletion on pages that need it.
     */
    public function preDispatch($request)
    {
        $module = $request->getModuleName();
        if (is_null($module)) {
            $module = 'default';
        }
        $controller = $request->getControllerName();
        $action = $request->getActionName();

        $routes = array(
            array(
                'module' => 'default',
                'controller' => 'items',
                'actions' => array('add', 'edit')
            ),
        );

        $found = false;
        foreach ($routes as $route) {
            if ($route['module'] === $module
              && $route['controller'] === $controller 
              && in_array($action, $route['actions']))
            {
                  $found = true;
                  break;
            }
        }

        if ($found) {
            $view = Zend_Registry::get('view');
            $view->headScript()->captureStart();
?>
            jQuery(document).bind('omeka:elementformload', function() {
              jQuery('input.koha-authority-suggest[type="text"]').each(function() {
                var $input = jQuery(this);
                var url = <?php echo json_encode($view->url('koha-authority-suggest/autocomplete/autocomplete')); ?>;
                var element_id = $input.attr('data-element-id');
                url += '?element_id=' + encodeURIComponent(element_id);

                jQuery(this).autocomplete({
                  minLength: 3,
                  source: url,
                  select: function(event, ui) {
                    var item = ui.item;
                    var json = JSON.stringify({value: item.value, id: item.id});
                    jQuery(event.target).next('input').val(json);
                  },
                  change: function(event, ui) {
                    if (!ui.item) {
                      $target = jQuery(event.target)
                      $target.val('');
                      $target.next('input').val('');
                      $target.autocomplete('instance').term = '';
                    }
                  }
                });
              });
            });
<?php
            $view->headScript()->captureEnd();
        }
    }
}
