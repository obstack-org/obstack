/******************************************************************
 *
 * mod
 *  .group
 *    .list()         Function
 *    .open()         Function
 *    .open_htgen()   Function
 *    .save()         Function
 *
 ******************************************************************/

mod['group'] = {

  /******************************************************************
   * mod.group.list()
   * ===================
   * List groups
   ******************************************************************/
  list: function() {

    // Loader
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get',`auth/group`)
    ).done(function(api_group) {

      let grplist = new obFTable({
        table: {
          id: 'bafcbdf92ba060eea91ab3940a61ecf29398c9ad',
        data: api_group,
        columns: [ { id: 'groupname', name:'Group name', orderable: true } ],
        columns_orderable: true,
        columns_resizable: true,
        columns_hidden: ['id', 'ldapcn', 'radiusattr']
        },
        search:   true,
        create:   function() { mod.group.open(null); },
        open:     function(td) {
          mod.group.open(JSON.parse($(td).parent().attr('hdt')).id);
        },
        footer:   'Groups'
      });

      content.empty().append(new obContent({
        name: api_group.name,
        content: grplist.html()
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });

      loader.remove();
    });

  },

  /******************************************************************
   * mod.group.open(id)
   * ===================
   * Open group
   *    id    : Group UUID
   ******************************************************************/
  open: function(id) {
    // New
    if (id == null) {
      $.when(
        api('get',`objecttype`)
      ).done(function(api_objecttype) {
        for (let i=0; i<api_objecttype.length; i++) {
          api_objecttype[i]['read']   = false;
          api_objecttype[i]['create'] = false;
          api_objecttype[i]['update'] = false;
          api_objecttype[i]['delete'] = false;
        }
        mod.group.open_htgen(null, {}, api_objecttype);
      });
    }
    // Open
    else {
      $.when(
        api('get',`auth/group/${id}`),
        api('get',`auth/group/${id}/acl`)
      ).done(function(api_group, api_acl) {
        mod.group.open_htgen(id, api_group[0], api_acl[0]);
      });
    }
  },

  /******************************************************************
   * mod.group.open_htget()
   * ===================
   * Generate page from API data
   *    id      : Group UUID
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(id, api_group, api_acl) {

    for (let i=0; i<api_acl.length; i++) {
      api_acl[i]['read']   = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['read'] });
      api_acl[i]['create'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['create'] });
      api_acl[i]['update'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['update'] });
      api_acl[i]['delete'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['delete'] });
    }

    let grpform = new obForm([
      { id:'groupname',  name:'Name',  type:'string', regex_validate:/^.+/, value:(id==null)?'':api_group.groupname },
      { id:'ldapcn',     name:'LDAP CN', type:'string', value:(id==null)?'':api_group.ldapcn },
      { id:'radiusattr', name:'Radius Attribute', type:'string', value:(id==null)?'':api_group.radiusattr }
    ]);

    let acclist = new obFTable({
      table: {
        id: 'f35f45de66157761e7d7e4b7361beb840a3ec9ef',
        data: api_acl,
        columns: [
          { id: 'name', name:'Name' },
          { id: 'read', name:'Read' },
          { id: 'create', name:'Create' },
          { id: 'update', name:'Update' },
          { id: 'delete', name:'Delete' }
        ],
        columns_resizable: true,
        columns_hidden: ['id']
      },
      search: true
    });

    let obtabs = new obTabs({
      tabs:[
        { title:'Group',  html:grpform.html() },
        { title:'Access', html:$('<div/>', { class:'content-tab-wrapper' }).append( acclist.html() )}
      ]
    });

    content.append(new obContent({
      name: [ $('<a/>', { class:'link', html:'Object types', click:function() { mod.group.list(); } }), ` / ${(id==null)?'[new]':api_group.groupname},` ],
      content: obtabs.html(),
      control: [
        // -- Save
        $('<input/>', { class: 'btn', type: 'submit', value: 'Save' }).on('click', function() {
          let grpform_data = grpform.validate();
          if (grpform_data != null) {
            mod.group.save(id, grpform_data, acclist.table());
          }
        }),
        // -- Delete
        (id==null)?null:$('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function() {
          if (confirm('Are you sure you want to delete this group?')) {
            $.when(
              api('delete',`auth/group/${id}/acl`)
            ).always(function() {
              $.when(
                api('delete',`auth/group/${id}`)
              ).always(function() {
                mod.group.close();
              });
            });
          }
        }),
        // -- Close
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function() { mod.group.close(); }),
      ]
    }).html());

    /*
    // Generate HTML
    let group = {
      config:     $('<form/>', { class: 'content-form' }),
    }
    let acllist = {
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

    for (let i=0; i<api_acl.length; i++) {
      api_acl[i]['read']   = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['read'] });
      api_acl[i]['create'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['create'] });
      api_acl[i]['update'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['update'] });
      api_acl[i]['delete'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['delete'] });
    }

    acllist.table = new obTable({
      id: 'f35f45de66157761e7d7e4b7361beb840a3ec9ef',
      data: api_acl,
      columns: [
        { id: 'name', name:'Name' },
        { id: 'read', name:'Read' },
        { id: 'create', name:'Create' },
        { id: 'update', name:'Update' },
        { id: 'delete', name:'Delete' }
      ],
      columns_resizable: true,
      columns_hidden: ['id']
    });
    acllist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { acllist.table.search(this.value); }) );
    acllist.content.append(acllist.table.html());
    acllist.footer.append(`Assigned object types: ${api_acl.length}`);

    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        ctwrap.tabs,
        ctwrap.control
      )
    );
    let ctabs = [
      { title: 'Group',   html: $('<div/>', { class: 'content-tab-wrapper' }).append( group.config ) },
      { title: 'Access',  html: $('<div/>', { class: 'content-tab-wrapper' }).append(
        acllist.control,
        acllist.content,
        acllist.footer,
        acllist.control
      )}

    ];

    ctwrap.name.append(
      $('<a/>', {
        class: 'link',
        html: 'Groups',
        click: function() { mod.group.list(); }
      }),
      ` / ${api_group.groupname}`
    );

    ctwrap.tabs.obTabs({
      tabs: ctabs
    });

    // Format and load form fields
    jfschema = {
      groupname:  { title: 'Name',  type: 'string', default: api_group.groupname },
      ldapcn:     { title: 'LDAP CN',  type: 'string', default: api_group.ldapcn },
      radiusattr: { title: 'Radius Attribute',  type: 'string', default: api_group.radiusattr }
    }
    group.config.jsonForm({
      schema: jfschema,
      form: ['*']
    });

    // Add onclick for empty password field
    group.config.find(':input').each(function() {
      if ($(this).prop('name').substring(0,4) == 'pass') {
        $(this).on('click', function(event) {
          if ($(this).val() == '•••••••') {
            $(this).val('');
          }
        });
      }
    });

    // Add object type buttons
    ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.group.save(id, group.config, acllist.table); }) );
    if ((id != null) && (id != 'self')) {
      ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) {
        if (confirm('Are you sure you want to delete this group?')) {
          $.when(
            api('delete',`auth/group/${id}/acl`)
          ).always(function() {
            $.when(
              api('delete',`auth/group/${id}`)
            ).always(function() {
              mod.group.close();
            });
          });
        }
      }));
    }
    ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.group.close(); }) );
    */

  },

  /******************************************************************
   * mod.group.save(id, settings, permissions)
   * ===================
   * Save profile
   *    id          : Group UUID
   *    settings    : Form data
   *    permissions : Permissions table
   ******************************************************************/
  save: function(id, grpform, acclist) {
    // Prepare data formats
    let dtsave = grpform;
    let aclsave = [];

    // Process ACL
    $.each(acclist.html().find('tbody').children('tr'), function() {
      let tr = this;
      aclsave = [...aclsave, {
        'id'    : JSON.parse($(tr).attr('hdt')).id,
        'read'  : $(tr.cells[1]).find('input').prop('checked'),
        'create': $(tr.cells[2]).find('input').prop('checked'),
        'update': $(tr.cells[3]).find('input').prop('checked'),
        'delete': $(tr.cells[4]).find('input').prop('checked')
      }];
    });

    // Send to API
    if (id == null) {
      $.when(
        api('post',`auth/group`, dtsave)
      ).always(function(api_group) {
        $.when(
          api('post',`auth/group/${api_group.id}/acl`, aclsave)
        ).always(function() {
          mod.group.close();
        });
      });
    }
    else {
      $.when(
        api('put',`auth/group/${id}`,dtsave),
        api('put',`auth/group/${id}/acl`, aclsave),
      ).always(function() { mod.group.close(); });
    }
  },

  close: function() {
    mod.group.list();
  }

}