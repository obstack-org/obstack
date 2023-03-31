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
    let cname = $('<div/>');
    var controlbar = $('<div/>', { class: 'content-wrapper-control' });
    var objecttable = new datatable();
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(cname),
      $('<div/>', { class: 'content-wrapper' }).append(
        objecttable.html(),
        controlbar
      )
    );  
    // Load and display data
    content.append(loader.removeClass('fadein').addClass('fadein'));    
    $.when(
      api('get',`objecttype/${type}`),
      api('get',`objecttype/${type}/property`),
      api('get',`objecttype/${type}/object?format=gui`)
    ).done(function(apidata_type, apidata_properties, apidata_objects) {
      cname.append(apidata_type[0].name);
      objecttable.apidata(apidata_objects[0]);
      objecttable.apicolumns(apidata_properties[0]);
      objecttable.create();  
      objecttable.html().on('click', 'tr', function () {
        if (typeof objecttable.table().row(this).data() != 'undefined') {
          mod.obj.open(type, objecttable.table().row(this).data()[0]);
        }
      });
      controlbar.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function(event) {
            mod.obj.open(type, 'create');
          })
      );
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
        api('get',`objecttype/${type}`),
        api('get',`objecttype/${type}/property`)
      ).done(function(api_objtype, api_objproperty) {
        mod.obj.open_htgen(type, id, api_objtype[0], api_objproperty[0], { id: type }, [{ name:'[new]' }], {}, {});
      });
    }
    else {
      $.when( 
        api('get',`objecttype/${type}`),
        api('get',`objecttype/${type}/property`),
        api('get',`objecttype/${type}/object/${id}`),
        api('get',`objecttype/${type}/object/${id}?format=short`),
        api('get',`objecttype/${type}/object/${id}/relation`),
        api('get',`objecttype/${type}/object/${id}/log`)
      ).done(function(api_objtype, api_objproperty, api_obj, api_obj_short, api_relation, api_log) {
        mod.obj.open_htgen(type, id, api_objtype[0], api_objproperty[0], api_obj[0], api_obj_short[0], api_relation[0], api_log[0]);
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
  open_htgen: function(type, id, api_objtype, api_objproperty, api_obj, api_obj_short, api_relation, api_log) {
    // Generate HTML with loader
    var stabs = $('<div/>', { class: 'content-tab' });
    var cname = $('<div/>');
    var controlbar = $('<div/>', { class: 'content-wrapper-control-right' });
    var properties = $('<form/>'); 
    var relations = new datatable();
    var relations_controls = $('<div/>', { class: 'content-wrapper-tab-control' });
    let logtable = $('<table/>', { class: 'log-table' });
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(cname), 
      $('<div/>', { class: 'content-wrapper' }).append(stabs, controlbar)
    );
    var stabs_tabs = [
      { title: 'Properties',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(properties) }, 
      { title: 'Relations',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(relations.html(), relations_controls ) },
    ];
    content.append(loader);

    // Load and display data    
    if (api_objtype.log) {
      stabs_tabs = [...stabs_tabs, { title: 'Log',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(logtable) }];
    }
    cname.append(
        $('<a/>', {
          class: 'link',
          html: api_objtype.name,
          click: function() { mod.obj.list(type); }
        }),
        ` / ${api_obj_short[0].name}`
      );
    stabs.simpleTabs({
      tabs: stabs_tabs
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
        // Remaining 'low config' fields (checkbox, datetime etc)
        if (cfg_value.type == def.prop.checkbox.id) { checkboxdata[cfg_value.id] = { value: value, readonly: cfg_value.frm_readonly }; }
        if (cfg_value.type == def.prop.date.id)     { formfield['type'] = 'date'; }
        if (cfg_value.type == def.prop.datetime.id) { formfield['type'] = 'datetime-local'; }
        // Prepare data for jsonForm        
        usedata[cfg_value.id]  = { type: mod.obj.jftypes[cfg_value.type], title: cfg_value.name, default: value, required: cfg_value.required, readonly: cfg_value.frm_readonly };
        formdata = [...formdata, formfield];
      }
    });
    properties.jsonForm({
      schema: usedata,
      form: formdata
    });

    // Add error field
    properties.find(':input').each(function() {
      $('<span class="jsonform-errortext"></span>').insertBefore($(this).parent());
    });
    
    // Select population counter
    let selectfilled = 0;

    // Populate objtype fields
    $.each(objtypes, function(tindex,tvalue) {
      let ptype = properties.find(`select[name=${tindex}]`);
      $.when( 
        api('get',`objecttype/${tvalue.srcid}/object?format=short`)
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

    // Populate valuemap fields
    $.each(valuemaps, function(tindex,tvalue) {
      let ptype = properties.find(`select[name=${tindex}]`);
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
      let ptype = properties.find(`input[name=${tindex}]`);
      ptype.prop('checked', (tvalue.value == 1));
      if (tvalue.readonly) {
        $(ptype).on('click', function () { return false; });
      }
    });

    // Add relations buttons
    relations_controls.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
            mod.obj.relations.open(type, id, relations);
        })
    );

    // Populate relations table
    $.fn.dataTable.ext.errMode = 'none';
    relations.apidata(api_relation);
    relations.options({
        columns: [{title:'[id]'}, {title:'[objtype]'}, {title:'Type'}, {title:'Fields'}, {title:''}, {title:''},  {title:''}], 
        columnDefs: [...relations.getoptions().columnDefs, { targets: [0,1], visible: false }, { targets:[4,5,6], orderable:false }]
      }); 
    relations.create();   
    relations.html().on('click', 'tr', function () {
      if (typeof relations.table().row(this).data() != 'undefined') {
        const rnode = $(relations.table().row(this).node());
        if (rnode.hasClass('delete')) {
          if (confirm('Do you want to remove the unassign mark?')) {
            rnode.removeClass('delete');
          }
        }
        else {
          mod.obj.relations.open(type, id, relations.table().row(this));
        }          
      }
    });

    // Add object type buttons
    controlbar.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.obj.save(type, id, properties, relations); }),
      $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) { 
        if (confirm('WARNING!: This action wil permanently delete this object and its values. Are you sure you want to continue?')) {
          $.when( api('delete',`objecttype/${type}/object/${id}`) ).always(function() { mod.obj.list(type); });
        }
      }),
      $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.obj.list(type); })
    );

    // Populate log field
    if (api_objtype.log) {
      logtable.append(
        $('<th/>', { class: 'log-th', width: 160}).text('Date/Time'),
        $('<th/>', { class: 'log-th', width: 100 }).text('User'),
        $('<th/>', { class: 'log-th', width: 100 }).text('Action'),
        $('<th/>', { class: 'log-th', width: 450 }).text('Details')
      );
      $.each(api_log, function(tindex,tvalue) {
        logtable.append(
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
    relations.table().rows().every(function() {
      if (!$(this.node()).hasClass('delete')) {
        dtsave.relations = [...dtsave.relations, this.data()[0]];
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
     *   rtable   : Relation as selected in the table
     ******************************************************************/
    open: function(type, id, rtable) {
      let tblnewrec = (rtable.constructor.name == 'datatable');

      // Generate HTML
      let overlay = $('<div/>');
      let wrapper = $('<div/>', { class: 'content-popup-wrapper' });
      let controlbar = $('<div/>', { class: 'content-popup-wrapper-control' });
      $('#_sTab1').append(overlay);
      overlay.append(
        $('<div/>', { class: 'content-popup-overlay' }).append(
          wrapper
        )
      );

      // Load and display data
      wrapper.append(loader);
      if (tblnewrec) {
        // Add relation -> show list
        $.fn.dataTable.ext.errMode = 'none';
        let objecttable = new datatable();
        wrapper.append(
          objecttable.html(),
          controlbar.append(
            $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
          )
        );
        $.when( 
          api('get',`/objecttype/${type}/object/${id}/relation/available?format=gui`)          
        ).done(function(relations) {
          // Filter out selected
          let relations_selected = [];
          $.each(rtable.table().rows().data(), function( index, value ) {
            relations_selected = [...relations_selected, value[0]];
          });
          $.each(relations, function( index, value ) {
            if (value.id == id) {
              delete relations[index];
            }
            if ( relations_selected.indexOf(value.id) != -1) {
              delete relations[index];
            }
          });
          // Clean up empty slots created by delete action
          relations = relations.flat();
          // Generate table
          objecttable.apidata(relations);
          objecttable.options({
            aaSorting: [],
            columns: [{title:'[id]'}, {title:'[objtype]'}, {title:'Type'}, {title:'Fields'}, {title:''}, {title:''},  {title:''}], 
            columnDefs: [...objecttable.getoptions().columnDefs, { targets: [0,1], visible: false }, { targets:[4,5,6], orderable:false }]
          }); 
          objecttable.create();   
          objecttable.html().on('click', 'tr', function () {
            rtable.addrow(objecttable.table().row(this).data());
            overlay.remove();            
          });
          wrapper.find('.dataTables_filter').addClass('float-left');
          loader.remove();
        });
      }
      else {
        // Open relation -> show form
        let properties = $('<form/>');          
        wrapper.append(
          controlbar.append(              
            $('<input/>', { class: 'btn', type: 'submit', value: 'Unassign' }).on('click', function(event) { $(rtable.node()).addClass('delete'); overlay.remove(); }),
            $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
          ),
          properties         
        );
        $.when( 
          api('get',`objecttype/${rtable.data()[1]}/object/${rtable.data()[0]}?format=gui`)
        ).done(function(apidata) {
          let usedata = {};
          $.each(apidata, function(key, value) {
            usedata[value.id]  = { type: 'string', title: value.label, default: value.value, readonly: true };
          });
          properties.jsonForm({
            schema: usedata,
            form: ['*']
          });
          loader.remove();
        });        
      }
    }
  }
}