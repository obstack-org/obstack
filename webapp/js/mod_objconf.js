/******************************************************************
 *
 * mod
 *  .objconf
 *    .objtypes         Array
 *    .options          Array
 *    .list()           Function
 *    .open()           Function
 *    .open_htgen()     Function
 *    .save()           Function
 *    .properties       Array
 *      .form           Array
 *      .open()         Function
 *      .typechange()   Function
 *
 ******************************************************************/

mod['objconf'] = {

  /******************************************************************
   * mod.objconf.objtypes
   * ====================
   * Array of object types, global to enable loading data from API
   * only once. Loading data in declaration causes an infinite loop
   * on the login screen due to the error clause in api().
   ******************************************************************/
  objtypes: {},

  /******************************************************************
   * mod.objconf.valuemap
   * ====================
   * Array of value maps, global to enable loading data from API
   * only once.
   ******************************************************************/
   valuemap: {},

  /******************************************************************
   * mod.objconf.options
   * ====================
   * Array of options in select boxes, used in the properties table
   * (options.type) and on the create/edit form
   ******************************************************************/
  options: {
    type:   {},
    reqr:   { 0:'No', 1:'Yes' },
    table:  { 1:'Show', 2:'Show - Sortable', 9:'Hide' },
    form:   { 1:'Show', 2:'Show - ReadOnly', 9:'Hide' },
    short:  { 1:'Show', 9:'Hide' }
  },

  /******************************************************************
   * mod.objconf.list()
   * ==================
   * List object types
   ******************************************************************/
  list: function() {
    // Generate HTML with loader
    let obtlist = {
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
      $('<div/>', { class: 'content-header' }).html('Object types'),
      $('<div/>', { class: 'content-wrapper' }).append(
        obtlist.control,
        obtlist.content,
        obtlist.footer,
        ctwrap.control
      )
    );

    // Load and display data
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get','objecttype'),
      api('get','valuemap')
    ).done(function(api_objtype, api_valuemap) {
      mod.objconf.objtypes = api_objtype[0];
      mod.objconf.valuemap = api_valuemap[0];
      frm.sidebar.show($.extend(true, {}, mod.objconf.objtypes));
      loader.remove();

      obtlist.table = new obTable({
        id: 'fb1adcc5594d952d9410528faed3b1b70e309aee',
        data: mod.objconf.objtypes,
        columns: [{ id: 'objecttype', name:'ObjectType', orderable: false }],
        columns_resizable: false,
        columns_hidden: ['id']
      });
      obtlist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { objlist.table.search(this.value); }) );
      obtlist.content.append(obtlist.table.html());
      obtlist.footer.append(`Objects: ${mod.objconf.objtypes.length}`);
      obtlist.table.html().on('click', 'td', function () {
        content.empty().append(loader);
        mod.objconf.open(JSON.parse($(this).parent().attr('hdt')).id);
      });

      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function() {
            mod.objconf.open(null);
          })
      );

      $.each(content.find('.obTable-tb'), function() {
        $(this).obTableRedraw();
      });
      loader.remove();

    });
  },

  /******************************************************************
   * mod.objconf.open(id)
   * ==================
   * Open an object type for editing
   *   id     : Object type UUID
   ******************************************************************/
  open: function(id) {
    // New
    if (id == null) {
      mod.objconf.open_htgen(id, { name:'[new]', prio: false }, {});
    }
    // Open
    else {
      $.when(
        api('get',`objecttype/${id}`),
        api('get',`objecttype/${id}/property`)
      )
      .done(function(api_conf, api_property) {
        mod.objconf.open_htgen(id, api_conf[0], api_property[0]);
      });
    }
  },

  /******************************************************************
   * mod.objconf.open()
   * ==================
   * Generate page from API data
   *   id     : Object type UUID
   *   api_.. : API data
   ******************************************************************/
  open_htgen: function(id, api_conf, api_property) {
    // Generate HTML
    let objtype = {
      config:     $('<form/>', { class: 'content-form' }),
    }
    let proplist = {
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
    let columns_hidden = [];
    if (api_property.length > 0) {
      $.each(api_property[0], function(key,value) {
        columns_hidden = [...columns_hidden, key];
      });
    }
    $.each(api_property, function(idx, rec) {
      api_property[idx].htname  = api_property[idx].name;
      api_property[idx].httype  = mod.objconf.options.type[api_property[idx].type];
      api_property[idx].httable = htbool(api_property[idx].tbl_visible);
      api_property[idx].htform  = htbool(api_property[idx].frm_visible);
    });

    proplist.table = new obTable({
      id: 'e0886e40f251333fcb4d2bc3ed17ac90b397a3b5',
      data: api_property,
      columns: [
        { id: 'property_name',  name:'Name' },
        { id: 'property_type',  name:'Type' },
        { id: 'property_table', name:'Table' },
        { id: 'property_form',  name:'Form' }
      ],
      columns_resizable: true,
      columns_hidden: columns_hidden,
      sortable: true
    });
    proplist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { proplist.table.search(this.value); }) );
    proplist.content.append(proplist.table.html());
    proplist.footer.append(`Properties: ${api_property.length}`);
    proplist.table.html().on('click', 'td', function () {
      if (!$(this).hasClass('obTable-drag')) {
        tr = $(this).parent();
        if (tr.hasClass('delete')) {
          if (confirm('Do you want to remove the deletion mark?')) {
            tr.removeClass('delete');
          }
        }
        else {
          mod.objconf.properties.open(proplist.table.html(), tr);
        }
      }
    });

    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        ctwrap.tabs,
        ctwrap.control
      )
    );
    let ctabs = [
      { title: 'Object Type',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(objtype.config) },
      { title: 'Properties',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(
        proplist.control,
        proplist.content,
        proplist.footer,
        proplist.control
      )}
    ];

    ctwrap.name.append(
      $('<a/>', {
        class: 'link',
        html: 'Object types',
        click: function() { mod.objconf.list(); }
      }),
      ` / ${api_conf.name}`
      );
    ctwrap.tabs.obTabs({
      tabs: ctabs
    });

    // Config form
    objtype.config.jsonForm({
      schema: {
        name:   { title: 'Name',  type: 'string', default: api_conf.name },
        short:  { title: 'Field in shortname', type: 'select', enum: [ '1', '2', '3', '4' ], default: '4' },
        log:    { title: 'Log', type: 'select', enum: [] }
      },
      form: ['*'],
    });

    // Set shortname value
    short = objtype.config.find(`select[name=short]`);
    if (id != null) {
      short.val(api_conf.short);
    }

    // Set Log options
    log = objtype.config.find(`select[name=log]`);
    log.append($('<option/>').text('Disabled').val(0));
    log.append($('<option/>').text('Enabled').val(1));
    let dstmp = 0;
    if (api_conf.log) dstmp = 1;
    log.val(dstmp);

    // Add properties buttons
    proplist.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
            mod.objconf.properties.open(proplist.table.html(), null);
        })
    );

    // Add object type buttons
    ctwrap.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.objconf.save(id, objtype.config, proplist.table); }),
      $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) {
        if (confirm('WARNING!: This action wil permanently delete this object type, all related objects and all related values. Are you sure you want to continue?'))
          if (confirm('WARNING!: Deleting object type. This can NOT be undone, are you really really sure?')) {
            $.when( api('delete',`objecttype/${id}`) ).always(function() { mod.objconf.list(); });
          }
        }),
      $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.objconf.list(); })
    );
    loader.remove();

  },

  /******************************************************************
   * mod.objconf.save(type, config, ptable)
   * ======================================
   * Save object type values as stated in the browser
   *   type     : Object type UUID
   *   config   : Config fields (first tab)
   *   ptable   : Properties table (second tab)
   ******************************************************************/
  save: function(id, config, proplist) {
    proplist = proplist.html();

    // Prepare data formats
    dtsave = { property: [] };
    dtdel  = { property: [] };

    // Gather data from config fields
    config.find(':input').each(function() {
      dtsave[$(this).prop('name')] = $(this).prop('value');
    });

    // Gather data from table
    $.each(proplist.find('tbody').children('tr'), function(){
      let tr = $(this);
      let dt = JSON.parse(tr.attr('hdt'));
      if (tr.hasClass('delete')) {
        dtdel.property = [...dtdel.property, { id: dt.id }];
      }
      else {
        dtsave.property = [...dtsave.property, dt];
      }
    });

    // Send data to API, with confirmation if required
    let save = false;
    if (dtdel.property.length > 0) {
      if (confirm('WARNING!: This action wil permanently delete one or more properties and all concerning values. Are you sure you want to continue?')) {
        save = true;
      }
    }
    else {
      save = true;
    }
    if (save) {
      if (id == null) {
        $.when( api('post','objecttype',dtsave) ).always(function() { mod.objconf.list(); });
      }
      else {
        $.when( api('put',`objecttype/${id}`,dtsave) ).always(function() { mod.objconf.list(); });
      }
    }
  },

  /******************************************************************
   * mod.objconf.properties
   * ==================
   * Array of functions and subarrays for editing object properties
   ******************************************************************/
  properties: {

    /******************************************************************
     * mod.objconf.properties.form
     * ==================
     * Array for generating the properties form
     ******************************************************************/
    form: {
      schema: {
        name:   {title: 'Name', type: 'string' },
        type:   {title: 'Type', type: 'string', enum: [] },
        tsrc:   {title: 'Source', type: 'select', enum: [] },
        reqr:   {title: 'Required', type: 'select', enum: [] },
        table:  {title: 'Display in Table', type: 'string', enum: [] },
        form:   {title: 'Display on Form', type: 'string', enum: [] }
      },
      form: ['*']
    },

    /******************************************************************
     * mod.objconf.properties.open(ptable)
     * ==================
     * Open the properties form
     *   table   : Table containing properties
     *   row     : Selected row
     ******************************************************************/
    open: function(table, row) {
      let frmnewrec = (row == null);

      // Generate HTML
      let popup = {
        overlay:  $('<div/>'),
        wrapper:  $('<div/>', { class: 'content-popup-wrapper' }),
        control:  $('<div/>', { class: 'content-popup-wrapper-control' }),
        form:     $('<form/>')
      };
      $('#_obTab1-content').append(
        popup.overlay.append(
          $('<div/>', { class: 'content-popup-overlay' }).append(
            popup.wrapper.append(
              popup.form,
              popup.control
            )
          )
        )
      );

      let recid = null;
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
        row = $('<tr/>');
      }
      else {
        recid = JSON.parse(row.attr('hdt')).id;
      }

      // Click event for Ok/Create button
      popup.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) {
          // Prepare form data
          let fdata = {};
          popup.form.find(':input').each(function() {
            fdata[$(this).prop('name')] = $(this).prop('value');
          });
          // Prepare internal data (stored inside row)
          let idata = {
            id: recid,
            name: fdata.name,
            type: fdata.type,
            type_objtype: null,
            type_valuemap: null,
            required: (fdata.reqr == 1),
            frm_visible: (fdata.form < 9),
            frm_readonly: (fdata.form == 2),
            tbl_visible: (fdata.table < 9),
            tbl_orderable: (fdata.table == 2)
          };
          if (idata.type == def.prop.select_objtype.id) { idata.type_objtype  = fdata.tsrc; }
          if (idata.type == def.prop.select_valuemap.id) { idata.type_valuemap = fdata.tsrc; }
          // Prepare row data
          row
            .empty()
            .attr('id', null)
            .attr('hdt', JSON.stringify(idata))
            .append(
              $('<td/>', { class: 'obTable-drag' }).append('⇅'),
              $('<td/>').append(fdata.name),
              $('<td/>').append(mod.objconf.options.type[fdata.type]),
              $('<td/>').append(htbool(idata.tbl_visible)),
              $('<td/>').append(htbool(idata.frm_visible))
            );
          if (frmnewrec) {
            table.find('tbody').append(row);
          }
          else {
            row.addClass('save');
          }
          table.find('.obTable-tb').obTableRedraw();
          popup.overlay.remove();
        })
      );

      // Click event for Delete button button
      if (!frmnewrec) {
        popup.control.append(
          $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) {
            // Mark for deletion
            if (confirm('Are you sure you want to mark this item for deletion?')) {
              row.addClass('delete');
              popup.overlay.remove();
            }
          })
        );
      }

      // Click event for Close button
      popup.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { popup.overlay.remove(); })
      );

      // Generate form
      popup.form.jsonForm(mod.objconf.properties.form);
      popup.form.find('select[name=tsrc]').parent().parent().hide();
      $.each(['type', 'reqr', 'table', 'form'], function(tindex,tvalue) {
        ptype = popup.form.find(`select[name=${tvalue}]`);
        $.each(mod.objconf.options[tvalue], function(index,value) {
          ptype.append($('<option/>').text(value).val(index));
        });
      });

      // Load data into form for editing
      if (!frmnewrec) {
        let rdata = JSON.parse(row.attr('hdt'));
        let pelem = {
          name:   popup.form.find('input[name=name]'),
          type:   popup.form.find('select[name=type]'),
          tsrc:   popup.form.find('select[name=tsrc]'),
          reqr:   popup.form.find('select[name=reqr]'),
          table:  popup.form.find('select[name=table]'),
          form:   popup.form.find('select[name=form]')
        }
        pelem.name.val(rdata.name);
        pelem.type.val(rdata.type);
        if (rdata.id != null) {
          pelem.type.attr('disabled', true);
        }
        if (rdata.type == def.prop.select_objtype.id) {
          mod.objconf.properties.typechange(pelem.type,false);
          pelem.tsrc.val(rdata.type_objtype);
        }
        if (rdata.type == def.prop.select_valuemap.id) {
          mod.objconf.properties.typechange(pelem.type,false);
          pelem.tsrc.val(rdata.type_valuemap);
        }
        if (rdata.type == def.prop.checkbox.id) {
          mod.objconf.properties.typechange(pelem.type,false);
        }
        pelem.tsrc.attr('disabled', true);
        let dstmp = 0;
        if (rdata.required) dstmp = 1;
        pelem.reqr.val(dstmp);
        dstmp = 9;
        if (rdata.tbl_visible) dstmp = 1;
        if (rdata.tbl_orderable) dstmp = 2;
        pelem.table.val(dstmp);
        dstmp = 9;
        if (rdata.frm_visible) dstmp = 1;
        if (rdata.frm_readonly) dstmp = 2;
        pelem.form.val(dstmp);
      }
      popup.form.find('select[name=type]').change( function(event) {
        mod.objconf.properties.typechange($(event.target),true);
      });
    },

    /******************************************************************
     * mod.objconf.properties.typechange(target, animate)
     * ==================
     * Function to load, show or hide the tscr select element based on
     * the selected value type.
     *   target     : Select element
     *   animate    : Enable/disable animation (boolean)
     ******************************************************************/
    typechange: function(target, animate) {
      let anspeed = 0;
      if (animate) { anspeed = 200; }
      let pelem = {
        tsrc: target.closest('form').find('select[name=tsrc]'),
        reqr: target.closest('form').find('select[name=reqr]')
      }
      if ([
            def.prop.select_objtype.id,
            def.prop.select_valuemap.id,
            def.prop.checkbox.id
          ].indexOf(parseInt(target.val())) < 0) {
        pelem.tsrc.parent().parent().hide(anspeed);
        pelem.reqr.parent().parent().show(anspeed);
      }
      pelem.tsrc.find('option').remove();

      if (target.val() == def.prop.select_objtype.id) {
        $.each(mod.objconf.objtypes, function(index, type) {
          pelem.tsrc.append($('<option/>').text(type.name).val(type.id));
        });
        pelem.reqr.parent().parent().hide();
        pelem.tsrc.parent().parent().show(anspeed);
      }
      if (target.val() == def.prop.select_valuemap.id) {
        $.each(mod.objconf.valuemap, function(index, type) {
          pelem.tsrc.append($('<option/>').text(type.name).val(type.id));
        });
        pelem.reqr.parent().parent().hide();
        pelem.tsrc.parent().parent().show(anspeed);
      }
      if (target.val() == def.prop.checkbox.id) {
        pelem.reqr.parent().parent().hide(anspeed);
      }
    }
  }

}