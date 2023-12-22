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

    // Loader
    state.set('obj', [type]);
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get',`objecttype/${type}?format=aggr`),
      api('get',`objecttype/${type}/object?format=text&display=table`)
    ).done(function(apidata_type, apidata_objects) {
      apidata_type = apidata_type[0];
      apidata_objects = apidata_objects[0];

      let columns = [];
      let columns_remove = [];
      let columns_allowhtml = [];
      $.each(apidata_type.property, function(id, column) {
        if (column.tbl_visible) {
          columns = [...columns, { id:column.id, name:column.name, orderable:column.tbl_orderable}];
        }
        else {
          columns_remove = [...columns_remove, column.name];
        }
        if (column.type == 5) {
          columns_allowhtml = [...columns_allowhtml, column.id];
          $.each(apidata_objects, function(idx, rec) {
            apidata_objects[idx][column.id] = htbool(apidata_objects[idx][column.id]);
          });
        }
        if (column.type == 12) {
          columns_allowhtml = [...columns_allowhtml, column.id];
          $.each(apidata_objects, function(idx, rec) {
            apidata_objects[idx][column.id] = [
              ...apidata_objects[idx][column.id],
              '&emsp;', $('<img/>', { class:'pointer', src:'img/iccpy.png', width:12 }).on('click', function(event) {
                event.stopPropagation();
                mod.obj.properties.open(apidata_type.objecttype.id, apidata_objects[idx].id, column.id, true);
              }),
              '&ensp;', $('<img/>', { class:'pointer', src:'img/icmgn.png', width:12 }).on('click', function(event) {
                event.stopPropagation();
                mod.obj.properties.open(apidata_type.objecttype.id, apidata_objects[idx].id, column.id);
              }),
            ];
          });
        }
      });
      for (let i=0; i<apidata_objects.length; i++) {
        $.each(columns_remove, function(idx, name) {
          delete apidata_objects[i][name];
        });
      }

      let objlist = new obFTable({
        table: {
          id: type,
          data: apidata_objects,
          columns: columns,
          columns_resizable: true,
          columns_hidden: ['id'],
          columns_allowhtml: columns_allowhtml
        },
        search:   true,
        create:   (apidata_type.acl.create)?function() { mod.obj.open(type, null); } : null,
        open:     function(td) {
          mod.obj.open(type, JSON.parse($(td).parent().attr('hdt')).id);
        },
        footer:   'Objects'
      });

      content.empty().append(new obContent({
        name: $('<span/>', { text:apidata_type.objecttype.name }),
        content: objlist.html()
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });

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

    // Loader
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // New
    if (id == null) {
      $.when(
        api('get',`objecttype/${type}?format=aggr&display=edit`)
      ).done(function(apidata) {
        mod.obj.open_htgen(type, id, apidata);
      });
    }
    // Open
    else {
      $.when(
        api('get',`objecttype/${type}/object/${id}?format=aggr&display=edit`)
      ).done(function(apidata) {
        mod.obj.open_htgen(type, id, apidata);
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
  open_htgen: function(type, id, apidata) {
    let api_objtype = apidata.objecttype;
    api_objtype.acl = apidata.acl;

    let api_obj = [];
    let api_log = [];
    let api_obj_short = [{ name:apidata.name }];
    let api_relations = [];

    if (id != null) {
      api_relations = apidata.relation;
      api_obj = apidata.object;
      if (apidata.objecttype.log) {
        api_log = apidata.log;
      }
    }

    // Load
    let acl_save = (mod.user.self.sa)?true:(id==null)?api_objtype.acl.create:api_objtype.acl.update;
    let propform_data = [];
    let pwrecv = [];
    $.each(apidata.property, function(key, property) {
      propform_data = [
        ...propform_data, {
          id:property.id,
          name:property.name,
          type:def.property_type[property.type],
          value:(property.id in api_obj)?api_obj[property.id]:null,
          readonly:!acl_save
        }];
      if (property.type == 12) {
        pwrecv = [...pwrecv, property.id];
      }
     });
     let propform = new obForm(propform_data);
     let propform_html = propform.html();

    // Options for selects
    $.each(apidata.property, function(key, property) {
      if ([3, 4].includes(property.type)) {
        let select = propform_html.find(`#${property.id}`);
        let value = (property.id in api_obj)?api_obj[property.id]:null;
        select.empty();
        // Object Type
        if (property.type == 3) {
          let otdata = apidata.meta.objecttype[property.type_objtype];
          $.each(lsSort(otdata, 'name'), function(idx, option) {
            let htopt = $('<option/>').text(option.name).val(option.id);
            if (option.id == value) {
              htopt.attr('selected','selected');
            }
            select.append(htopt);
          });
        }
        // Value Map
        if (property.type == 4) {
          let vmdata = apidata.meta.valuemap[property.type_valuemap];
          $.each(vmdata, function(idx, option) {
            let htopt = $('<option/>').text(option.name).val(option.id);
            if (option.id == value) {
              htopt.attr('selected','selected');
            }
            select.append(htopt);
          });
        }
      }
    });

    // Relations
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

    let rellist = new obFTable({
      table: {
        id: `${type}_relations`,
        data: api_relations,
        columns: [
          { id: 'rtp', name:'Type', orderable: true },
          { id: 'rf1', name:'Field', orderable: true },
          { id: 'rf2', name:'', orderable: false },
          { id: 'rf3', name:'', orderable: false },
          { id: 'rf4', name:'', orderable: false }
        ],
        columns_resizable: true,
        columns_hidden: ['id', 'objtype']
      },
      search:   true,
      create:   (!acl_save)?null:function() { mod.obj.relations.open(type, id, rellist.table(), null); },
      open:     function(td) {
        let tr = $(td).parent();
        if (tr.hasClass('delete')) {
          if (confirm('Do you want to remove the deletion mark?')) {
            tr.removeClass('delete');
          }
        }
        else {
          mod.obj.relations.open(type, id, rellist.table(), tr, acl_save);
        }
      }
    });

    // Logs
    let logs = null;
    if (mod.user.self.sa && api_objtype.log && (id != null)) {
      logs =$('<table/>', { class: 'log-table' });
      logs.append(
        $('<th/>', { class: 'log-th', width: 160}).text('Date/Time'),
        $('<th/>', { class: 'log-th', width: 100 }).text('User'),
        $('<th/>', { class: 'log-th', width: 100 }).text('Action'),
        $('<th/>', { class: 'log-th', width: 450 }).text('Details')
      );
      $.each(api_log, function(idx, log) {
        logs.append(
          $('<tr/>').append(
            $('<td/>', { class: 'log-td' }).text(log.timestamp),
            $('<td/>', { class: 'log-td' }).text(log.username),
            $('<td/>', { class: 'log-td' }).text(def.logtype[parseInt(log.action)]),
            $('<td/>', { class: 'log-td' }).text(log.details)
          )
        )
      });
    }

    // Draw
    let obtabs = new obTabs({ tabs: [
      { title:'Properties', html:$('<div/>', { class:'content-tab-wrapper' }).append(propform_html) },
      { title:'Relations',  html:$('<div/>', { class:'content-tab-wrapper' }).append(rellist.html()) },
      (logs==null)?null:{ title:'Log', html:$('<div/>', { class:'content-tab-wrapper' }).append(logs) }
    ]});

    let obcontent = {
      name: [$('<a/>', { class:'link', text:`${api_objtype.name}`, click:function() { if (change.check()) { mod.obj.list(type); } } }), $('<span/>', { text:` / ${(id==null)?'[new]':api_obj_short[0].name}`})],
      content: obtabs.html(),
      control: [
        // -- Save
        (!acl_save)?null:$('<input/>', { class:'btn', type:'submit', value:'Save'  }).on('click', function() {
          let propform_data = propform.validate();
          if (propform_data != null) {
            change.reset();
            mod.obj.save(type, id, propform_data, rellist.table());
          }
          else {
            obtabs.showtab('_obTab0');
          }
        }),
        // -- Delete
        (id == null || !api_objtype.acl.delete)?null:$('<input/>', { class:'btn', type:'submit', value:'Delete'  }).on('click', function() {
          if (confirm('WARNING!: This action wil permanently delete this object, are you sure you want to continue?')) {
            $.when( api('delete',`objecttype/${type}/object/${id}`) ).always(function() {
              change.reset();
              mod.obj.list(type);
            });
          }
        }),
        // -- Close
        $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() {
          if (change.check()) { mod.obj.list(type); }
        })
      ]
    };

    content.empty().append(new obContent(obcontent).html());
    loader.remove();
    change.observe();

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
   save: function(type, id, obj_config, rellist) {

    // Prepare data formats
    let dtsave = obj_config;
    if (rellist != null) {
      dtsave['relations'] = [];
      $.each(rellist.html().find('tbody').children('tr'), function() {
        let tr = $(this);
        if (!tr.hasClass('delete')) {
          dtsave.relations = [...dtsave.relations, JSON.parse(tr.attr('hdt')).id];
        }
      });
    }

    if (id == null) {
      $.when(api('post',`objecttype/${type}/object`,dtsave)).always(function() { mod.obj.list(type); });
    }
    else {
      $.when( api('put',`objecttype/${type}/object/${id}`,dtsave) ).always(function() { mod.obj.list(type); });
    }

  },


  /******************************************************************
   * mod.obj.properties
   * ==================
   * Array of properties and subarrays for gathering individual
   * property data
   ******************************************************************/
  properties: {

    open: function(type, id, property, copy=false) {

      $.when(
        api('get',`objecttype/${type}/object/${id}/property/${property}`)
      ).done(function(apidata) {
        let value = CryptoJS.AES.decrypt(apidata.value, (new obBase).decode(basebq)).toString(CryptoJS.enc.Utf8);
        if (copy) {
          if (!window.isSecureContext) {
            alert('Copy to clipboard is only available on secure pages');
          }
          else {
            navigator.clipboard.writeText(value);
          }
        }
        else {
          console.log(value);
        }
      });

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

      // Loader
      let popup_loader = new obPopup({
        content: loader.removeClass('fadein').addClass('fadein'),
        control: null
      });
      $('#_obTab1-content').append(popup_loader.html());

      let newrel = (row == null);

      // Select new relation
      if (newrel) {

        $.when(
          api('get',`/objecttype/${type}/object/${id}/relation/available?format=aggr`)
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
            else if (relations_selected.indexOf(relations[idx].id) != -1) {
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
          });

          // Clean up empty slots created by delete action
          relations = relations.flat();

          // Table
          let objlist = new obFTable({
            table: {
              id: '1eba292da5c6bd11e8b501c462cbf896ba76ee6a',
              data: relations,
              columns: [
                { id: 'rtp', name:'Type', orderable: true },
                { id: 'rf1', name:'Field', orderable: true },
                { id: 'rf2', name:'', orderable: false },
                { id: 'rf3', name:'', orderable: false },
                { id: 'rf4', name:'', orderable: false }
              ],
              columns_resizable: true,
              columns_hidden: ['id','objtype']
            },
            search: true,
            open:   function(td) {
              let tr = $(td).parent();
              let rdata = [];
              $.each(tr.find('td'), function(idx, field) {
                rdata = [...rdata, $(field).text()];
              });
              let rrow = table.addrow(rdata);
              rrow.attr('hdt', tr.attr('hdt'));
              popup.remove();
            },
            footer: 'Objects'
          });

          // Popup
          let popup = new obPopup({
            content: objlist.html(),
            control: $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
          });
          popup.html().find('.tblwrap-control-search').removeClass('tblwrap-control-search').addClass('tblwrap-control-search-left');
          $('#_obTab1-content').append(popup.html());

          $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
          popup_loader.remove();

        });
      }

      // Show related object properties
      else {

        let hdt = JSON.parse(row.attr('hdt'));
        $.when(
          api('get',`objecttype/${hdt.objtype}/object/${hdt.id}?format=full`)
        ).fail(function() {
          popup_loader.remove();
        })
        .done(function(api_obj) {

          // Prepare
          let frmdata = [];
          $.each(api_obj, function(id, value) {
            if (id != 'id') {
              let field = { id:value.id, name:value.label, type:'string', value:value.value_text, readonly:true };
              if ($.inArray(value.type, [3, 4]) != -1) {
                field.type = "select";
                field.options = { 0:value.value_text };
                field.value = 0;
              }
              else if (value.type == 5) {
                field.type = "checkbox";
              }
              frmdata = [...frmdata, field];
            }
          });

          // Popup
          let popup = new obPopup({
            content: new obForm(frmdata).html(),
            control: [
              (!acl_update)?null:$('<input/>', { class:'btn', type:'submit', value:'Unassign' }).on('click', function() { row.addClass('delete'); popup.remove(); }),
              $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
            ]
          });
          popup.html().find('.tblwrap-control-search').removeClass('tblwrap-control-search').addClass('tblwrap-control-search-left');
          $('#_obTab1-content').append(popup.html());
          popup_loader.remove();

        });

      }
    }
  }
}