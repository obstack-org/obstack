/******************************************************************
 *
 * frm
 *  .sidebar
 *    .show()           Function
 *
 ******************************************************************/

frm['sidebar'] = {

  storage: {},

  tree: function(maps, otlist) {
    let result = [];
    let lskey = 'obTree:fe26fe47bc970242be2f5d8a9383a898b9';
    frm.sidebar.storage = JSON.parse(localStorage.getItem(lskey));
    if (frm.sidebar.storage == null) {
      frm.sidebar.storage = { open: [] };
    }
    $.each(maps, function(idx, map) {
      // Map
      let open = $.inArray(map.id, frm.sidebar.storage.open) != -1;
      let ul = $('<ul/>', { class:'sidebar-ul' });
      let li = $('<li/>', {
        text:map.name,
        class:'sidebar-map',
        // Click
        click:function(event) {
          event.stopPropagation();
          // Storage
          if (!$(this).children('ul').is(':visible')) {
            if (!open) {
              frm.sidebar.storage.open = [ ...frm.sidebar.storage.open, map.id ];
            }
          }
          else {
            frm.sidebar.storage.open = $.grep(frm.sidebar.storage.open, function(value) { return value != map.id; });
          }
          localStorage.setItem(lskey, JSON.stringify(frm.sidebar.storage));
          // Toggle
          $(this).toggleClass('sidebar-map-down');
          $(this).children('ul').toggle(80);
        }
      });
      li.append(ul);

      // Submaps
      if (map.children.length > 0) {
        ul.append(
          frm.sidebar.tree(map.children, otlist)
        );
      }
      // Items
      $.each(otlist[map.id], function(idy, objtype) {
        ul.append(
          $('<li/>', { class:'sidebar-item', text:objtype.name, click: function(event) { event.stopPropagation(); change.check(function() { mod.obj.list(objtype.id); }); } })
        );
      });
      // Open
      if (open) {
        li.toggleClass('sidebar-map-down');
        ul.css("display", "block");
      }

      result = [...result, li];
    });

    return result;
  },

  /******************************************************************
   * frm.sidebar.show()
   * ===================
   * Load sidebar content
   ******************************************************************/
  show: function(objecttypes, navigation) {

    let maps = [];
    $.each(navigation, function(idx, map) {
      maps = [...maps, map.id];
    });

    // Load ObjectTypes
    let otlist = {};
    $.each(objecttypes, function(idx, type) {
      let parent = ($.inArray(type.map, maps) != -1) ? type.map : 'null';
      if (!otlist[parent]) {
        otlist[parent] = [ { id:type.id, name:type.name} ];
      }
      else {
        otlist[parent] = [...otlist[parent], { id:type.id, name:type.name} ];
      }
    });

    // Load sidebar
    sidebar.empty().append(frm.sidebar.tree(new obTree(navigation).data(), otlist));
    $.each(otlist['null'], function(idx, objtype) {
      sidebar.append(
        $('<li/>', { class:'sidebar-item', text:objtype.name, click: function() { change.check(function() { mod.obj.list(objtype.id); }); } })
      );
    });
  }
}