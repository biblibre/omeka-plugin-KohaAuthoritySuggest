jQuery(document).bind('omeka:elementformload', function() {
  jQuery('input.koha-authority-suggest[type="text"]').each(function() {
    var $input = jQuery(this);
    var url = '/admin/koha-authority-suggest/autocomplete/autocomplete';
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
