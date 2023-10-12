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

    // Loader
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get','objecttype'),
      api('get','valuemap')
    ).done(function(api_objtype, api_valuemap) {
      mod.objconf.objtypes = api_objtype[0];
      mod.objconf.valuemap = api_valuemap[0];
      frm.sidebar.show(mod.objconf.objtypes);

      let obtlist = new obFTable({
        table: {
          id: 'fb1adcc5594d952d9410528faed3b1b70e309aee',
          data: mod.objconf.objtypes,
          columns: [{ id:'objecttype', name:'ObjectType', orderable:false }],
          columns_resizable: false,
          columns_hidden: ['id']
        },
        search:   true,
        create:   function() { mod.objconf.open(null); },
        open:     function(td) {
          content.empty().append(loader.removeClass('fadein').addClass('fadein'));
          mod.objconf.open(JSON.parse($(td).parent().attr('hdt')).id);
        },
        footer:   'Object Types'
      });

      content.empty().append(new obContent({
        name: 'Object Types',
        content: obtlist.html()
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });

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
      $.when(
        api('get',`objecttype/${id}/acl`)
      )
      .done(function(api_acl) {
        mod.objconf.open_htgen(id, { prio:false }, {}, api_acl);
      });
    }
    // Open
    else {
      $.when(
        api('get',`objecttype/${id}`),
        api('get',`objecttype/${id}/property`),
        api('get',`objecttype/${id}/acl`)
      )
      .done(function(api_conf, api_property, api_acl) {
        mod.objconf.open_htgen(id, api_conf[0], api_property[0], api_acl[0]);
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
  open_htgen: function(id, api_conf, api_property, api_acl) {

    content.empty().append(loader.removeClass('fadein').addClass('fadein'));

    // Form
    let obtform = new obForm([
      { id:'name',  name:'Name', type:'string', regex_validate:/^.+/, value:api_conf.name },
      { id:'short', name:'Fields in shortname', regex_validate:/^.+/, type:'select', options:{1:1, 2:2, 3:3, 4:4}, value:api_conf.short },
      { id:'log',   name:'Log', type:'select',  options:{0:'Disabled', 1:'Enabled'}, value:(api_conf.log)?1:0 },
    ]);

    // Properties
    let columns_hidden = [];
    if (api_property.length > 0) {
      $.each(api_property[0], function(key) {
        columns_hidden = [...columns_hidden, key];
      });
    }
    $.each(api_property, function(idx, rec) {
      api_property[idx].htname  = api_property[idx].name;
      api_property[idx].httype  = mod.objconf.options.type[api_property[idx].type];
      api_property[idx].httable = htbool(api_property[idx].tbl_visible);
      api_property[idx].htform  = htbool(api_property[idx].frm_visible);
    });

    let proplist = new obFTable({
      table: {
        id: 'e0886e40f251333fcb4d2bc3ed17ac90b397a3b5',
        data: api_property,
        columns: [
          { id:'property_name',  name:'Name' },
          { id:'property_type',  name:'Type' },
          { id:'property_table', name:'Table' },
          { id:'property_form',  name:'Form' }
        ],
        columns_resizable: true,
        columns_hidden: columns_hidden,
        sortable: true
      },
      search:   true,
      create:   function() { mod.objconf.properties.open(proplist, null); },
      open:     function(td) {
        if (!$(td).hasClass('obTable-drag')) {
          let tr = $(td).parent();
          if (tr.hasClass('delete')) {
            if (confirm('Do you want to remove the deletion mark?')) {
              tr.removeClass('delete');
            }
          }
          else {
            mod.objconf.properties.open(proplist, tr);
          }
        }
      }
    });

    // Access
    for (let i=0; i<api_acl.length; i++) {
      api_acl[i]['read']   = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['read'] });
      api_acl[i]['create'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['create'] });
      api_acl[i]['update'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['update'] });
      api_acl[i]['delete'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['delete'] });
    }

    let acclist = new obFTable({
      table: {
        id: 'f35f45de66157761e7d7e4b7361beb840a3ec9ef',
      data: api_acl,
      columns: [
        { id:'name', name:'Name' },
        { id:'read', name:'Read' },
        { id:'create', name:'Create' },
        { id:'update', name:'Update' },
        { id:'delete', name:'Delete' }
      ],
      columns_resizable: true,
      columns_hidden: ['id']
      },
      search: true
    });

    // Draw
    let obtabs = new obTabs({ tabs:[
      { title:'Object Type', html:obtform.html() },
      { title:'Properties',  html:$('<div/>', { class:'content-tab-wrapper' }).append(proplist.html()) },
      { title:'Access',      html:$('<div/>', { class:'content-tab-wrapper' }).append(acclist.html()) },
    ] });

    content.append(new obContent({
      name: [ $('<a/>', { class:'link', html:'Object types', click:function() { mod.objconf.list(); } }), ` / ${(id==null)?'[new]':api_conf.name}` ],
      content: obtabs.html(),
      control: [
        // -- Save
        $('<input/>', { class:'btn', type:'submit', value:'Save'  }).on('click', function() {
          let obtform_data = obtform.validate();
          if (obtform_data != null) {
            mod.objconf.save(id, obtform_data, proplist.table(), acclist.table());
          }
          else {
            obtabs.showtab('_obTab0');
          }
        }),
        // -- Delete
        $('<input/>', { class:'btn', type:'submit', value:'Delete'  }).on('click', function() {
          if (confirm('WARNING!: This action wil permanently delete this object type, all related objects and all related values. Are you sure you want to continue?')) {
            if (confirm('WARNING!: Deleting object type. This can NOT be undone, are you really really sure?')) {
              $.when( api('delete',`objecttype/${id}`) ).always(function() { mod.objconf.list(); });
            }
          }
        }),
        // -- Close
        $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { mod.objconf.list(); })
      ]
    }).html());

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
  save: function(id, objtype_config, proplist, acclist) {

    // Prepare data formats
    let dtsave = objtype_config;
    if (proplist != null) {
      dtsave['property'] = [];
      $.each(proplist.html().find('tbody').children('tr'), function() {
        let tr = $(this);
        if (!tr.hasClass('delete')) {
          dtsave.property = [...dtsave.property, JSON.parse(tr.attr('hdt'))];
        }
      });
    }

    let aclsave = [];
    $.each(acclist.html().find('tbody').children('tr'), function(idx, tr) {
      aclsave = [...aclsave, {
        'id'    : JSON.parse($(tr).attr('hdt')).id,
        'read'  : $(tr.cells[1]).find('input').prop('checked'),
        'create': $(tr.cells[2]).find('input').prop('checked'),
        'update': $(tr.cells[3]).find('input').prop('checked'),
        'delete': $(tr.cells[4]).find('input').prop('checked')
      }];
    });

    // Save
    if (id == null) {
      $.when(
        api('post','objecttype', dtsave)
      ).always(function(api_objecttype) {
        $.when(
          api('post',`objecttype/${api_objecttype.id}/acl`, aclsave)
        ).always(function() {
          mod.objconf.list();
        });
      });
    }
    else {
      $.when(
        api('put',`objecttype/${id}`, dtsave),
        api('put',`objecttype/${id}/acl`, aclsave)
      ).always(function() { mod.objconf.list(); });
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

      let newrec = (row == null);

      // Form
      let rdata = { id:null, name:null, type:null, type_objtype:null, type_valuemap:null, required:false, frm_visible:true, frm_readonly:false, tbl_visible:true, tbl_orderable:false };
      if (row != null) {
        rdata = JSON.parse(row.attr('hdt'));
      }
      let propform = new obForm([
        {id:'name', name:'Name', type: 'string', regex_validate:/^.+/, value:rdata.name },
        {id:'type', name:'Type', type: 'select', regex_validate:/^.+/, options: def.property, value:rdata.type, readonly:(!newrec&&rdata.id!=null) },
        {id:'tsrc', name:'Source', type: 'select', readonly:(!newrec&&rdata.id!=null) },
        {id:'reqr', name:'Required', type: 'select', options: { 0:'No', 1:'Yes' }, value:(rdata.required)?1:0 },
        {id:'table', name:'Display in Table', type: 'select', options: { 1:'Show', 2:'Show - Sortable', 9:'Hide' }, value:(rdata.tbl_visible)?((rdata.tbl_orderable)?2:1):9 },
        {id:'form', name:'Display on Form', type: 'select', options: { 1:'Show', 2:'Show - Readonly', 9:'Hide' }, value:(rdata.frm_visible)?((rdata.frm_readonly)?2:1):9 }
      ]);

      // Popup
      let popup = new obPopup({
        content: propform.html(),
        control: [
          // -- Create / Save
          $('<input/>', { class:'btn', type:'submit', value:(newrec)?'Create':'Save' }).on('click', function() {
            let propform_data = propform.validate();
            if (propform_data != null) {
              propform_data = $.extend(propform_data, {
                id: null,
                required:(propform_data.type=="1"),
                type_objtype:(propform_data.type=="3")?propform_data.tsrc:null,
                type_valuemap:(propform_data.type=="4")?propform_data.tsrc:null,
                tbl_visible:(propform_data.table<9),
                tbl_orderable:(propform_data.table==2),
                frm_visible:(propform_data.form<9),
                frm_readonly:(propform_data.form==2)
              });
              let rrow = (newrec)
                ? table.addrow([propform_data.name, def.property[propform_data.type], htbool(propform_data.table<=2), htbool(propform_data.form<=2)])
                : table.updaterow(row, [propform_data.name, def.property[propform_data.type], htbool(propform_data.table<=2), htbool(propform_data.form<=2)]);
              ['tsrc', 'reqr', 'table', 'form'].forEach(key => delete propform_data[key]);
              rrow.attr('hdt',JSON.stringify(propform_data));
              popup.remove();
            }
          }),
          // -- Delete
          (newrec)?null:$('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function() {
            if (confirm('Are you sure you want to mark this item for deletion?')) {
              row.addClass('delete');
              popup.remove();
            }
          }),
          // -- Close
          $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
        ]
      });

      let popup_html = popup.html();
      popup_html.find('select[name=tsrc]').parent().hide();
      popup_html.find('select[name=reqr]').parent().hide();
      popup_html.find('select[name=type]').change(function() {
        mod.objconf.properties.typechange(this);
      });
      mod.objconf.properties.typechange(popup_html.find('select[name=type]'), false, (rdata.type_objtype!=null)?rdata.type_objtype:rdata.type_valuemap);
      table.table().html().parents('.obTabs-tab-content').append(popup_html);

    },

    /******************************************************************
     * mod.objconf.properties.typechange(target, animate)
     * ==================
     * Function to load, show or hide the tscr select element based on
     * the selected value type.
     *   target     : Select element
     *   animate    : Animate show/hide element (default = true)
     *   tsrc       : Type Source UUID (default = null)
     ******************************************************************/
    typechange: function(target, animate=true, tsrc=null) {

      // Prepare
      target = $(target);
      let form = target.parents('form');
      let elmn = {
        tsrc: form.find('select[name=tsrc]'),
        reqr: form.find('select[name=reqr]')
      };
      let types = {
        "3": mod.objconf.objtypes,
        "4": mod.objconf.valuemap
      }
      let anim = (animate)?200:0;
      anim = [anim,anim];

      // Field: Type, Source
      if (target.val() in {"3":1,"4":2}) {
        elmn.tsrc.empty();
        $.each(types[target.val()], function(idx, type) {
          let option = $('<option/>').text(type.name).val(type.id);
          if (tsrc != null) {
            if (type.id == tsrc) {
              option.attr('selected','selected');
            }
          }
          elmn.tsrc.append(option);
        });
        elmn.tsrc.parent().show(anim[0]);
        anim[0] = anim[0]*2;
      }
      else {
        elmn.tsrc.empty();
        elmn.tsrc.parent().hide(anim[1]);
        anim[1] = anim[1]*2;
      }

      // Field: Required
      if ($.inArray(target.val(), ["3","4","5"]) != -1) {
        elmn.reqr.parent().hide(anim[0]);
      }
      else {
        elmn.reqr.parent().show(anim[1]);
      }
    }
  }

}