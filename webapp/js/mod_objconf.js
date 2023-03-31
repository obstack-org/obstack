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
    var controlbar = $('<div/>', { class: 'content-wrapper-control' });
    var dtobjtypes = new datatable();
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html('Object types'),
      $('<div/>', { class: 'content-wrapper' }).append(
        dtobjtypes.html(),
        controlbar
      )
    );
    content.append(loader);

    // Load and display data
    $.when(
      api('get','objecttype'),
      api('get','valuemap')
    ).done(function(api_objtype, api_valuemap) {
      mod.objconf.objtypes = api_objtype[0];
      mod.objconf.valuemap = api_valuemap[0];
      frm.sidebar.show(mod.objconf.objtypes);
      dtobjtypes.apidata(mod.objconf.objtypes);
      dtobjtypes.options({
        aaSorting: [],
        columns: [{title:'[id]'}, {title:'Object Type'}],
        columnDefs: [...dtobjtypes.getoptions().columnDefs, { targets:[1], orderable:false }]
      });
      dtobjtypes.create();   
      dtobjtypes.html().on('click', 'tr', function () {
        if (typeof dtobjtypes.table().row(this).data() != 'undefined') {
          mod.objconf.open(dtobjtypes.table().row(this).data()[0]);
        }
      });
      controlbar.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function(event) {
            mod.objconf.open(null);
          })
      );
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
    // Generate HTML with loader
    var stabs = $('<div/>', { class: 'content-tab' });
    var cname = $('<div/>');
    var controlbar = $('<div/>', { class: 'content-wrapper-control-right' });
    var config = $('<form/>');
    var log = $('<table/>', { class: 'log-table' });
    var properties = new datatable();  
    var properties_controls = $('<div/>', { class: 'content-wrapper-tab-control' });
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(cname),
      $('<div/>', { class: 'content-wrapper' }).append(stabs, controlbar)
    );
    content.append(loader);

    // Load and display data
    cname.empty().append(
      $('<a/>', {
        class: 'link',
        html: 'Object types',
        click: function() { mod.objconf.list(); }
      }),
      ` / ${api_conf.name}`
      );
    stabs.simpleTabs({
      tabs: [      
        { title: 'Object Type',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(config) }, 
        { title: 'Properties',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(properties.html(), properties_controls ) }
      ]
    });
    config.jsonForm({
      schema: { 
        name:   { title: 'Name',  type: 'string', default: api_conf.name },
        short:  { title: 'Field in shortname', type: 'select', enum: [ '1', '2', '3', '4' ], default: '4' },
        log:    { title: 'Log', type: 'select', enum: [] }
      },
      form: ['*'],
    });

    // Set shortname value
    short = config.find(`select[name=short]`);
    if (id != null) {
      short.val(api_conf.short);
    }
    
    // Set Log options
    log = config.find(`select[name=log]`);
    log.append($('<option/>').text('Disabled').val(0));
    log.append($('<option/>').text('Enabled').val(1));
    let dstmp = 0;
    if (api_conf.log) dstmp = 1;
    log.val(dstmp);

    // Add properties buttons
    properties_controls.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
            mod.objconf.properties.open(properties);
        })
    );

    // Load, prepare and fill properties table
    var prio = 0;
    $.each(api_property, function(index, value) {
      prio = prio + 1;
      api_property[index] = { 
        prio: prio,
        '[data]': value,
        '':'<move>&#8645;</move>',
        label: value.name,
        type_name: mod.objconf.options.type[value.type],
        on_table: htbool(value.tbl_visible),
        on_form:  htbool(value.frm_visible)
      };
    }); 
    properties.apidata(api_property);
    properties.options({
      rowReorder: true,
      columns: [{title:'[prio]'}, {title:'[data]'}, {title:''}, {title:'Name'}, {title:'Type'}, {title:'Table'}, {title:'Form'}],
      columnDefs: [
        { targets: '_all',  className:  'dt-head-left', orderable: false },            
        { targets: [0,1],  visible: false },
        { targets: [0,1,2],  searchable: false },
        { targets: [2], width: '10px' },
        { targets: [5,6], width: '100px' }
      ],
    });    
    properties.create();
    
    // Click event for properties table
    properties.html().on('click', 'tr', function () {
      const rnode = $(properties.table().row(this).node())
      if (rnode.hasClass('delete')) {
        if (confirm('Do you want to remove the deletion mark?')) {
          rnode.removeClass('delete');
        }
      }
      else {
        if (typeof properties.table().row(this).data() != 'undefined') {
          mod.objconf.properties.open(properties.table().row(this));
        }
      }
    });

    // Add object type buttons
    controlbar.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.objconf.save(id, config, properties); }),
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
  save: function(id, config, ptable) {
    // Prepare data formats 
    dtsave = { property: [] };
    dtdel  = { property: [] };

    // Gather data from config fields
    config.find(':input').each(function() {
      dtsave[$(this).prop('name')] = $(this).prop('value');
    });

    // Gather data from table
    ptable.table().rows().every(function() {
      if ($(this.node()).hasClass('delete')) {
        dtdel.property = [...dtdel.property, { id: this.data()[1].id }];
      }
      else {
        dtsave.property = [...dtsave.property, this.data()[1]];
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
     *   ptable   : Property as selected in the table
     ******************************************************************/
    open: function(ptable) {
      let frmnewrec = (ptable.constructor.name == 'datatable');

      // Generate HTML with loader
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }
      let pform = $('<form/>');
      var overlay = $('<div/>');
      $('#_sTab1').append(overlay);
      overlay.append(
        $('<div/>', { class: 'content-popup-overlay' }).append(
          $('<div/>', { class: 'content-popup-wrapper' }).append(
            $('<div/>', { class: 'content-popup-wrapper-control' }).append(

              // Click event for Ok/Create button
              $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) { 
                
                // Prepare form data
                let fdata = {};
                pform.find(':input').each(function() {
                  fdata[$(this).prop('name')] = $(this).prop('value');
                });

                // Prepare internal data (stored inside row)
                let idata = {
                  id: null, 
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
                let rdata = [
                  idata, 
                  '&nbsp;⇅', 
                  fdata.name, 
                  mod.objconf.options.type[fdata.type], 
                  htbool(idata.tbl_visible),
                  htbool(idata.frm_visible)
                ];
                if (frmnewrec) {
                  ptable.addrow([9999, ...rdata]);
                }
                else {
                  rdata[0].id = ptable.data()[1].id;
                  ptable.data([ptable.data()[0], ...rdata]).draw(false);
                }
                if (!frmnewrec) {
                  $(ptable.node()).addClass('save');
                }
                overlay.remove();
              }),

              // Click event for Delete button button
              $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) { 

                // Mark for deletion
                if (ptable.data()[1].id != null) {
                  if (confirm('Are you sure you want to mark this item for deletion?')) {
                    $(ptable.node()).addClass('delete');
                    overlay.remove();
                  }
                }
                else {
                  ptable.remove().draw();
                  overlay.remove();
                }
                
              }),

              // Click event for Close button
              $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
            ),
            pform
          )
        )
      )
      
      // Generate form
      pform.jsonForm(mod.objconf.properties.form);
      pform.find('select[name=tsrc]').parent().parent().hide();
      $.each(['type', 'reqr', 'table', 'form'], function(tindex,tvalue) {
        ptype = pform.find(`select[name=${tvalue}]`);
        $.each(mod.objconf.options[tvalue], function(index,value) {
          ptype.append($('<option/>').text(value).val(index));
        });
      });

      // Load data into form for editing
      if (!frmnewrec) {
        let pelem = {
          name:   pform.find('input[name=name]'),
          type:   pform.find('select[name=type]'),
          tsrc:   pform.find('select[name=tsrc]'),
          reqr:   pform.find('select[name=reqr]'),
          table:  pform.find('select[name=table]'),
          form:   pform.find('select[name=form]')
        }
        pelem.name.val(ptable.data()[1].name);
        pelem.type.val(ptable.data()[1].type);
        if (ptable.data()[1].id != null) {
          pelem.type.attr('disabled', true);
        }
        if (ptable.data()[1].type == def.prop.select_objtype.id) {
          mod.objconf.properties.typechange(pelem.type,false);
          pelem.tsrc.val(ptable.data()[1].type_objtype);
        }
        if (ptable.data()[1].type == def.prop.select_valuemap.id) {
          mod.objconf.properties.typechange(pelem.type,false);
          pelem.tsrc.val(ptable.data()[1].type_valuemap);
        }
        if (ptable.data()[1].type == def.prop.checkbox.id) {
          mod.objconf.properties.typechange(pelem.type,false);
        }
        pelem.tsrc.attr('disabled', true);
        let dstmp = 0;
        if (ptable.data()[1].required) dstmp = 1;
        pelem.reqr.val(dstmp);
        dstmp = 9;
        if (ptable.data()[1].tbl_visible) dstmp = 1;
        if (ptable.data()[1].tbl_orderable) dstmp = 2;
        pelem.table.val(dstmp);
        dstmp = 9;
        if (ptable.data()[1].frm_visible) dstmp = 1;
        if (ptable.data()[1].frm_readonly) dstmp = 2;
        pelem.form.val(dstmp);
      }      
      pform.find('select[name=type]').change( function(event) {
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