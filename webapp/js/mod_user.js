/******************************************************************
 *
 * mod
 *  .user
 *    .self           Object
 *    .list()         Function
 *    .open()         Function
 *    .open_htgen()   Function
 *    .save()         Function
 *    .close()        Function
 *    .groups         Object
 *      .open()       Function
 *    .token          Object
 *      .open()       Function
 *
 ******************************************************************/

mod['user'] = {

  /******************************************************************
   * mod.user.self
   * ====================
   * Object containing active user info (for display only)
   ******************************************************************/
  self: {},

  /******************************************************************
   * mod.user.list()
   * ===================
   * List users
   ******************************************************************/
  list: function() {

    // Loader
    state.set('user');
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Load and display data
    $.when(
      api('get','auth/user')
    ).done(function(api_user) {

      $.each(api_user, function(idx) {
        api_user[idx].active = htbool(api_user[idx].active);
        api_user[idx].tokens = htbool(api_user[idx].tokens);
        api_user[idx].sa = htbool(api_user[idx].sa);
      });

      content.empty().append(new obContent({
        name:     [ $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }), 'Users' ],
        content:  new obFTable({
          table: {
            id: 'e1d79a905880b70f7cb789a9060cda9c23d7f87e',
            data: api_user,
            columns: [
              { id:'username', name:'Username', orderable:true },
              { id:'firstname', name:'First name', orderable:true },
              { id:'lastname', name:'Last name', orderable:true },
              { id:'active', name:'Active', orderable:true },
              { id:'tokens', name:'Tokens', orderable:true },
              { id:'admin', name:'Admin', orderable:true }
            ],
            columns_orderable: true,
            columns_resizable: true,
            columns_hidden: ['id'],
            columns_allowhtml: ['active', 'tokens', 'sa']
          },
          search:   true,
          create:   function() { mod.user.open(null); },
          open:     function(td) {
            content.empty().append(loader.removeClass('fadein').addClass('fadein'));
            mod.user.open(JSON.parse($(td).parent().attr('hdt')).id);
          },
          footer:   'Users'
        }).html(),
        control:  []
      }).html());

      $.each(content.find('.obTable-tb'), function() { $(this).obTableRedraw(); });
      loader.remove();

    });
  },

  /******************************************************************
   * mod.user.open(id)
   * ===================
   * Open user or own profile
   *    id    : User UUID, null for own profile
   ******************************************************************/
  open: function(id) {
    // New
    if (id == null) {
      mod.user.open_htgen(null, { active:true }, {}, {});
    }
    // Open
    else if (id == 'self') {
      $.when(
        api('get',`auth/user/${id}`),
        api('get',`auth/user/${id}/token`)
      ).done(function(api_user, api_tokens) {
        mod.user.open_htgen(id, api_user[0], {}, api_tokens[0]);
      });
    }
    else {
      $.when(
        api('get',`auth/user/${id}`),
        api('get',`auth/user/${id}/group`),
        api('get',`auth/user/${id}/token`)
      ).done(function(api_user, api_groups, api_tokens) {
        mod.user.open_htgen(id, api_user[0], api_groups[0], api_tokens[0]);
      });
    }
  },

  /******************************************************************
   * mod.user.open_htget(id, api_..)
   * ===================
   * Generate page from API data
   *    id      : User UUID, null for own profile
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(id, api_user, api_groups, api_tokens) {

    let self = (id == 'self');
    content.empty().append(loader.removeClass('fadein').addClass('fadein'));

    // Prepare
    let grplist = null;
    let tknlist = null;
    let obtabs = null;

    // Form
    let frmpw = '•••••••';
    if (id == null) {
      frmpw = '';
      api_user.totp = cfg.settings.totp_default_enabled;
    }
    let usrform_config = [
      { id:'username', name:'Username', type:'string', regex_input:/^[a-zA-Z][a-zA-Z0-9-_]{0,12}$/, regex_validate:/^[a-zA-Z][a-zA-Z0-9-_]{1,12}$/, value:api_user.username, readonly:(id!=null) },
      { id:'password', name:'Password', type:'password', info:'Minimum length: 7', regex_validate:/^.{7,}$/, value:frmpw },
      { id:'passvrfy', name:'Password (verify)', type:'password', regex_validate:/^.{7,}$/, value:frmpw }
    ];

    // Form (extend)
    if (self && api_user.totp) {
      usrform_config = [...usrform_config,
        { id:'totp_reset',      name:'2FA Reset',  type:'select',  options:{0:'No', 1:'Yes'}, value:0, info:'Reset your 2FA token on save' }
      ];
    }
    if (!self) {
      usrform_config = [...usrform_config,
        { id:'totp',      name:'2-Factor authentication', type:'select',  options:{0:'Disabled', 1:'Enabled', 2:'Reset token'}, value:(api_user.totp)?1:0, info:'2FA TOTP using FreeOTP. Ignored on token login.' },
        { id:'firstname', name:'First name',  type:'string', regex_validate:/^.{2,}$/, info:'Minimum length: 2', value:api_user.firstname },
        { id:'lastname',  name:'Last name',   type:'string', regex_validate:/^.{2,}$/, info:'Minimum length: 2', value:api_user.lastname },
        { id:'active',    name:'Active',      type:'checkbox', value:api_user.active },
        { id:'tokens',    name:'Tokens',      type:'checkbox', value:api_user.tokens, info:'Change requires save to activate' },
        { id:'sa',        name:'Admin',       type:'checkbox', info:'Full access to all components', value:api_user.sa },
      ]
    };
    let usrform = new obForm(usrform_config);

    // Groups
    if (!self) {
      $.each(api_groups, function(idx) {
        api_groups[idx]['delete'] = $('<img/>', { class:'pointer', src:'img/icbin.png', width: 14 })
          .on('click', function() {
            let tr = $(this).parents('tr');
            if (tr.hasClass('delete')) {
              tr.removeClass('delete');
            }
            else {
              tr.addClass('delete');
            }
          });
      });
      grplist = new obFTable({
        table: {
          id: 'c0dd88fcec3bfa4b2decb41f3f14a7fa1fb5d60a',
          data: api_groups,
          columns: [
            { id:'name', name:'Name' },
            { id:'delete', name:'Delete' },
          ],
          columns_resizable: true,
          columns_hidden: ['id'],
          columns_allowhtml: ['delete']
        },
        search:   true,
        create:   function() { mod.user.groups.open(id, grplist); }
      });
    }

    // Tokens
    if (api_user.tokens) {
      tknlist = new obFTable({
        table: {
          id: 'f51ce9052efad7f2933a13ca75792c875a63514f',
          data: api_tokens,
          columns: [
            { id:'name', name:'Name' },
            { id:'expiry', name:'Expires' }
          ],
          columns_resizable: true,
          columns_hidden: ['id']
        },
        search:   true,
        create:   function() { mod.user.token.open(id, tknlist, null); },
        open:     function(td) {
          mod.user.token.open(id, tknlist, $(td).parent() );
        }
      });
    }

    // Tabs
    obtabs = new obTabs({
      tabs: [
        { title:'Profile', html:usrform.html() },
        (self)?null:{ title:'Groups', html:$('<div/>', { class:'content-tab-wrapper' }).append(grplist.html()) },
        (!api_user.tokens)?null:{ title:'Tokens', html:$('<div/>', { class:'content-tab-wrapper' }).append(tknlist.html()) }
      ]
    });

    // Draw
    content.append(new obContent({
      name: (self)?[
        $('<img/>', { src: 'img/icuss.png', class:'content-header-icon' }),
        'Profile'
      ] : [
        $('<img/>', { src: 'img/iccgs.png', class:'content-header-icon' }),
        $('<a/>', { class:'link', html:'Users', click: function() {
          change.check(function() { mod.user.close(id); });
        } }), ` / ${(id==null)?'[new]':api_user.username}`
      ],
      content: obtabs.html(),
      control: [
        // -- Save
        $('<input/>', { class:'btn', type:'submit', value:'Save' }).on('click', function() {
          let usrform_data = usrform.validate();
          if (usrform_data != null) {
            if (usrform_data.passvrfy != usrform_data.password) {
              usrform.html().find('#passvrfy').prop('style', 'box-shadow: 0 0 5px red');
              obtabs.showtab('_obTab0');
            }
            else {
              if (usrform_data.password == '•••••••') {
                delete usrform_data.password;
              }
              delete usrform_data.passvrfy;
              change.reset();
              mod.user.save(id, usrform_data, (grplist == null)?null:grplist.table());
            }
          }
          else {
            obtabs.showtab('_obTab0');
          }
        }),
        // -- Delete
        ((id==null)||(id=='self'))?null:$('<input/>', { class:'btn', type:'submit', value:'Delete'  }).on('click', function() {
          obAlert('Are you sure you want to delete this user?', { Ok:function(){
            $.when( api('delete',`auth/user/${id}`) ).always(function() {
              change.reset();
              mod.user.close();
            });
          }, Cancel:null });
        }),
        // -- Close
        ((id==null)||(id=='self'))?null:$('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() {
          change.check(function() { mod.user.close(id); });
        })
      ]
    }).html());

    loader.remove();
    change.observe();

  },

  /******************************************************************
   * mod.user.save(id, usrform, grplist)
   * ===================
   * Save profile
   *    id        : User UUID, null for own profile
   *    usrform   : Form data
   *    grplist   : Group membership
   ******************************************************************/
  save: function(id, usrform, grplist) {
    content.append(loader.removeClass('fadein').addClass('fadein'));

    // Prepare data formats
    let dtsave = usrform;
    if (grplist != null) {
      dtsave['groups'] = [];
      $.each(grplist.html().find('tbody').children('tr'), function() {
        let tr = $(this);
        if (!tr.hasClass('delete')) {
          dtsave.groups = [...dtsave.groups, JSON.parse(tr.attr('hdt')).id];
        }
      });
    }

    // TOTP
    if (id=='self') {
      usrform.totp_reset = parseInt(usrform.totp_reset);
    }
    else {
      usrform.totp = parseInt(usrform.totp);
      if (usrform.totp == 2) {
        delete usrform.totp;
        usrform.totp_reset = 1;
      }
    }

    // Save
    if (id == null) {
      $.when( api('post',`auth/user`,dtsave) ).always(function() { mod.user.close(); });
    }
    else {
      delete dtsave.username;
      if (!jQuery.isEmptyObject(dtsave)) {
        $.when( api('put',`auth/user/${id}`,dtsave) ).always(function() {
          if (id=='self') {
            setTimeout(function(){ mod.user.open('self'); }, 800);
          }
          else {
            mod.user.close();
          }
        });
      }
      else {
        mod.user.close();
      }
    }

  },

  /******************************************************************
   * mod.user.close(id)
   * ===================
   * Close profile
   *    id        : User UUID, return to previous screen
   ******************************************************************/
  close: function(id) {
    if (id == 'self') {
      $.when(
        api('get','objecttype')
      ).done(function(objecttypes) {
        if (objecttypes.length >= 1) {
          mod.user.list(objecttypes[0].id);
        }
      });
    }
    else {
      mod.user.list();
    }
  },

  /******************************************************************
   * mod.user.groups
   * ===============
   * Array of functions and subarrays for managing group membership
   ******************************************************************/
  groups: {

    /******************************************************************
     * mod.user.groups.open(id, table)
     * ===============================
     * Open the group select form
     *   id       : User UUID
     *   table    : Table with selected groups
     ******************************************************************/
    open: function(id, table) {

      $.when(
        api('get',`auth/group`)
      ).done(function(api_groups) {

        // Prepare data
        let groups_selected = [];
        $.each(table.table().html().find('tbody').find('tr'), function(idx, tr) {
          groups_selected = [...groups_selected, JSON.parse($(tr).attr('hdt')).id];
        });
        for (let i=0; i<api_groups.length; i++) {
          if ( groups_selected.indexOf(api_groups[i].id) != -1) {
            delete api_groups[i];
          }
          else {
            delete(api_groups[i]['ldapcn']);
            delete(api_groups[i]['radiusattr']);
          }
        }
        api_groups = api_groups.flat();

        // Table
        let grplist = new obFTable({
          table: {
            id: '7ddf83906ec857525e73f93fd3c482ede1287cf6',
            data: api_groups,
            columns: [  { id:'groupname', name:'Group', orderable:true } ],
            columns_hidden: ['id']
          },
          search: true,
          open:   function(td) {
            td = $(td);
            let tr = td.parent('tr');
            let newrow = table.addrow({
              name: td.text(),
              delete: $('<img/>', { class:'tblc-icon', src:'img/icbin.png' })
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

  },

  /******************************************************************
   * mod.user.token
   * ==============
   * Array of functions and subarrays for editing tokens
   ******************************************************************/
  token: {

    /******************************************************************
     * mod.user.token.open(id, table, token)
     * ===============================
     * Open the value form
     *   id       : User UUID, null for own profile
     *   table    : Token table
     *   row      : Selected table row (null for new)
     ******************************************************************/
    open: function(id, table, row) {

      // Form
      let rowid = null;
      let rowdata = [null, null];
      if (row != null) {
        rowid = JSON.parse(row.attr('hdt')).id;
        let rowcs = row.find('td');
        rowdata = [
          rowcs[0].innerText,
          rowcs[1].innerText
        ];
      }
      let tknform = new obForm([
        { id:'name',   name:'Name',    type:'string', regex_validate:/^.+/, value:rowdata[0] },
        { id:'expiry', name:'Expires', type:'datetime', regex_validate:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/, value:rowdata[1] }
      ]);

      // Control buttons
      let popup_control = [];
      if (row == null) {
        popup_control = [
          // -- Create
          $('<input/>', { class:'btn', type:'submit', value:'Create' }).on('click', function() {
            let tknform_data = tknform.validate();
            if (tknform_data != null) {
              tknform_data.expiry.replace(/[A|P]M/,'');
              $.when(
                api('post',`auth/user/${id}/token`, { name:tknform_data.name, expiry:tknform_data.expiry })
              ).done(function(api_token) {
                popup.remove();
                let tknpopup = new obPopup({
                  size: 'small',
                  content: new obForm([
                    { id:'token', name:'Token: (only provided once)', type:'string', value:api_token.token, readonly:true }
                  ]).html(),
                  control: $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { tknpopup.remove(); })
                });
                table.addrow({id:api_token.id, name:tknform_data.name, expiry:tknform_data.expiry.replace('T',' ')});
                table.table().html().parents('.obTabs-tab-content').append(tknpopup.html());
              });
            }
          }),
        ];
      }
      else {
        popup_control = [
          // -- OK
          $('<input/>', { class:'btn', type:'submit', value:'Ok' }).on('click', function() {
            let tknform_data = tknform.validate();
            if (tknform_data != null) {
              tknform_data.expiry = tknform_data.expiry.replace('T',' ');
              $.when(
                api('put',`auth/user/${id}/token/${rowid}`, tknform_data)
              ).done(function(api_token) {
                row.remove();
                table.addrow({id:rowid, ...tknform_data});
                popup.remove();
              });
            }
          }),
          // -- Delete
          $('<input/>', { class:'btn', type:'submit', value:'Delete' }).on('click', function() {
            obAlert('<b>WARNING!:</b><br>Token will be deleted instantly. Are you sure you want to continue?', { Ok:function(){
              $.when(
                api('delete',`auth/user/${id}/token/${rowid}`)
              ).done(function() {
                row.remove();
                popup.remove();
              });
            }, Cancel:null });
          })
        ];
      }
      popup_control = [
        // -- Close
        ...popup_control,
        $('<input/>', { class:'btn', type:'submit', value:'Close' }).on('click', function() { popup.remove(); })
      ];

      // Popup
      let popup = new obPopup({
        size: 'medium',
        content: tknform.html(),
        control: popup_control
      });
      table.table().html().parents('.obTabs-tab-content').append(popup.html());

    }
  }

}