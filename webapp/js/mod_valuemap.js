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
    let vmlist = {
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
        vmlist.control,
        vmlist.content,
        vmlist.footer,
        ctwrap.control
      )
    );

    // Load and display data
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get','valuemap')
    ).done(function(api_valuemap) {
      vmlist.table = new obTable({
        id: 'ee166cbe41ceee1a5b6a1b7cc814ef6ad7e0b4c4',
        data: api_valuemap,
        columns: [{ id: 'name', name:'Value map', orderable: true }],
        columns_resizable: false,
        columns_hidden: ['id']
      });
      vmlist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { objlist.table.search(this.value); }) );
      vmlist.content.append(vmlist.table.html());
      vmlist.footer.append(`Value maps: ${api_valuemap.length}`);
      vmlist.table.html().on('click', 'td', function () {
        content.empty().append(loader);
        mod.valuemap.open(JSON.parse($(this).parent().attr('hdt')).id);
      });

      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function() {
            mod.valuemap.open(null);
          })
      );

      $.each(content.find('.obTable-tb'), function() {
        $(this).obTableRedraw();
      });
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

    // Generate HTML
    let vmap = {
      config:     $('<form/>', { class: 'content-form' }),
    }
    let vallist = {
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

    vallist.table = new obTable({
      id: '9a6a3494b2d1aabc999e07bb13cb7a6f0fd0e14c',
      data: api_value,
      columns: [{ id: 'name', name:'Name' }],
      columns_resizable: false,
      columns_hidden: ['id'],
      sortable: api_conf.prio
    });
    vallist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { vallist.table.search(this.value); }) );
    vallist.content.append(vallist.table.html());
    vallist.footer.append(`Values: ${api_value.length}`);
    vallist.table.html().on('click', 'td', function () {
      tr = $(this).parent();
      if (tr.hasClass('delete')) {
        if (confirm('Do you want to remove the deletion mark?')) {
          tr.removeClass('delete');
        }
      }
      else {
        mod.valuemap.value.open(vallist.table.html(), tr, api_conf.prio);
      }
    });
    vallist.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
          mod.valuemap.value.open(vallist.table.html(), null, api_conf.prio);
        })
    );

    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        ctwrap.tabs,
        ctwrap.control
      )
    );
    let ctabs = [
      { title: 'Value map',  html: $('<div/>', { class: 'content-tab-wrapper' }).append( vmap.config ) },
      { title: 'Values',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(
        vallist.control,
        vallist.content,
        vallist.footer,
        vallist.control
      )}
    ];

    ctwrap.name.append(
      $('<a/>', {
        class: 'link',
        html: 'Value maps',
        click: function() { mod.valuemap.list(); }
      }),
      ` / ${api_conf.name}`
      );

    ctwrap.tabs.obTabs({
      tabs: ctabs
    });

    vmap.config.jsonForm({
      schema: {
        name: { title: 'Name',  type: 'string', default: api_conf.name },
        prio: { title: 'Sorting by <info title="Applied after save">&#x1F6C8;</info>', type: 'select', enum: [] }
      },
      form: ['*'],
    });

    // Set Order by value and for table
    prio = vmap.config.find(`select[name=prio]`);
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

    // Add object type buttons
    ctwrap.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.valuemap.save(id, vmap.config, vallist.table); })
    );
    if (id != null) {
      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) {
          if (confirm('WARNING!: This action wil permanently delete this valuemap, affecting all concerning objects. Are you sure you want to continue?'))
            if (confirm('WARNING!: Deleting valuemap. This can NOT be undone, are you really really sure?')) {
              $.when( api('delete',`valuemap/${id}`) ).always(function() { mod.valuemap.list(); });
            }
          }
        )
      );
    }
    ctwrap.control.append(
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
   save: function(id, config, vallist) {
    vallist = vallist.html();

    // Prepare data formats
    dtsave = { value: [] };
    dtdel  = { value: [] };

    // Gather data from config fields
    config.find(':input').each(function() {
      dtsave[$(this).prop('name')] = $(this).prop('value');
    });

    // Gather data from table
    $.each(vallist.find('tbody').children('tr'), function(){
      let tr = $(this);
      let dt = JSON.parse(tr.attr('hdt'));
      if (tr.hasClass('delete')) {
        dtdel.value = [...dtdel.value];
      }
      else {
        dt.name = tr.find('td:not(.obTable-drag)').text();
        dtsave.value = [...dtsave.value, dt ];
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
    open: function(table, row, rowsort) {
      let frmnewrec = (row == null);

      // Generate HTML
      let popup = {
        overlay:  $('<div/>'),
        wrapper:  $('<div/>', { class: 'content-popup-wrapper content-popup-wrapper_small' }),
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

      // Generate HTML with loader
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }

      // Ok/Create button
      popup.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) {
          // Prepare form data
          let fdata = {};
          popup.form.find(':input').each(function() {
            fdata[$(this).prop('name')] = $(this).prop('value');
          });
          if (frmnewrec) {
            row = $('<tr/>')
              .attr('id', null)
              .attr('hdt', JSON.stringify({ id:null }));
            if (rowsort) {
              row.append($('<td/>', { class:'obTable-drag' }).append('⇅'));
            }
            row.append($('<td/>').append(fdata.name));
            table.find('.obTable-tb').append(row);
          }
          else {
            row.empty();
            if (rowsort) {
              row.append($('<td/>', { class:'obTable-drag' }).append('⇅'));
            }
            row
              .append($('<td/>').append(fdata.name))
              .addClass('save');
          }

          if (!rowsort) {
            table.find('.obTable-tb').obTableSort(0);
          }
          table.find('.obTable-tb').obTableRedraw();
          popup.overlay.remove();
        })
      );

      // Delete button
      if (!frmnewrec) {
        popup.control.append(
          $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) {
            // Mark for deletion
            if (typeof row.attr('id') == 'undefined') {
              row.remove();
              popup.overlay.remove();
            }
            else {
              if (confirm('Are you sure you want to mark this item for deletion?')) {
                row.addClass('delete');
                popup.overlay.remove();
              }
            }
          })
        );
      }

      // Close button
      popup.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { popup.overlay.remove(); })
      );

      // Generate form
      popup.form.jsonForm({
        schema: { 'name':  {title:'Name', type:'string'} },
        form: ['*']
      });

      // Load data into form for editing
      if (!frmnewrec) {
        popup.form.find('input[name=name]').val(row.find('td:not(.obTable-drag)').text());
      }
    }
  }
}