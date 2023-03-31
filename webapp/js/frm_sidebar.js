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
  show: function(val) {
    // Load and display sidebar
    sidebar.empty();
    $.each(val, function(index, type) {
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