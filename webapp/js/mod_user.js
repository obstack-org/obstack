/******************************************************************
 *
 * mod
 *  .user
 *    .self           Object
 *    .list()         Function
 *    .open()         Function
 *    .open_htgen()   Function
 *    .save()         Function
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

    // Generate HTML with loader
    let usrlist = {
      control:  $('<div/>', { class: 'tblwrap-control' }),
      content:  $('<div/>', { class: 'tblwrap-table' }),
      footer:   $('<div/>', { class: 'tblwrap-footer' }),
      table:    null
    };
    let ctwrap = {
      name:     $('<div/>').append('Users'),
      control:  $('<div/>', { class: 'content-wrapper-control' })
    }
    content.empty().append(
      $('<div/>', { class: 'content-header' }).html(ctwrap.name),
      $('<div/>', { class: 'content-wrapper' }).append(
        usrlist.control,
        usrlist.content,
        usrlist.footer,
        ctwrap.control
      )
    );

    // Load and display data
    content.append(loader.removeClass('fadein').addClass('fadein'));
    $.when(
      api('get','auth/user')
    ).done(function(api_user) {
      for (let i=0; i<api_user.length; i++) {
        api_user[i].sa = htbool(api_user[i].sa);
        api_user[i].active = htbool(api_user[i].active);
        api_user[i].tokens = htbool(api_user[i].tokens);
      }
      usrlist.table = new obTable({
        id: 'e1d79a905880b70f7cb789a9060cda9c23d7f87e',
        data: api_user,
        columns: [
          { id: 'username', name:'Username', orderable: true },
          { id: 'firstname', name:'First name', orderable: true },
          { id: 'lastname', name:'Last name', orderable: true },
          { id: 'active', name:'Active', orderable: true },
          { id: 'tokens', name:'Tokens', orderable: true },
          { id: 'admin', name:'Admin', orderable: true }
        ],
        columns_orderable: true,
        columns_resizable: true,
        columns_hidden: ['id']
      });
      usrlist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { usrlist.table.search(this.value); }) );
      usrlist.content.append(usrlist.table.html());
      usrlist.footer.append(`Objects: ${api_user.length}`);
      usrlist.table.html().on('click', 'td', function () {
        content.empty().append(loader);
        mod.user.open(JSON.parse($(this).parent().attr('hdt')).id);
      });

      ctwrap.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
          .on('click', function() {
            mod.user.open(null);
          })
      );

      $.each(content.find('.obTable-tb'), function() {
        $(this).obTableRedraw();
      });
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
      mod.user.open_htgen(null, { active:true }, {});
    }
    // Open
    else {
      $.when(
        api('get',`auth/user/${id}`),
        api('get',`auth/user/${id}/token`)
      ).done(function(api_user, api_tokens) {
        mod.user.open_htgen(id, api_user[0], api_tokens[0]);
      });
    }
  },

  /******************************************************************
   * mod.user.open_htget()
   * ===================
   * Generate page from API data
   *    id      : User UUID, null for own profile
   *    api_..  : API data
   ******************************************************************/
  open_htgen: function(id, api_user, api_tokens) {

    // Generate HTML
    let user = {
      config:     $('<form/>', { class: 'content-form' }),
    }
    let tknlist = {
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

    tknlist.table = new obTable({
      id: 'f51ce9052efad7f2933a13ca75792c875a63514f',
      data: api_tokens,
      columns: [
        { id: 'name', name:'Name' },
        { id: 'expiry', name:'Expires' }
      ],
      columns_resizable: true,
      columns_hidden: ['id']
    });
    tknlist.control.append( $('<input/>', { width: 300, class: 'tblwrap-control-search' }).on('keyup', function() { tknlist.table.search(this.value); }) );
    tknlist.content.append(tknlist.table.html());
    tknlist.footer.append(`Tokens: ${api_tokens.length}`);
    tknlist.table.html().on('click', 'td', function () {
      if (!$(this).hasClass('obTable-drag')) {
        tr = $(this).parent();
        if (tr.hasClass('delete')) {
          if (confirm('Do you want to remove the deletion mark?')) {
            tr.removeClass('delete');
          }
        }
        else {
          let tokenid = JSON.parse(tr.attr('hdt')).id;
          $.when(
            api('get',`auth/user/${id}/token/${tokenid}`)
          ).done(function(api_token) {
            api_token.id = tokenid;
            mod.user.token.open(id, tknlist.table.html(), tr, api_token);
          });
        }
      }
    });
    tknlist.control.append(
      $('<input/>', { class: 'btn', type: 'submit', value: 'Add' })
        .on('click', function(event) {
          mod.user.token.open(id, tknlist.table.html(), null, null);
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
      { title: 'Profile',  html: $('<div/>', { class: 'content-tab-wrapper' }).append( user.config ) }
    ];
    if (api_user.tokens) {
      ctabs = [
        ...ctabs,
        { title: 'Tokens',   html: $('<div/>', { class: 'content-tab-wrapper' }).append(
          tknlist.control,
          tknlist.content,
          tknlist.footer,
          tknlist.control
        )}
      ];
    }

    let self = (id == 'self');
    if (self) {
      ctwrap.name.append('Profile');
    }
    else {
      ctwrap.name.append(
        $('<a/>', {
          class: 'link',
          html: 'Users',
          click: function() { mod.user.list(); }
        }),
        ` / ${api_user.username}`
        );
    }
    ctwrap.tabs.obTabs({
      tabs: ctabs
    });

    // Format and load form fields
    jfpasswd = '';
    if (id != null) {
      jfpasswd = '•••••••';
    }
    jfschema = {
      username: { title: 'Name',  type: 'string', default: api_user.username, readonly:(id!=null) },
      password: { title: 'Password', type: 'password', default:jfpasswd },
      passvrfy: { title: 'Password (verify)', type: 'password', default:jfpasswd }
    }
    if (!self) {
      jfschema['firstname'] = { title: 'First name',  type: 'string',  default: api_user.firstname };
      jfschema['lastname']  = { title: 'Last name',   type: 'string',  default: api_user.lastname };
      jfschema['active']    = { title: 'Active',      type: 'boolean', default: api_user.active };
      jfschema['tokens']    = { title: 'Tokens',      type: 'boolean', default: api_user.tokens };
      jfschema['sa']        = { title: 'Admin',       type: 'boolean', default: api_user.sa };
    }
    user.config.jsonForm({
      schema: jfschema,
      form: ['*']
    });

    // Add onclick for empty password field
    user.config.find(':input').each(function() {
      if ($(this).prop('name').substring(0,4) == 'pass') {
        $(this).on('click', function(event) {
          if ($(this).val() == '•••••••') {
            $(this).val('');
          }
        });
      }
    });

    // Add object type buttons
    ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Save'  }).on('click', function(event) { mod.user.save(id, user.config); }) );
    if ((id != null) && (id != 'self')) {
      ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Delete'  }).on('click', function(event) {
        if (confirm('Are you sure you want to delete this user?')) {
          $.when( api('delete',`auth/user/${id}`) ).always(function() { mod.user.close(); });
        }
      }));
    }
    ctwrap.control.append( $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) { mod.user.close(id); }) );
  },

  /******************************************************************
   * mod.user.save(id, settings)
   * ===================
   * Save profile
   *    id        : User UUID, null for own profile
   *    settings  : Form data
   ******************************************************************/
  save: function(id, settings) {
    // Prepare data formats
    let dtsave = {};
    let frmpassword = null;
    let frmpassvrfy = null;

    // Gather data from settings fields
    settings.find(':input').each(function() {
      if ($(this).prop('type') == 'checkbox') {
        if ($(this).prop('checked')) { dtsave[$(this).prop('name')] = 1; }
        else { dtsave[$(this).prop('name')] = 0; }
      }
      else {
        dtsave[$(this).prop('name')] = $(this).prop('value');
        if ($(this).prop('name') == 'password') { frmpassword = $(this); }
        if ($(this).prop('name') == 'passvrfy') { frmpassvrfy = $(this); }
      }
    });

    // Form validity checks
    let save = true;
    if (dtsave.password.length < 7) {
      save = false;
      frmpassword.prop('style', 'box-shadow: 0 0 5px red');
      $('.sTabs-tab').removeClass('active');
      $('.sTabs-tab-content').hide();
      $('#_sTab0').addClass('active');
      $('#_sTab0-content').show();
    }
    else {
      frmpassword.prop('style', '');
    }
    if (dtsave.password != dtsave.passvrfy) {
      save = false;
      frmpassvrfy.prop('style', 'box-shadow: 0 0 5px red');
      $('.sTabs-tab').removeClass('active');
      $('.sTabs-tab-content').hide();
      $('#_sTab0').addClass('active');
      $('#_sTab0-content').show();
    }

    // Send to API
    if (save) {
      delete dtsave.passvrfy;
      if (dtsave.password == '•••••••') { delete dtsave.password }
      if (id == null) {
        $.when( api('post',`auth/user`,dtsave) ).always(function() { mod.user.close(); });
      }
      else {
        delete dtsave.username;
        $.when( api('put',`auth/user/${id}`,dtsave) ).always(function() { mod.user.close(); });
      }
    }
  },

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
   * mod.user.token
   * ==============
   * Array of functions and subarrays for editing tokens
   ******************************************************************/
  token: {

    /******************************************************************
     * mod.user.token.open(id, table)
     * ===============================
     * Open the value form
     *   id       : User UUID, null for own profile
     *   table   : Value/Token as selected in the table
     ******************************************************************/
    open: function(id, table, row, token) {
      let frmnewrec = (row == null);

      // Generate HTML
      let popup = {
        overlay:  $('<div/>'),
        wrapper:  $('<div/>', { class: 'content-popup-wrapper content-popup-wrapper_medium' }),
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

      // Ok/Create button
      let btnsubmit = 'Ok';
      if (frmnewrec) {
        btnsubmit = 'Create';
      }
      popup.control.append(
        $('<input/>', { class: 'btn', type: 'submit', value: btnsubmit }).on('click', function(event) {
          // Prepare form data
          let fdata = {};
          popup.form.find(':input').each(function() {
            fdata[$(this).prop('name')] = $(this).prop('value');
          });
          // Prepare row data
          let rdata = [
            fdata.name,
            fdata.expiry.replace('T',' ')
          ];

          // Check date format and submit
          if (!rdata[1].match(/[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}$/)) {
            popup.form.find(':input').each(function() {
              if ($(this).prop('name') == 'expiry') {
                $(this).prop('style', 'box-shadow: 0 0 5px red');
              }
            });
          }
          else {
            if (frmnewrec) {
              // Create token
              $.when(
                api('post',`auth/user/${id}/token`, { name:rdata[0], expiry:rdata[1] })
              ).done(function(api_token) {
                table.append(
                  $('<tr/>')
                    .attr('id', null)
                    .attr('hdt', JSON.stringify({ id:api_token.id }))
                    .append(
                      $('<td/>').append(rdata[0]),
                      $('<td/>').append(rdata[1])
                    )
                );
                table.find('.obTable-tb').obTableRedraw();
                popup.wrapper.removeClass('content-popup-wrapper_medium').addClass('content-popup-wrapper_small');
                popup.form.empty().jsonForm({
                  schema: { 'token':   {title:'Token: (only provided once)', type:'string', default:api_token.token, readonly:true } },
                  form: ['*']
                });
                popup.control.empty().append(
                  $('<input/>', { class: 'btn', type: 'submit', value: 'Close' }).on('click', function(event) {
                    popup.overlay.remove();
                  })
                );
              });
            }
            else {
              // Update token
              $.when(
                api('put',`auth/user/${id}/token/${token.id}`, { name:rdata[0], expiry:rdata[1] })
              ).done(function() {
                row
                  .empty()
                  .append(
                    $('<td/>').append(rdata[0]),
                    $('<td/>').append(rdata[1])
                  );
                table.find('.obTable-tb').obTableRedraw();
                popup.overlay.remove();
              });
            }
          }
        })
      );

      // Delete button
      if (!frmnewrec) {
        popup.control.append(
          $('<input/>', { class: 'btn', type: 'submit', value: 'Delete' }).on('click', function(event) {
            if (confirm('WARNING!: Token will be deleted instantly. Are you sure you want to continue?')) {
              $.when(
                api('delete',`auth/user/${id}/token/${token.id}`)
              ).done(function() {
                row.remove();
                popup.overlay.remove();
              });
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
        schema: {
          'name':   {title:'Name', type:'string'},
          'expiry': {title:'Expires', type:'datetime-local'}
        },
        form: ['*']
      });

      // Load data into form for editing
      if (!frmnewrec) {
        popup.form.find('input[name=name]').val(token.name);
        popup.form.find('input[name=expiry]').val(token.expiry);
      }
    }
  }

}