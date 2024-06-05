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

    // Loader
    state.set('valuemap');
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get','valuemap')
    ).done(function(api_valuemap) {
      let vmlist = new obFTable({
        table: {
          id: 'ee166cbe41ceee1a5b6a1b7cc814ef6ad7e0b4c4',
          data: api_valuemap,
          columns: [{ id:'name', name:'Value map', orderable:true }],
          columns_resizable: false,
          columns_hidden: ['id']
        },
        search:   true,
        create:   function() { mod.valuemap.open(null); },
        open:     function(td) {
          content.empty().append(loader.removeClass('fadein').addClass('fadein'));
          mod.valuemap.open(JSON.parse($(td).parent().attr('hdt')).id);
        },
        footer:   'Value maps'
      });

      content.empty().append(new obContent({
        name: [ $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }), 'Value maps' ],
        content: vmlist.html()
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });

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
      mod.valuemap.open_htgen(id, { prio:false }, {});
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

    // Load data
    let vallist = new obFTable({
      table:
      {
        id: '9a6a3494b2d1aabc999e07bb13cb7a6f0fd0e14c',
        data: api_value,
        columns: [{ id:'name', name:'Name' }],
        columns_resizable: false,
        columns_hidden: ['id'],
        sortable: api_conf.prio
      },
      search:   false,
      create:   function() { mod.valuemap.value.open(vallist, null, api_conf.prio); },
      open:     function(td) {
        let tr = $(td).parent();
        if (tr.hasClass('delete')) {
          obAlert('Do you want to remove the deletion mark?', { Ok:function(){
            tr.removeClass('delete');
          }, Cancel:null });
        }
        else {
          mod.valuemap.value.open(vallist.table().html(), tr, api_conf.prio);
        }
      },
      footer:   'Values'
    });

    let vmform = new obForm([
      { id:'name', name:'Name',       type:'string', regex_validate:/^.+/, value:api_conf.name },
      { id:'prio', name:'Sorting by', type:'select', options:{ 0:'Name', 1:'Order'}, info:'Change requires save to activate', value:(api_conf.prio)?1:0 }
    ]);

    let obtabs = new obTabs({ tabs: [
      { title:'Value map', html:vmform.html() },
      { title:'Values',    html:$('<div/>', { class:'content-tab-wrapper' }).append( vallist.html() ) }
    ]});

    content.empty().append(new obContent({
      name: [
        $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }),
        $('<a/>', { class:'link', html:'Value maps', click:function() { change.check(function() { mod.valuemap.list(); }); } }), ` / ${(id==null)?'[new]':api_conf.name}`
      ],
      content: obtabs.html(),
      control: [
        // -- Save
        $('<input/>', { class:'btn', type:'submit', value:'Save'  }).on('click', function() {
          let vmform_data = vmform.validate();
          if (vmform_data != null) {
            change.reset();
            mod.valuemap.save(id, vmform_data, vallist.table());
          }
          else {
            obtabs.showtab('_obTab0');
          }
        }),
        // -- Delete
        (id==null)?null:$('<input/>', { class:'btn', type:'submit', value:'Delete'  }).on('click', function() {
          obAlert('<b>WARNING!:</b><br>This action wil permanently delete this valuemap, affecting all concerning objects. Are you sure you want to continue?', { Ok:function(){
            obAlert('<b>WARNING!:</b><br>Deleting valuemap. This can NOT be undone, are you really really sure?', { Ok:function(){
              $.when( api('delete', `valuemap/${id}`) ).always(function() {
                change.reset();
                mod.valuemap.list();
              });
            }, Cancel:null });
          }, Cancel:null });
        }),
        // -- Close
        $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() {
          change.check(function() { mod.valuemap.list(); });
        })
      ]
    }).html());

    loader.remove();
    change.observe();

  },

  /******************************************************************
   * mod.valuemap.save(id, config, vallist)
   * =====================================
   * Save object type value as stated in the browser
   *   id        : Value map UUID
   *   config    : Config fields (first tab)
   *   vallist   : value table (second tab)
   ******************************************************************/
   save: function(id, vmform, vallist) {

    // Prepare data formats
    vallist = vallist.html();
    let save = true;
    let value = [];

    // Confirm on value delete(s)
    $.each(vallist.find('tbody').children('tr'), function() {
      let tr = $(this);
      if (tr.hasClass('delete')) {
        save = false;
      }
      else {
        value = [...value, { id:JSON.parse(tr.attr('hdt')).id, name:tr.find('td:not(.obTable-drag)').text() } ];
      }
    });

    // Save
    if (!save) {
      obAlert('<b>WARNING!:</b><br>This action wil permanently delete one or more values, affecting all concerning objects. Are you sure you want to continue?', { Ok:function(){
        if (id == null) {
          $.when( api('post','valuemap', { name:vmform.name, prio:(vmform.prio==1), value:value } ) ).always(function() { mod.valuemap.list(); });
        }
        else {
          $.when( api('put',`valuemap/${id}`, { name:vmform.name, prio:(vmform.prio==1), value:value } ) ).always(function() { mod.valuemap.list(); });
        }
      }, Cancel:null });
    }
    else {
      if (id == null) {
        $.when( api('post','valuemap', { name:vmform.name, prio:(vmform.prio==1), value:value } ) ).always(function() { mod.valuemap.list(); });
      }
      else {
        $.when( api('put',`valuemap/${id}`, { name:vmform.name, prio:(vmform.prio==1), value:value } ) ).always(function() { mod.valuemap.list(); });
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
      let value = '';
      if (!frmnewrec) {
        value = row.find('td:not(.obTable-drag)').text();
      }
      let form = new obForm([{ id:'value', name:'Name', type:'string', value:value, regex_validate:/^.+/ }]);

      // Generate HTML with loader
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }

      let popup = new obPopup({
        size: 'small',
        content: form.html(),
        control: [
          // -- OK / Create
          $('<input/>', { class:'btn', type:'submit', value:btnsubmit }).on('click', function() {
            let newvalue = form.validate();
            if (newvalue != null) {
              newvalue = newvalue.value;
              if (frmnewrec) {
                table.addrow([newvalue]);
              }
              else if (rowsort) {
                $(row.find('td')[1]).text(newvalue);
              }
              else {
                $(row.find('td')[0]).text(newvalue);
              }
              $(table).find('.obTable-tb').obTableSort(0);
              popup.remove()
            }
          }),
          // -- Delete
          $('<input/>', { class:'btn', type:'submit', value:'Delete' }).on('click', function() {
            if (typeof row.attr('id') == 'undefined') {
              row.remove();
              popup.remove();
            }
            else {
              obAlert('Are you sure you want to mark this item for deletion?', { Ok:function(){
                row.addClass('delete');
                popup.remove();
              }, Cancel:null });
            }
          }),
          // -- Close
          $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
        ]
      });

      $('#_obTab1-content').append(popup.html());

    }
  }
}