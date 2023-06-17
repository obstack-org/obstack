/******************************************************************
 *
 * mod
 *  .obj
 *    .jftypes          Array
 *    .list()           Function
 *    .open()           Function
 *    .open_htgen()     Function
 *    .save()           Function
 *    .relations        Array
 *      .open()         Function
 *
 ******************************************************************/

mod['obj'] = {

  /******************************************************************
   * mod.obj.jftypes
   * ====================
   * Array of field types used in jsonForm schema. Content is loaded
   * from const after loading module
   ******************************************************************/
   jftypes: {},

  /******************************************************************
   * mod.obj.list(type)
   * ===================
   * List objects of type
   *    type    : Object type UUID
   ******************************************************************/
  list: function(type) {
    // Generate HTML with loader
    let objlist = {
      control:  $('<div/>', { class: 'tblwrap-control' }),
      content:  $('<div/>', { class: 'tblwrap-table' }),
      footer:   $('<div/>', { class: 'tblwrap-footer' }),
      table:    null
    };
    let ctwrap = {
      name:     $('<div/>'),
      control:  $('<div/>', { class: 'content-wrapper-control' })
    }
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        objlist.control,
        objlist.content,
        objlist.footer,
        ctwrap.control
      )
    );

    // Load and display data
    content.append(loader.removeClass('fadein').addClass('fadein'));
    $.when(
      api('get',`objecttype/${type}?format=gui`),
      api('get',`objecttype/${type}/property`),
      api('get',`objecttype/${type}/object?format=gui`)
    ).done(function(apidata_type, apidata_properties, apidata_objects) {
      ctwrap.name.append(apidata_type[0].name);

      let columns = [];
      let columns_remove = [];
      $.each(apidata_properties[0], function(id, column) {
        if (column.tbl_visible) {
          columns = [...columns, { id:column.id, name:column.name, orderable:column.tbl_orderable}];
        }
        else {
          columns_remove = [...columns_remove, column.name];
        }
      });
      for (let i=0; i<apidata_objects[0].length; i++) {
        $.each(columns_remove, function(idx, name) {
          delete apidata_objects[0][i][name];
        });
      }
      objlist.table = new obTable({
        id: type,
        data: apidata_objects[0],
        columns: columns,
        columns_resizable: true,
        columns_hidden: ['id']
      });
      objlist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { objlist.table.search(this.value); }) );
      objlist.content.append(objlist.table.html());
      objlist.footer.append(`Objects: ${apidata_objects[0].length}`);
      objlist.table.html().on('click', 'td', function () {
        content.empty().append(loader);
        mod.obj.open(type, JSON.parse($(this).parent().attr('hdt')).id);
      });
      $(objlist.table.html()).find('tbody').children('tr').each(function() {
        $(this).addClass('pointer');
      });

      if (apidata_type[0].acl.create) {
        ctwrap.control.append(
          $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
            .on('click', function() {
              mod.obj.open(type, 'create');
            })
        );
      }

      $.each(content.find('.obTable-tb'), function() {
        $(this).obTableRedraw();
      });
      loader.remove();
    });
  },

  /******************************************************************
   * mod.obj.open(type, id)
   * ===================
   * Open object of type with id
   *    type    : Object type UUID
   *    id      : Object UUID
   ******************************************************************/
  open: function(type, id) {
    if (id == 'create') {
      $.when(
        api('get',`objecttype/${type}?format=gui`),
        api('get',`objecttype/${type}/property`)
      ).done(function(api_objtype, api_objproperty) {
        mod.obj.open_htgen(type, id, api_objtype[0], api_objproperty[0], { id: type }, [{ name:'[new]' }], {}, {});
      });
    }
    else {
      $.when(
        api('get',`objecttype/${type}?format=gui`),
        api('get',`objecttype/${type}/property`),
        api('get',`objecttype/${type}/object/${id}`),
        api('get',`objecttype/${type}/object/${id}?format=short`),
        api('get',`objecttype/${type}/object/${id}/relation`),
        api('get',`objecttype/${type}/object/${id}/log`)
      ).done(function(api_objtype, api_objproperty, api_obj, api_obj_short, api_relations, api_log) {
        mod.obj.open_htgen(type, id, api_objtype[0], api_objproperty[0], api_obj[0], api_obj_short[0], api_relations[0], api_log[0]);
      });
    }
  },

  /******************************************************************
   * mod.obj.open_htgen()
   * ====================
   * Generate page from API data
   *    type    : Object type UUID
   *    id      : Object UUID
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(type, id, api_objtype, api_objproperty, api_obj, api_obj_short, api_relations, api_log) {
    // Generate HTML
    let object = {
      properties: $('<form/>', { class: 'content-form' }),
      logs:       $('<table/>', { class: 'log-table' })
    }
    let rellist = {
      control:  $('<div/>', { class: 'tblwrap-control' }),
      content:  $('<div/>', { class: 'tblwrap-table' }),
      footer:   $('<div/>', { class: 'tblwrap-footer' }),
      table:    null
    };
    let ctwrap = {
      name:     $('<div/>'),
      tabs:     $('<div/>', { class: 'content-tab' }),
      control:  $('<div/>', { class: 'content-wrapper-control-right' })
    }

    // Populate relations table
    $.each(api_relations, function(idx, rec) {
      let colnr = 0;
      $.each(rec, function(key, value) {
        if (colnr > 6) {
          delete api_relations[idx][key];
        }
        colnr++;
      });
      for (let i=colnr; i<=6; i++) {
        api_relations[idx][`dmy${i}`] = null;
      }

    });

    rellist.table = new obTable({
      id: `${type}_relations`,
      data: api_relations,
      columns: [
        { id: 'relations_type', name:'Type', orderable: true },
        { id: 'relations_field1', name:'Field', orderable: true },
        { id: 'relations_field2', name:'', orderable: false },
        { id: 'relations_field3', name:'', orderable: false },
        { id: 'relations_field4', name:'', orderable: false }
      ],
      columns_resizable: true,
      columns_hidden: ['id', 'objtype']
    });
    rellist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { rellist.table.search(this.value); }) );
    rellist.content.append(rellist.table.html());
    rellist.footer.append(`Relations: ${api_relations.length}`);
    rellist.table.html().on('click', 'td', function () {
      tr = $(this).parent();
      if (tr.hasClass('delete')) {
        if (confirm('Do you want to remove the unassign mark?')) {
          tr.removeClass('delete');
        }
      }
      else {
        mod.obj.relations.open(type, id, rellist.table, tr, api_objtype.acl.update);
      }
    });
    $(rellist.table.html()).find('tbody').children('tr').each(function() {
      $(this).addClass('pointer');
    });

    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        ctwrap.tabs,
        ctwrap.control
      )
    );
    let ctabs = [
      { title: 'Properties',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(object.properties) },
      { title: 'Relations',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(
        rellist.control,
        rellist.content,
        rellist.footer,
        rellist.control
      )}
    ];

    // Load and display data
    if (mod.user.self.sa && api_objtype.log) {
      ctabs = [...ctabs, { title: 'Log',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(object.logs) }];
    }
    ctwrap.name.append(
        $('<a/>', {
          class: 'link',
          html: api_objtype.name,
          click: function() { mod.obj.list(type); }
        }),
        ` / ${api_obj_short[0].name}`
      );
    ctwrap.tabs.obTabs({
      tabs: ctabs
    });

    // Format and load form fields
    let usedata = {};
    let formdata = [];
    let objtypes = {};
    let valuemaps = {};
    let checkboxdata = {};
    let selectcount = 0;
    $.each(api_objproperty, function(cfg_id, cfg_value) {
      // Process when field is visible
      if (cfg_value.frm_visible) {
        // 'default' field (straight forward values)
        let value = '';
        $.each(api_obj, function(data_id, data_value) {
          if (cfg_value.id == data_value.id) {
            value = data_value.value;
          }
        });
        formfield = { key: cfg_value.id };
        // Select field for object types (prepare options)  -->> TO SHORT !!!
        if (cfg_value.type == def.prop.select_objtype.id) {
          objtypes[cfg_value.id] = {
            value: value,
            srcid: cfg_value.type_objtype
          }
          selectcount++;
        }
        // Select field for value map (prepare options)
        if (cfg_value.type == def.prop.select_valuemap.id) {
          valuemaps[cfg_value.id] = {
            value: value,
            srcid: cfg_value.type_valuemap
          }
          selectcount++;
        }
        // Overwrite readonly
        if (!api_objtype.acl.update && (id != 'create')) {
          cfg_value.frm_readonly = true;
        }
        // Remaining 'low config' fields (checkbox, datetime etc)
        if (cfg_value.type == def.prop.checkbox.id) { checkboxdata[cfg_value.id] = { value: value, readonly: cfg_value.frm_readonly }; }
        if (cfg_value.type == def.prop.date.id)     { formfield['type'] = 'date'; }
        if (cfg_value.type == def.prop.datetime.id) { formfield['type'] = 'datetime-local'; }
        // Prepare data for jsonForm
        usedata[cfg_value.id]  = { type: mod.obj.jftypes[cfg_value.type], title: cfg_value.name, default: value, required: cfg_value.required, readonly: cfg_value.frm_readonly };
        formdata = [...formdata, formfield];
      }
    });
    object.properties.jsonForm({
      schema: usedata,
      form: formdata
    });

    // Add error field
    object.properties.find(':input').each(function() {
      $('<span class="jsonform-errortext"></span>').insertBefore($(this).parent());
    });

    // Select population counter
    let selectfilled = 0;

    // Populate objtype fields
    $.each(objtypes, function(tindex,tvalue) {
      let ptype = object.properties.find(`select[name=${tindex}]`);
      $.when(
        api('get',`objecttype/${tvalue.srcid}/object?format=short`)
      ).done(function(apidata) {
        $.each(apidata, function(index,value) {
          ptype.append($('<option/>').text(value.name).val(value.id));
        });
        if (apidata.length == 1) {
          if (apidata[0].id == null) {
            ptype.val('');
          }
        }
        else {
          ptype.val(tvalue.value);
        }
        selectfilled++;
        if (selectfilled >= selectcount) {
          loader.remove();
        }
      });
    });

    // Populate valuemap fields
    $.each(valuemaps, function(tindex,tvalue) {
      let ptype = object.properties.find(`select[name=${tindex}]`);
      $.when(
        api('get',`valuemap/${tvalue.srcid}/value`)
      ).done(function(apidata) {
        $.each(apidata, function(index,value) {
          ptype.append($('<option/>').text(value.name).val(value.id));
        });
        ptype.val(tvalue.value);
        selectfilled++;
        if (selectfilled >= selectcount) {
          loader.remove();
        }
      });
    });

    // Populate checkbox fields
    $.each(checkboxdata, function(tindex,tvalue) {
      let ptype = object.properties.find(`input[name=${tindex}]`);
      ptype.prop('checked', (tvalue.value == 1));
      if (tvalue.readonly) {
        $(ptype).on('click', function () { return false; });
      }
    });

    // Add relations buttons
    if (api_objtype.acl.update || (api_objtype.acl.create && id=='create')) {
      rellist.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function() {
              mod.obj.relations.open(type, id, rellist.table, null);
          })
      );
    }

    // Add object type buttons
    if (api_objtype.acl.update || (id == 'create')) {
      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function() { mod.obj.save(type, id, object.properties, rellist.table); })
      );
    }
    if (api_objtype.acl.delete) {
      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function() {
          if (confirm('WARNING!: This action wil permanently delete this object and its values. Are you sure you want to continue?')) {
            $.when( api('delete',`objecttype/${type}/object/${id}`) ).always(function() { mod.obj.list(type); });
          }
        })
      );
    }
    ctwrap.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function() { mod.obj.list(type); })
    );

    // Populate log field
    if (mod.user.self.sa && api_objtype.log) {
      object.logs.append(
        $('<th/>', { class: 'log-th', width: 160}).text('Date/Time'),
        $('<th/>', { class: 'log-th', width: 100 }).text('User'),
        $('<th/>', { class: 'log-th', width: 100 }).text('Action'),
        $('<th/>', { class: 'log-th', width: 450 }).text('Details')
      );
      $.each(api_log, function(tindex,tvalue) {
        object.logs.append(
          $('<tr/>').append(
            $('<td/>', { class: 'log-td' }).text(tvalue.timestamp),
            $('<td/>', { class: 'log-td' }).text(tvalue.username),
            $('<td/>', { class: 'log-td' }).text(def.logtype[parseInt(tvalue.action)]),
            $('<td/>', { class: 'log-td' }).text(tvalue.details)
          )
        )
      });
    }

    loader.remove();
  },

  /******************************************************************
   * mod.obj.save(type, id, properties, relations)
   * ===================================
   * Save object
   *    type        : Object type UUID
   *    id          : Object UUID
   *    properties  : Form data
   *    relations   : Relations list
   ******************************************************************/
   save: function(type, id, properties, relations) {
    // Prepare data formats
    let dtsave = { relations: [] };
    let incomplete = false;

    // Gather data from config fields
    properties.find(':input').each(function() {
      if ($(this).prop('type') == 'checkbox') {
        if ($(this).prop('checked')) { dtsave[$(this).prop('name')] = 1; }
        else { dtsave[$(this).prop('name')] = 0; }
      }
      else {
        dtsave[$(this).prop('name')] = $(this).prop('value');
      }
      if ($(this).prop('required')) {
        if ($(this).prop('value').length < 1) {
          incomplete = true;
          $(this).prop('style', 'box-shadow: 0 0 5px red');
          $(this).parent().parent().find('span').html('Required ↴');
        }
        else {
          $(this).prop('style', 'box-shadow: 0 0 0px #000');
          $(this).parent().parent().find('span').html('');
        }
      }
    });

    // Gather data from the relations table
    $.each(relations.html().children('tbody').find('tr'), function(idx, tr) {
      tr = $(tr);
      if (!tr.hasClass('delete')) {
        dtsave.relations = [...dtsave.relations, JSON.parse(tr.attr('hdt')).id];
      }
    });

    // Save when all required fields are filled
    if (incomplete) {
      $('.sTabs-tab').removeClass('active');
      $('.sTabs-tab-content').hide();
      $('#_sTab0').addClass('active');
      $('#_sTab0-content').show();
      $($.fn.dataTable.tables(true)).DataTable().columns.adjust();
    }
    else {
      if (id == 'create') {
        $.when(api('post',`objecttype/${type}/object`,dtsave)).always(function() { mod.obj.list(type); });
      }
      else {
        $.when( api('put',`objecttype/${type}/object/${id}`,dtsave) ).always(function() { mod.obj.list(type); });
      }
    }
  },

  /******************************************************************
   * mod.obj.relations
   * ==================
   * Array of relations and subarrays for adding and removing
   * related objects
   ******************************************************************/
  relations: {

    /******************************************************************
     * mod.obj.relations.open(type, id, rtable)
     * ==================
     * Open the relations form
     *    type    : Object type UUID
     *    id      : Object UUID
     *    table   : Relations table
     *    row     : Relation as selected in the table
     ******************************************************************/
    open: function(type, id, table, row, acl_update) {
      let tblnewrec = (row == null);

      // Generate HTML
      let popup = {
        overlay:  $('<div/>'),
        wrapper:  $('<div/>', { class: 'content-popup-wrapper' }),
        control:  $('<div/>', { class: 'content-popup-wrapper-control' })
      };
      $('#_obTab1-content').append(
        popup.overlay.append(
          $('<div/>', { class: 'content-popup-overlay' }).append(
            popup.wrapper
          )
        )
      );

      // Load and display data
      popup.wrapper.append(loader);
      if (tblnewrec) {
        // Add relation -> show list
        $.when(
          api('get',`/objecttype/${type}/object/${id}/relation/available?format=gui`)
        ).done(function(relations) {
          // Filter out selected
          let relations_selected = [];
          $.each(table.html().children('tbody').find('tr'), function(idx, tr) {
            relations_selected = [...relations_selected, JSON.parse($(tr).attr('hdt')).id];
          });
          $.each(relations, function( idx, rec ) {
            if (rec.id == id) {
              delete relations[idx];
            }
            else {
              if ( relations_selected.indexOf(relations[idx].id) != -1) {
                delete relations[idx];
              }
              else {
                let colnr = 0;
                $.each(rec, function(key, value) {
                  if (colnr > 6) {
                    delete relations[idx][key];
                  }
                  colnr++;
                });
                for (let i=colnr; i<=6; i++) {
                  relations[idx][`dmy${i}`] = null;
                }
              }
            }
          });

          // Clean up empty slots created by delete action
          relations = relations.flat();

          // Generate table
          let objlist = {
            control:  $('<div/>', { class: 'tblwrap-control' }),
            content:  $('<div/>', { class: 'tblwrap-table' }),
            footer:   $('<div/>', { class: 'tblwrap-footer' }),
            table:    null
          };
          objlist.table = new obTable({
            id: '1eba292da5c6bd11e8b501c462cbf896ba76ee6a',
            data: relations,
            columns: [
              { id: 'relations_type', name:'Type', orderable: true },
              { id: 'relations_field1', name:'Field', orderable: true },
              { id: 'relations_field2', name:'', orderable: false },
              { id: 'relations_field3', name:'', orderable: false },
              { id: 'relations_field4', name:'', orderable: false }
            ],
            columns_resizable: true,
            columns_hidden: ['id','objtype']
          });
          objlist.control.append( $('<input/>', { class: 'tblwrap-control-search-left' }).on('keyup', function() { objlist.table.search(this.value); }) );
          objlist.content.append(objlist.table.html());
          objlist.footer.append(`Objects: ${relations.length}`);
          objlist.table.html().on('click', 'tr', function () {
            rellist = { table: $(table.html()) };
            rellist.table.find('tbody').append($(this));
            rellist.table.find('.obTable-tb').obTableRedraw();
            cnt = 0;
            $.each(rellist.table.find('tbody').find('tr'), function() {
              cnt++;
            });
            rellist.table.parent('div').parent('div').find('.tblwrap-footer').html(`Relations: ${cnt}`);
            popup.overlay.remove();
          });
          $(objlist.table.html()).find('tbody').children('tr').each(function() {
            $(this).addClass('pointer');
          });

          popup.wrapper.append(
            objlist.control,
            objlist.content,
            objlist.footer,
            popup.control.append(
              $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function() { popup.overlay.remove(); })
            )
          );

          popup.wrapper.find('.dataTables_filter').addClass('float-left');
          loader.remove();
        });
      }
      else {
        // Open relation -> show form
        let properties = $('<form/>');
        let btnunassign = '';
        if (acl_update) {
          btnunassign = $('<input/>', { class: 'btn', type: 'submit', value: 'Unassign' }).on('click', function() { row.addClass('delete'); popup.overlay.remove(); });
        }
        popup.wrapper.append(
          popup.control.append(
            btnunassign,
            $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function() { popup.overlay.remove(); })
          ),
          properties
        );

        let hdt = JSON.parse(row.attr('hdt'));
        $.when(
          api('get',`objecttype/${hdt.objtype}/object/${hdt.id}?format=gui`)
        )
        .done(function(apidata) {
          let usedata = {};
          $.each(apidata, function(key, value) {
            usedata[value.id]  = { type: 'string', title: value.label, default: value.value, readonly: true };
          });
          properties.jsonForm({
            schema: usedata,
            form: ['*']
          });
          loader.remove();
        })
        .fail(function() {
          properties.append('Failed to open object (no permissions?)');
          loader.remove();
        });
      }
    }
  }
}