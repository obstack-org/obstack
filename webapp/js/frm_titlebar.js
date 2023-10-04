/******************************************************************
 *
 * frm
 *  .titlebar
 *    .dropdown         Variable
 *    .items            Array
 *    .show()           Function
 *    .load()           Function
 *
 ******************************************************************/

frm['titlebar'] = {

  /******************************************************************
   * frm.login.dropdown
   * ===================
   * Element definition for dropdown menu
   ******************************************************************/
  dropdown: $('<div/>', { id: 'titlebar-dropdown', class: 'titlebar-dropdown' }).hide().slideUp('fast'),

  /******************************************************************
   * frm.login.items
   * ===================
   * Data for dropdown menus
   ******************************************************************/
  items: {
    'icusr': [15, {
      "Logout"      : function() { $.when(api('delete', 'auth')).always(function() { location.reload(true); }); }
    }],
    'iccnf': [45, {
      "Users"       : function() { mod.user.list(); },
      "Groups"      : function() { mod.group.list(); },
      "Value maps"  : function() { mod.valuemap.list(); },
      "Object types": function() { mod.objconf.list(); }
    }],
  },

  /******************************************************************
   * frm.titlebar.show()
   * ===================
   * Generate titlebar content
   ******************************************************************/
  show: function() {
    // Load and display titlebar
    let controlbar = $('<div/>', { class: 'titlebar-control' });
    let titlebar_name = $('<div/>', { class: 'titlebar-title' }).append(
      $('<img/>', { src: 'img/obstack.png' }),
      `&nbsp;${title}`
    );
    titlebar.append(
      titlebar_name,
      frm.titlebar.dropdown,
      controlbar
    )

    // Load and display dropdown menus
    if (!mod.user.self.ext) {
      frm.titlebar.items['icusr'][1] = {
        "Profile"     : function() { mod.user.open('self'); },
        ...frm.titlebar.items['icusr'][1]
      };
    }
    if (mod.user.self.sa) {
      controlbar.append(
        $('<img/>', { src: 'img/iccnf.png', class: "titlebar-control-img" }).on('click', function(event) { frm.titlebar.load('iccnf') })
      );
    }
    controlbar.append(
      $('<img/>', { src: 'img/icusr.png', class: "titlebar-control-img" }).on('click', function(event) { frm.titlebar.load('icusr') })
    );
  },

  /******************************************************************
   * frm.login.load()
   * ===================
   * Load dropdown menu content
   ******************************************************************/
  load: function(select) {
    // Load and display dropdown content
    frm.titlebar.dropdown.css({'right': frm.titlebar.items[select][0] })
    frm.titlebar.dropdown.empty();
    $.each(frm.titlebar.items[select][1], function(key) {
      frm.titlebar.dropdown.append(
        $('<div/>', { class: 'titlebar-dropdown-item' })
          .html(key)
          .on('click', function() { frm.titlebar.items[select][1][key](); }),
      )
    });
    frm.titlebar.dropdown.slideDown('fast');
  }

}