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
    // Sort objecttypes
    objecttypes.sort(function (a, b) {
      let data = [a,b];
      for(let i = 0; i <= 1; i++) {
        data[i] = data[i]['name'].trim();
        if (data[i].match(/^\d*(\s|$)/)) {
          data[i] = tbpad(data[i].slice(0, data[i].search(/[a-zA-Z\-\s_]/)),12) + data[i].slice(data[i].search(/[a-zA-Z\-\s_]/));
        }
      }
      return data[0].localeCompare(data[1]);
    });
    // Load sidebar
    sidebar.empty();
    $.each(objecttypes, function(index, type) {
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