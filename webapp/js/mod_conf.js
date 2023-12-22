/******************************************************************
 *
 * mod
 *  .config
 *    .list()         Function
 *    .save()         Function
 *
 ******************************************************************/

mod['config'] = {

  settings_base: {
    'title':'',
    'css_titlebar-color':'#ddeeee',
    'css_titlebar-background':'#1b5f98',
    'css_sidebar-width':'180px',
    'css_sidebar-color':'#bfcad0',
    'css_sidebar-background':'#1c1c1c',
    'css_content-color':'#444444',
    'css_content-background':'#fafafa',
    'totp_default-enable':'0'
  },

  /******************************************************************
   * mod.config.list()
   * ===================
   * List settings
   ******************************************************************/
  list: function() {

    // Loader
    content.empty().append(loader.removeClass('fadein').addClass('fadein'));
    state.set('config');

    // Load and display data
    $.when(
      api('get',`config`)
    ).done(function(apidata) {

      // Navigation
      mod.config.navigation.maps = apidata.navigation;

      let navlist = new obFTable({
        table: {
          id: 'd9c769f68f18425387fd0d3f00502ae5 ',
          data: [],
          columns: [
            { id:'displayname', name:'Name' },
            { id:'info', name:'Info' },
          ],
          columns_resizable: true,
          columns_hidden: [ 'id', 'parent', 'name' ],
          columns_allowhtml: [ 'displayname' ]
        },
        create: function() {
          mod.config.navigation.open(navlist, null);
        },
        open: function(td) {
          let tr = $(td).parent();
          mod.config.navigation.open(navlist, tr);
        }
      });
      // Populate afterwards for all table exceptions
      mod.config.navigation.populate(navlist);

      // Settings
      let cfglist = new obFTable({
        table: {
          id: '0419a3e2622275926ece27edbc4111c7',
          data: [],
          columns: [
            { id:'name', name:'Name' },
            { id:'value', name:'Value' },
            { id:'reset', name:'Reset' },
          ],
          columns_resizable: true,
          columns_allowhtml: [ 'value', 'reset', 1, 2 ]
        }
      });
      // Populate afterwards for all table exceptions
      let cfgcache = {};
      $.each(apidata.settings, function(idx,value) {
        cfgcache[value.name] = value.value;
      });
      $.each(mod.config.settings_base, function(name, defval) {
        let curval = (typeof cfgcache[name] == 'undefined') ? defval : cfgcache[name];
        let value = $('<input/>', { class:'input-conf', hnm:name, value:curval });
        if (name.indexOf('css_') == 0) {
          if (value.prop('value').length == 0) {
            value.prop('value', curval );
          }
          if ((name.indexOf('-color') == name.length-6) || (name.indexOf('-background') == name.length - 11)) {
            value.prop('type', 'color');
          }
          if (name.indexOf('-width') == name.length-6) {
            curval = parseInt(curval);
            value.prop('type', 'range').prop('min', '80').prop('max', '300').css('width', '300px').prop('value', curval);
          }
        }
        if (name.indexOf('totp_default-enable') == 0) {
          value.prop('type', 'checkbox').removeClass('input-conf').addClass('nomrg').prop('checked', curval=='1');
        }
        cfglist.addrow([
          name,
          value,
          [
            $('<img/>', { class:'tblc-icon', src:'img/icund.png', title:'Undo' }).on('click', function() { change.change(); value.prop('value', curval); }),
            $('<img/>', { class:'tblc-icon', src:'img/icrst.png', title:'Reset to default' }).on('click', function() { change.change(); value.prop('value', defval); })
          ]
        ]);
      });

      // Draw
      content.append(new obContent({
        name: [ $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }), 'Configuration' ],
        content: new obTabs({ tabs:[
            { title:'Navigation', html:$('<div/>', { class: 'content-tab-wrapper' }).append(navlist.html()) },
            { title:'Settings',   html:$('<div/>', { class: 'content-tab-wrapper' }).append(cfglist.html()) },
          ] }).html(),
          control: [
            // -- Save
            $('<input/>', { class:'btn', type:'submit', value:'Save' }).on('click', function() {
              let depth = 1;
              $.each(new obTree(mod.config.navigation.maps).data(true), function(idx, map) {
                if (map.depth > depth) {
                  depth = map.depth;
                }
              });
              if (depth >= 4) {
                alert('Unable to save, maximum depth exceeded');
              }
              else {
                change.reset();
                content.append(loader.removeClass('fadein').addClass('fadein'));
                mod.config.save(navlist.table(), cfglist.table());
              }
            })
          ]
      }).html());

      // Additional controls
      $('#_obTab1-content').find('.tblwrap-control').append(
        $('<div/>').append(
          $('<input/>', { class:'btn', type:'submit', value:'Test Colors', width:100 }).on('click', function() {
            $.each(cfglist.table().html().find('tbody').find('input'), function() {
              let name = $(this).attr('hnm');
              if (name.indexOf('css_') == 0 && name.indexOf('-width') != name.length-6) {
                $('body')[0].style.setProperty('--'+name.substr(4), this.value);
              }
            });
          }),
          $('<input/>', { class:'btn', type:'submit', value:'Undo All', width:100 }).on('click', function() {
          })
        )
      );

      loader.remove();
      change.observe();

    });

  },

  /******************************************************************
   * mod.config.save(settings)
   * ===================
   * Save settings
   ******************************************************************/
  save: function(navlist, cfglist) {

    $.each(mod.config.navigation.maps, function(idx) {
      delete mod.config.navigation.maps[idx].depth;
      delete mod.config.navigation.maps[idx].children;
    });

    let cfgsave = [];
    $.each(cfglist.html().find('tbody').find('input'), function() {
      let name = $(this).attr('hnm');
      let value = this.value;
      if (name.indexOf('css_') == 0 && name.indexOf('-width') == name.length-6) {
        value = `${value}px`;
      }
      if (name.indexOf('totp_default-enable') == 0) {
        value = ($(this).prop('checked')) ? '1' : '0';
      }
      cfgsave = [ ...cfgsave, { name:name, value:value} ];
    });
    $.when(
      api('put',`config`, { settings:cfgsave, navigation:mod.config.navigation.maps })
    ).always(function() {
      location.reload(true);
    });

  },

  /******************************************************************
   * mod.config.navigation
   * ==================
   * Array of functions and subarrays for editing values
   ******************************************************************/
  navigation: {

    /******************************************************************
     * mod.config.navigation.maps
     * ==========================
     * Array of navigation maps, global to enable loading data
     * from API only once.
     ******************************************************************/

    maps: {},

    /******************************************************************
     * mod.config.navigation.newid
     * ==========================
     * Global id for new records, required for dynamic table
     ******************************************************************/

    newid: 0,

    /******************************************************************
     * mod.config.navigation.navlist
     * ==========================
     * Separate function for (re-)loading navlist
     ******************************************************************/

    populate: function(navlist) {

      $.each(new obTree(mod.config.navigation.maps).data(true), function(idx, map) {
        let error = '';
        if (map.depth >= 4) {
          error = 'Exceeds maximum depth (4)';
        }
        row = navlist.addrow({
          id:map.id,
          parent:map.parent,
          name:map.name,
          displayname:'&ensp;&emsp;'.repeat(map.depth) + '↳&ensp;' + map.name,
          info:error
        });
        if (map.depth >= 4) {
          row.find('td:eq(0)').addClass('error');
          row.find('td:eq(1)').addClass('error');
        }
      });

      return navlist;

    },

    /******************************************************************
     * mod.config.navigation.open(vtable)
     * ==================
     * Open the value form
     *   vtable   : Value as selected in the table
     ******************************************************************/
    open: function(navlist, row, parent=null) {

      let frmnewrec = (row == null);
      let rec = {};
      let value = '';
      let btnsubmit = 'Create';
      if (!frmnewrec) {
        rec = JSON.parse($(row).attr('hdt'));
        value = rec.name;
        btnsubmit = 'Ok';
      }

      // Select tree
      let maps = { 'null':'[top]' };
      let maxdepth = 99;
      $.each(new obTree(mod.config.navigation.maps).data(true), function(idx, map) {
        if (map.id == rec.id) {
          maxdepth = map.depth;
        }
        else if (map.depth <= maxdepth) {
          maps[map.id] = '\xA0\xA0\xA0\xA0'.repeat(map.depth) + '\xA0↳ ' + map.name;
        }
        if (map.depth < maxdepth || (map.depth == maxdepth && map.id != rec.id)) {
          maxdepth = 99;
        }
      });
      if (rec.parent == null) {
        rec.parent = 'null';
      }

      // form
      let form = new obForm([
        { id:'displayname', name:'Name', type:'string', value:value, regex_validate:/^.+/ },
        { id:'parent', name:'parent', type:'select', options:maps, value:rec.parent },
      ]);

      // Popup
      let popup = new obPopup({
        size: 'medium',
        content: form.html(),
        control: [
          // -- Ok
          $('<input/>', { class:'btn', type:'submit', value:btnsubmit }).on('click', function() {
            let form_data = form.validate();
            if (form_data != null) {
              if (frmnewrec) {
                let newid = 'null-'+mod.config.navigation.newid;
                mod.config.navigation.newid++;
                mod.config.navigation.maps = [
                  ...mod.config.navigation.maps,
                  { id:newid, name:form_data.displayname, parent:(form_data.parent == 'null') ? null : form_data.parent }
                ];
              }
              else {
                $.each(mod.config.navigation.maps, function(idx) {
                  if (mod.config.navigation.maps[idx].id == rec.id) {
                    mod.config.navigation.maps[idx].name = form_data.displayname;
                    mod.config.navigation.maps[idx].parent = (form_data.parent == 'null') ? null : form_data.parent;
                  }
                });
              }
              navlist.table().html().find('.obTable-tb').empty();
              mod.config.navigation.populate(navlist);
              popup.remove();
            }
          }),
          // -- Delete
          $('<input/>', { class:'btn', type:'submit', value:'Delete' }).on('click', function() {
            let isparent = false;
            $.each(mod.config.navigation.maps, function(idx) {
              if (mod.config.navigation.maps[idx].parent == rec.id) {
                isparent = true;
              }
            });
            if (isparent) {
              alert('Cannot delete map while having submaps');
            }
            else {
              $.each(mod.config.navigation.maps, function(idx) {
                if (mod.config.navigation.maps[idx].id == rec.id) {
                  delete mod.config.navigation.maps[idx];
                }
              });
              mod.config.navigation.maps = mod.config.navigation.maps.flat();
              navlist.table().html().find('.obTable-tb').empty();
              mod.config.navigation.populate(navlist);
              popup.remove();
            }
          }),
          // -- Close
          $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); }), '<br/>',
          // -- Add submap
          (frmnewrec || $(row).find('td:eq(0)').hasClass('error')) ? null : $('<input/>', { class:'btn', type:'submit', value:'Add submap', width:120 }).on('click', function() {
            popup.remove();
            mod.config.navigation.open(navlist, null, rec.id);
          })
        ]
      });

      // Validate select
      $.each(popup.html().find('#parent').find('option'), function() {
        let option = $(this);
        if (option.text().split('\xA0').length > 16) {
          option.attr('disabled','disabled');
        }
        if (option.val() == parent) {
          option.attr('selected','selected');
        }
      });

      $('#_obTab0-content').append(popup.html());
      loader.remove();

    }
  }

}