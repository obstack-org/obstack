/******************************************************************
 *
 * mod
 *  .group
 *    .list()         Function
 *    .open()         Function
 *    .open_htgen()   Function
 *    .save()         Function
 *    .members        Object
 *      .open()       Function
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
    state.set('group');
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
        name: [ $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }), 'Groups' ],
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
        mod.group.open_htgen(null, {}, {}, api_objecttype);
      });
    }
    // Open
    else {
      $.when(
        api('get',`auth/group/${id}`),
        api('get',`auth/group/${id}/member`),
        api('get',`auth/group/${id}/acl`)
      ).done(function(api_group, api_member, api_acl) {
        mod.group.open_htgen(id, api_group[0], api_member[0], api_acl[0]);
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
  open_htgen: function(id, api_group, api_member, api_acl) {

    for (let i=0; i<api_acl.length; i++) {
      api_acl[i]['read']   = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['read'] });
      api_acl[i]['create'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['create'] });
      api_acl[i]['update'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['update'] });
      api_acl[i]['delete'] = $('<input/>', { type:'checkbox', class:'nomrg', checked:api_acl[i]['delete'] });
    }

    let grpform = new obForm([
      { id:'groupname',  name:'Name',  type:'string', regex_validate:/^.+/, value:(id==null)?'':api_group.groupname },
      { id:'ldapcn',     name:'LDAP Group mapping', type:'string', value:(id==null)?'':api_group.ldapcn, info:'LDAP group CN for automatic membership, see documentation for more info' },
      { id:'radiusattr', name:'Radius Attribute mapping', type:'string', value:(id==null)?'':api_group.radiusattr, info:'Radius attribute for automatic membership, see documentation for more info' }
    ]);

    for (let i=0; i<api_member.length; i++) {
      api_member[i]['active'] = htbool(api_member[i]['active']);
      api_member[i]['delete'] = $('<img/>', { class:'pointer', src:'img/icbin.png', width: 14 })
        .on('click', function() {
          let tr = $(this).parents('tr');
          if (tr.hasClass('delete')) {
            tr.removeClass('delete');
          }
          else {
            tr.addClass('delete');
          }
        });
    }

    let usrlist = new obFTable({
      table: {
        id: '652e14bd1b293bce298e1dc615ada9f5aea9f318',
        data: api_member,
        columns: [
          { id: 'username', name:'Username' },
          { id: 'firstname', name:'First name' },
          { id: 'lastname', name:'Last name' },
          { id: 'active', name:'Active' },
          { id: 'delete', name:'Delete' }
        ],
        columns_resizable: true,
        columns_hidden: ['id'],
        columns_allowhtml: ['active', 'delete']
      },
      search: true,
      create: function() { mod.group.members.open(id, usrlist); }
    });

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
        columns_hidden: ['id'],
        columns_allowhtml: ['read', 'create', 'update', 'delete']
      },
      search: true
    });

    let obtabs = new obTabs({
      tabs:[
        { title:'Group',  html:grpform.html() },
        { title:'Users',  html:$('<div/>', { class:'content-tab-wrapper' }).append( usrlist.html() )},
        { title:'Access', html:$('<div/>', { class:'content-tab-wrapper' }).append( acclist.html() )}
      ]
    });

    content.empty().append(new obContent({
      name: [
        $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }),
        $('<a/>', { class:'link', html:'Groups', click:function() { change.check(function() { mod.group.close(); }); } }), ` / ${(id==null)?'[new]':api_group.groupname}`
      ],
      content: obtabs.html(),
      control: [
        // -- Save
        $('<input/>', { class: 'btn', type: 'submit', value: 'Save' }).on('click', function() {
          let grpform_data = grpform.validate();
          if (grpform_data != null) {
            change.reset();
            mod.group.save(id, grpform_data, usrlist.table(), acclist.table());
          }
        }),
        // -- Delete
        (id==null)?null:$('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function() {
          obAlert('Are you sure you want to delete this group?', { Ok:function(){
            $.when(
              api('delete',`auth/group/${id}/acl`)
            ).always(function() {
              $.when(
                api('delete',`auth/group/${id}`)
              ).always(function() {
                change.reset();
                mod.group.close();
              });
            });
          }, Cancel:null });
        }),
        // -- Close
        $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function() {
          change.check(function() { mod.group.close(); });
        }),
      ]
    }).html());

    change.observe();

  },

  /******************************************************************
   * mod.group.save(id, settings, permissions)
   * ===================
   * Save profile
   *    id          : Group UUID
   *    settings    : Form data
   *    permissions : Permissions table
   ******************************************************************/
  save: function(id, grpform, usrlist, acclist) {
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Prepare data formats
    let dtsave = grpform;
    let aclsave = [];

    if (usrlist != null) {
      dtsave['users'] = [];
      $.each(usrlist.html().find('tbody').children('tr'), function() {
        let tr = $(this);
        if (!tr.hasClass('delete')) {
          dtsave.users = [...dtsave.users, JSON.parse(tr.attr('hdt')).id];
        }
      });
    }

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
  },

  /******************************************************************
   * mod.group.members
   * ===============
   * Array of functions and subarrays for managing group membership
   ******************************************************************/
  members: {

    /******************************************************************
     * mod.group.members.open(id, table)
     * ===============================
     * Open the group select form
     *   id       : Group UUID
     *   table    : Table with selected users
     ******************************************************************/
    open: function(id, table) {

      $.when(
        api('get',`auth/user`)
      ).done(function(api_users) {

        // Prepare data
        let members_selected = [];
        $.each(table.table().html().find('tbody').find('tr'), function(idx, tr) {
          members_selected = [...members_selected, JSON.parse($(tr).attr('hdt')).id];
        });
        for (let i=0; i<api_users.length; i++) {
          if ( members_selected.indexOf(api_users[i].id) != -1) {
            delete api_users[i];
          }
          else {
            api_users[i]['active'] = htbool(api_users[i]['active']);
            delete(api_users[i]['tokens']);
            delete(api_users[i]['sa']);
          }
        }
        api_users = api_users.flat();

        // Table
        let grplist = new obFTable({
          table: {
            id: '7ddf83906ec857525e73f93fd3c482ede1287cf6',
            data: api_users,
            columns: [
              { id:'username', name:'Username', orderable:true },
              { id:'firstname', name:'First name', orderable:true },
              { id:'lastname', name:'Last name', orderable:true },
              { id:'active', name:'Active', orderable:true }
            ],
            columns_hidden: ['id'],
            columns_allowhtml: ['active']
          },
          search: true,
          open:   function(td) {
            let tr = $(td).parent('tr');
            let tds = [];
            $.each(tr.children('td'), function(){ tds = [...tds, $(this).html()]; });
            let newrow = table.addrow({
              username:tds[0],
              firstname:tds[1],
              lastname:tds[2],
              active:tds[3],
              delete:$('<img/>', { class:'pointer', src:'img/icbin.png', width:14 })
                .on('click', function() {
                  tr = $(this).parents('tr');
                  if (tr.hasClass('delete')) {
                      tr.removeClass('delete');
                  }
                  else {
                    tr.addClass('delete');
                  }
                })
            });
            newrow.attr('hdt',tr.attr('hdt'));
            popup.remove();
          },
          footer: 'Groups'
        });

        // Popup
        let popup = new obPopup({
          content: grplist.html(),
          control: $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
        });
        popup.html().find('.tblwrap-control-search').removeClass('tblwrap-control-search').addClass('tblwrap-control-search-left');
        $('#_obTab1-content').append(popup.html());

        $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });

      });

    }

  }

}