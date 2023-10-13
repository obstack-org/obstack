/******************************************************************
 *
 * frm
 *  .sidebar
 *    .show()           Function
 *
 ******************************************************************/

frm['sidebar'] = {

  /******************************************************************
   * frm.sidebar.show()
   * ===================
   * Load sidebar content
   ******************************************************************/
  show: function(objecttypes) {
    // Load sidebar
    sidebar.empty();
    $.each(lsSort(objecttypes, 'name'), function(index, type) {
      sidebar.append(
        $('<div/>', {
          class: "sidebar-item",
          html: type.name,
          click: function() { mod.obj.list(type.id); }
        })
      );
    });
  }
}