/******************************************************************
 * 
 * mod
 *  .valuemap
 *    .list()           Function
 *    .open()           Function
 *    .open_htgen()     Function
 *    .save()           Function
 *    .value           Array
 *      .open()         Function
 * 
 ******************************************************************/

 mod['valuemap'] = {

  /******************************************************************
   * mod.valuemap.list()
   * ===================
   * List value maps
   ******************************************************************/
  list: function() {
    // Generate HTML with loader
    var controlbar = $('<div/>', { class: 'content-wrapper-control' });
    var dtobjtypes = new datatable();
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html('Value maps'),
      $('<div/>', { class: 'content-wrapper' }).append(
        dtobjtypes.html(),
        controlbar
      )
    );
    content.append(loader);

    // Load and display data
    $.when( 
      api('get','valuemap')
    ).done(function(apidata) {
      dtobjtypes.apidata(apidata);
      dtobjtypes.options({
        aaSorting: [],
        columns: [{title:'[id]'}, {title:'Value map'}],
        columnDefs: [...dtobjtypes.getoptions().columnDefs, { targets:[1], orderable:false }]
      });
      dtobjtypes.create();   
      dtobjtypes.html().on('click', 'tr', function () {
        if (typeof dtobjtypes.table().row(this).data() != 'undefined') {
          mod.valuemap.open(dtobjtypes.table().row(this).data()[0]);
        }
      });
      controlbar.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function(event) {
            mod.valuemap.open(null);
          })
      );
      loader.remove();
    });
  },

  /******************************************************************
   * mod.valuemap.open(id)
   * =====================
   * Open a value map for editingp
   *   id       : Value map UUID
   ******************************************************************/
  open: function(id) {
    // New
    if (id == null) {
      mod.valuemap.open_htgen(id, { name:'[new]', prio: false }, {});
    }
    // Open
    else {
      $.when(
        api('get',`valuemap/${id}`),
        api('get',`valuemap/${id}/value`)
      )
      .done(function(api_conf, api_value) {
        mod.valuemap.open_htgen(id, api_conf[0], api_value[0]);
      });
    }
  },

  /******************************************************************
   * mod.valuemap.open_htgen()
   * =========================
   * Open a value map for editing
   *   id       : Valuemap id
   *   api_..   : API data
   ******************************************************************/
  open_htgen: function(id, api_conf, api_value) {
    // Generate HTML with loader
    var stabs = $('<div/>', { class: 'content-tab' });
    var cname = $('<div/>');
    var controlbar = $('<div/>', { class: 'content-wrapper-control-right' });
    var config = $('<form/>');
    var log = $('<table/>', { class: 'log-table' });
    var value = new datatable();  
    var value_controls = $('<div/>', { class: 'content-wrapper-tab-control' });
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(cname),
      $('<div/>', { class: 'content-wrapper' }).append(stabs, controlbar)
    );
    content.append(loader);

    // Load and display data
    cname.empty().append(
      $('<a/>', {
        class: 'link',
        html: 'Value maps',
        click: function() { mod.valuemap.list(); }
      }),
      ` / ${api_conf.name}`
      );
    stabs.simpleTabs({
      tabs: [      
        { title: 'Value map', html: $('<div/>', { class: 'content-tab-wrapper' }).append(config) }, 
        { title: 'values',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(value.html(), value_controls ) }
      ]
    });
    config.jsonForm({
      schema: { 
        name: { title: 'Name',  type: 'string', default: api_conf.name },
        prio: { title: 'Sorting by <info title="Applied after save">&#x1F6C8;</info>', type: 'select', enum: [] }
      },
      form: ['*'],
    });

    // Set Order by value and for table
    prio = config.find(`select[name=prio]`);
    prio.append($('<option/>').text('Order').val(1));
    prio.append($('<option/>').text('Name').val(0));
    let dstmp = 0;
    let value_hidecolumns = [0,1,2];
    let value_rowReorder = false;
    if (api_conf.prio) {
      dstmp = 1;
      value_hidecolumns = [0,1];
      value_rowReorder = true;
    }
    prio.val(dstmp);

    // Add value buttons
    value_controls.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
            mod.valuemap.value.open(value);
        })
    );

    // Load, prepare and fill value table
    var prio = 0;
    $.each(api_value, function(index, value) {
      prio = prio + 1;
      api_value[index] = { 
        prio: prio,
        '[data]': value,
        '':'<move>&#8645;</move>',
        label: value.name
      };
    });
    
    value.apidata(api_value);
    value.options({
      rowReorder: value_rowReorder,
      columns: [{title:'[prio]'}, {title:'[data]'}, {title:''}, {title:'Name'}],
      columnDefs: [
        { targets: '_all',  className:  'dt-head-left', orderable: false },            
        { targets: value_hidecolumns,  visible: false },
        { targets: [0,1,2],  searchable: false },
        { targets: [2], width: '10px' }
      ],
    });
    value.create();

    // Click event for value table
    value.html().on('click', 'tr', function () {
      const rnode = $(value.table().row(this).node())
      if (rnode.hasClass('delete')) {
        if (confirm('Do you want to remove the deletion mark?')) {
          rnode.removeClass('delete');
        }
      }
      else {
        if (typeof value.table().row(this).data() != 'undefined') {
          mod.valuemap.value.open(value.table().row(this));
        }
      }
    });

    // Add object type buttons
    controlbar.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.valuemap.save(id, config, value); })
    );
    if (id != null) {
      controlbar.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) { 
          if (confirm('WARNING!: This action wil permanently delete this valuemap, affecting all concerning objects. Are you sure you want to continue?'))
            if (confirm('WARNING!: Deleting valuemap. This can NOT be undone, are you really really sure?')) {
              $.when( api('delete',`valuemap/${id}`) ).always(function() { mod.valuemap.list(); });
            }          
          }
        )
      );
    }
    controlbar.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.valuemap.list(); })
    );

    loader.remove();
  },

  /******************************************************************
   * mod.valuemap.save(id, config, vtable)
   * =====================================
   * Save object type value as stated in the browser
   *   id        : Value map UUID
   *   config    : Config fields (first tab)
   *   vtable    : value table (second tab)
   ******************************************************************/
   save: function(id, config, vtable) {
    // Prepare data formats 
    dtsave = { value: [] };
    dtdel  = { value: [] };

    // Gather data from config fields
    config.find(':input').each(function() {
      dtsave[$(this).prop('name')] = $(this).prop('value');
    });

    // Gather data from table
    vtable.table().rows().every(function() {
      if ($(this.node()).hasClass('delete')) {
        dtdel.value = [...dtdel.value, { id: this.data()[1].id }];
      }
      else {
        dtsave.value = [...dtsave.value, this.data()[1]];
      }
    });

    // Send data to API, with confirmation if required
    let save = false;
    if (dtdel.value.length > 0) {
      if (confirm('WARNING!: This action wil permanently delete one or more values, affecting all concerning objects. Are you sure you want to continue?')) {
        save = true;
      }
    } 
    else {
      save = true;
    }
    if (save) {
      if (id == null) {
        $.when( api('post','valuemap',dtsave) ).always(function() { mod.valuemap.list(); });
      }
      else {
        $.when( api('put',`valuemap/${id}`,dtsave) ).always(function() { mod.valuemap.list(); });
      }
    }
  },

  /******************************************************************
   * mod.valuemap.value
   * ==================
   * Array of functions and subarrays for editing values
   ******************************************************************/ 
  value: {
    
    /******************************************************************
     * mod.valuemap.value.open(vtable)
     * ==================
     * Open the value form
     *   vtable   : Value as selected in the table
     ******************************************************************/
    open: function(vtable) {
      let frmnewrec = (vtable.constructor.name == 'datatable');

      // Generate HTML with loader
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }
      let vform = $('<form/>');      
      var overlay = $('<div/>');
      let popup_control = $('<div/>', { class: 'content-popup-wrapper-control' });
      $('#_sTab1').append(overlay);
      overlay.append(
        $('<div/>', { class: 'content-popup-overlay' }).append(
          $('<div/>', { class: 'content-popup-wrapper content-popup-wrapper_small' }).append(popup_control, vform)
        )
      );

      // Ok/Create button
      popup_control.append(        
        $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) {
          // Prepare form data
          let fdata = {};
          vform.find(':input').each(function() {
            fdata[$(this).prop('name')] = $(this).prop('value');
          });
          // Prepare internal data (stored inside row)
          let idata = {
            id: null, 
            name: fdata.name
          };
          // Prepare row data
          let rdata = [
            idata, 
            '&nbsp;⇅', 
            fdata.name
          ];
          if (frmnewrec) {
            vtable.addrow([9999, ...rdata]);
          }
          else {
            rdata[0].id = vtable.data()[1].id;
            vtable.data([vtable.data()[0], ...rdata]).draw(false);
          }
          if (!frmnewrec) {
            $(vtable.node()).addClass('save');
          }
          overlay.remove();
        })
      );

      // Delete button
      if (!frmnewrec) {
        popup_control.append( 
          $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) {
            // Mark for deletion
            if (vtable.data()[1].id != null) {
              if (confirm('Are you sure you want to mark this item for deletion?')) {
                $(vtable.node()).addClass('delete');
                overlay.remove();
              }
            }
            else {
              vtable.remove().draw();
              overlay.remove();
            }                
          })
        );
      }

      // Close button
      popup_control.append( 
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { overlay.remove(); })
      );

      // Generate form
      vform.jsonForm({
        schema: { 'name':  {title:'Name', type:'string'} },
        form: ['*']
      });      

      // Load data into form for editing
      if (!frmnewrec) {
        vform.find('input[name=name]').val(vtable.data()[1].name);        
      }
    }
  }
}